<?php
/**
 * LingQ API client — cURL paginated GET with retry/backoff.
 *
 * Public surface:
 *   $client = new LingqClient($config, $logger);
 *   $allCards = $client->fetchAllCards();   // returns array of associative arrays
 *
 * - Retry: 5xx + timeout only, max $config['retry'] times, backoff 1s/3s/9s.
 * - Fast-fail: any 4xx (401/403/404...) → throws RuntimeException.
 * - Sleep between successful pages: $config['sleep_ms'].
 */

if (!extension_loaded('curl')) {
    fwrite(STDERR, "FATAL: PHP cURL extension not loaded. Enable extension=curl in php.ini.\n");
    exit(1);
}

class LingqClient
{
    /** @var array */
    private $cfg;
    /** @var callable */
    private $log;

    public function __construct(array $cfg, callable $logger)
    {
        $required = ['api_key', 'language', 'base_url', 'page_size', 'timeout', 'retry', 'sleep_ms'];
        foreach ($required as $k) {
            if (!array_key_exists($k, $cfg)) {
                throw new RuntimeException("Config missing key: {$k}");
            }
        }
        if ($cfg['api_key'] === '' || $cfg['api_key'] === 'PASTE_YOUR_LINGQ_TOKEN_HERE') {
            throw new RuntimeException("Config api_key chưa được set. Mở config.php và paste token thật.");
        }
        $this->cfg = $cfg;
        $this->log = $logger;
    }

    /**
     * Fetch all cards across pages. Returns flat array of card dicts.
     */
    public function fetchAllCards()
    {
        $all = [];
        $page = 1;
        $pageSize = (int)$this->cfg['page_size'];
        $lang = $this->cfg['language'];
        $base = rtrim($this->cfg['base_url'], '/');

        while (true) {
            $url = "{$base}/{$lang}/cards/?page={$page}&page_size={$pageSize}";
            $start = microtime(true);
            $resp = $this->getWithRetry($url);
            $ms = (int)round((microtime(true) - $start) * 1000);

            $payload = json_decode($resp['body'], true);
            if (!is_array($payload) || !isset($payload['results'])) {
                throw new RuntimeException("Invalid JSON response on page {$page}: " . substr($resp['body'], 0, 200));
            }

            $results = $payload['results'];
            $count = count($results);
            $totalReported = isset($payload['count']) ? (int)$payload['count'] : null;

            $msg = sprintf("Fetching page %d...   %d cards (HTTP %d, %dms)", $page, $count, $resp['http_code'], $ms);
            call_user_func($this->log, 'INFO', $msg);
            echo $msg . PHP_EOL;

            foreach ($results as $row) {
                $all[] = $row;
            }

            if (empty($payload['next']) || $count === 0) {
                if ($totalReported !== null && count($all) !== $totalReported) {
                    call_user_func($this->log, 'WARN', "API count={$totalReported} nhưng fetched=" . count($all));
                }
                break;
            }

            $page++;
            $this->sleepMs((int)$this->cfg['sleep_ms']);
        }

        return $all;
    }

    /**
     * GET with retry on 5xx / timeout. Returns ['http_code'=>int, 'body'=>string].
     */
    private function getWithRetry($url)
    {
        $attempts = max(1, (int)$this->cfg['retry']);
        $backoff = [1, 3, 9];
        $lastErr = '';

        for ($i = 0; $i < $attempts; $i++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int)$this->cfg['timeout'],
                CURLOPT_HTTPHEADER     => [
                    "Authorization: Token {$this->cfg['api_key']}",
                    "Accept: application/json",
                ],
                CURLOPT_USERAGENT      => "lingq-sync-deutsch/1.0",
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0 || $body === false) {
                $lastErr = "cURL errno={$errno} {$err}";
                call_user_func($this->log, 'WARN', "GET {$url} attempt " . ($i + 1) . " — {$lastErr}");
                if ($i < $attempts - 1) {
                    $this->sleepMs(($backoff[$i] ?? end($backoff)) * 1000);
                    continue;
                }
                throw new RuntimeException("Network error after {$attempts} attempts: {$lastErr}");
            }

            if ($code >= 200 && $code < 300) {
                return ['http_code' => $code, 'body' => $body];
            }

            if ($code >= 400 && $code < 500) {
                // Fast-fail on client errors.
                $snippet = substr($body, 0, 200);
                call_user_func($this->log, 'ERROR', "HTTP {$code} on {$url} — {$snippet}");
                throw new RuntimeException("HTTP {$code} " . $this->httpReason($code) . " — {$snippet}");
            }

            // 5xx — retry.
            $lastErr = "HTTP {$code}";
            call_user_func($this->log, 'WARN', "GET {$url} attempt " . ($i + 1) . " — {$lastErr}");
            if ($i < $attempts - 1) {
                $this->sleepMs(($backoff[$i] ?? end($backoff)) * 1000);
                continue;
            }
            throw new RuntimeException("Server error after {$attempts} attempts: {$lastErr}");
        }

        throw new RuntimeException("Unreachable retry loop exit: {$lastErr}");
    }

    private function sleepMs($ms)
    {
        if ($ms <= 0) return;
        usleep($ms * 1000);
    }

    private function httpReason($code)
    {
        $map = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            429 => 'Too Many Requests',
        ];
        return isset($map[$code]) ? $map[$code] : '';
    }
}
