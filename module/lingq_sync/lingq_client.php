<?php
/**
 * LingQ API client — cURL with retry/backoff for GET (paginated) +
 * POST/PATCH/DELETE (Phase D push).
 *
 * Public surface:
 *   $client = new LingqClient($config, $logger);
 *
 *   // Phase C — read
 *   $allCards = $client->fetchAllCards();        // array of card dicts
 *
 *   // Phase D — write
 *   $created  = $client->createCard($payload);   // returns decoded body (201)
 *   $updated  = $client->updateCard($pk, $patch); // returns decoded body (200)
 *   $resp     = $client->deleteCard($pk);        // returns ['http_code'=>int, 'body'=>string]
 *
 * Retry policy: 5xx + network errors only, max $config['retry'] times,
 * backoff 1s/3s/9s. 4xx fast-fail (createCard/updateCard throw; deleteCard
 * returns http_code so caller can treat 404 as "already gone").
 *
 * Rate limit: sleep $config['sleep_ms'] giữa các successful requests
 * (caller responsibility — client cung cấp helper sleepMs()).
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

    // -------------------------------------------------------------------------
    // Phase C — GET paginated.
    // -------------------------------------------------------------------------

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
            $resp = $this->requestWithRetry('GET', $url);
            $ms = (int)round((microtime(true) - $start) * 1000);

            if ($resp['http_code'] < 200 || $resp['http_code'] >= 300) {
                $snippet = substr($resp['body'], 0, 200);
                call_user_func($this->log, 'ERROR', "HTTP {$resp['http_code']} on {$url} — {$snippet}");
                throw new RuntimeException("HTTP {$resp['http_code']} " . $this->httpReason($resp['http_code']) . " — {$snippet}");
            }

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

    // -------------------------------------------------------------------------
    // Phase D — write methods.
    // -------------------------------------------------------------------------

    /**
     * POST /{lang}/cards/ — tạo card mới.
     * $payload: array với các key term, fragment, hint, status, extended_status, tags (array of strings)
     * Returns decoded response body on 201/200. Throws on 4xx/5xx.
     */
    public function createCard(array $payload)
    {
        $lang = $this->cfg['language'];
        $base = rtrim($this->cfg['base_url'], '/');
        $url  = "{$base}/{$lang}/cards/";
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $resp = $this->requestWithRetry('POST', $url, $body);

        if ($resp['http_code'] < 200 || $resp['http_code'] >= 300) {
            $snippet = substr($resp['body'], 0, 300);
            call_user_func($this->log, 'ERROR', "POST cards HTTP {$resp['http_code']} term=" . (isset($payload['term']) ? $payload['term'] : '?') . " body=" . $snippet);
            throw new RuntimeException("POST card failed HTTP {$resp['http_code']} — {$snippet}");
        }

        $decoded = json_decode($resp['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException("POST card returned invalid JSON: " . substr($resp['body'], 0, 200));
        }
        return $decoded;
    }

    /**
     * PATCH /{lang}/cards/{pk}/ — update card.
     * $patch: chỉ các field thay đổi (fragment/hint/tags). KHÔNG include status
     *         (status preserved từ snapshot — do caller enforce, không phải client).
     * Returns decoded body. Throws on 4xx/5xx.
     */
    public function updateCard($pk, array $patch)
    {
        $lang = $this->cfg['language'];
        $base = rtrim($this->cfg['base_url'], '/');
        $pk   = (string)$pk;
        $url  = "{$base}/{$lang}/cards/{$pk}/";
        $body = json_encode($patch, JSON_UNESCAPED_UNICODE);
        $resp = $this->requestWithRetry('PATCH', $url, $body);

        if ($resp['http_code'] < 200 || $resp['http_code'] >= 300) {
            $snippet = substr($resp['body'], 0, 300);
            call_user_func($this->log, 'ERROR', "PATCH pk={$pk} HTTP {$resp['http_code']} body=" . $snippet);
            throw new RuntimeException("PATCH pk={$pk} failed HTTP {$resp['http_code']} — {$snippet}");
        }

        $decoded = json_decode($resp['body'], true);
        // PATCH có thể trả empty body (204) hoặc body — chấp nhận cả 2.
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * DELETE /{lang}/cards/{pk}/ — xoá card.
     * Returns ['http_code'=>int, 'body'=>string] — caller xử lý 204 (OK) vs 404
     * (already deleted). Throws chỉ khi network/5xx-after-retry hoặc 4xx khác 404.
     *
     * Quyết định: 404 KHÔNG throw vì có thể card đã bị xoá ở session trước
     * (resume scenario). Caller log WARN và đếm là "already gone".
     */
    public function deleteCard($pk)
    {
        $lang = $this->cfg['language'];
        $base = rtrim($this->cfg['base_url'], '/');
        $pk   = (string)$pk;
        $url  = "{$base}/{$lang}/cards/{$pk}/";
        $resp = $this->requestWithRetry('DELETE', $url);

        // 204 = success, 404 = already gone (acceptable). Cả 2 không throw.
        if ($resp['http_code'] === 204 || $resp['http_code'] === 200 || $resp['http_code'] === 404) {
            return $resp;
        }
        if ($resp['http_code'] >= 400 && $resp['http_code'] < 500) {
            $snippet = substr($resp['body'], 0, 300);
            call_user_func($this->log, 'ERROR', "DELETE pk={$pk} HTTP {$resp['http_code']} body=" . $snippet);
            throw new RuntimeException("DELETE pk={$pk} failed HTTP {$resp['http_code']} — {$snippet}");
        }
        // 5xx-after-retry rơi vào đây nếu requestWithRetry không throw (không xảy ra,
        // requestWithRetry đã throw cho 5xx-final-attempt) — phòng hờ.
        throw new RuntimeException("DELETE pk={$pk} unexpected HTTP {$resp['http_code']}");
    }

    // -------------------------------------------------------------------------
    // Phase K — Lessons CRUD (độc lập với CARD methods ở trên).
    //
    // Endpoint verified 2026-05-24 (token thật, GET/OPTIONS):
    //   LIST   GET    /api/v3/{lang}/search/?shelf=my_lessons  (paginate qua 'next', KHÔNG có 'count')
    //   GET    GET    /api/v2/{lang}/lessons/{id}/
    //   DELETE DELETE /api/v2/{lang}/lessons/{id}/             (OPTIONS allow: GET,PUT,PATCH,DELETE)
    //   CREATE POST   /api/v2/{lang}/lessons/                  (OPTIONS allow: GET,POST) — thêm ở K2/K3
    // base_url trong config = .../api/v2; search endpoint là v3 → derive qua v3Base().
    // -------------------------------------------------------------------------

    /**
     * Fetch toàn bộ lessons của user (shelf=my_lessons). Returns flat array of
     * lesson dicts (raw search result — caller normalize).
     *
     * Search endpoint KHÔNG trả 'count' (khác /cards/) → paginate cho tới khi
     * 'next' rỗng hoặc page trả 0 result. page_size kế thừa config.
     */
    public function fetchAllLessons()
    {
        $all = [];
        $page = 1;
        $pageSize = (int)$this->cfg['page_size'];
        $lang = $this->cfg['language'];
        $baseV3 = $this->v3Base();

        while (true) {
            $url = "{$baseV3}/{$lang}/search/?shelf=my_lessons&page={$page}&page_size={$pageSize}";
            $start = microtime(true);
            $resp = $this->requestWithRetry('GET', $url);
            $ms = (int)round((microtime(true) - $start) * 1000);

            if ($resp['http_code'] < 200 || $resp['http_code'] >= 300) {
                $snippet = substr($resp['body'], 0, 200);
                call_user_func($this->log, 'ERROR', "HTTP {$resp['http_code']} on {$url} — {$snippet}");
                throw new RuntimeException("HTTP {$resp['http_code']} " . $this->httpReason($resp['http_code']) . " — {$snippet}");
            }

            $payload = json_decode($resp['body'], true);
            if (!is_array($payload) || !isset($payload['results'])) {
                throw new RuntimeException("Invalid JSON response on lessons page {$page}: " . substr($resp['body'], 0, 200));
            }

            $results = $payload['results'];
            $count = count($results);
            $msg = sprintf("Fetching page %d...   %d lessons (HTTP %d, %dms)", $page, $count, $resp['http_code'], $ms);
            call_user_func($this->log, 'INFO', $msg);
            echo $msg . PHP_EOL;

            foreach ($results as $row) {
                $all[] = $row;
            }

            if (empty($payload['next']) || $count === 0) {
                break;
            }

            $page++;
            $this->sleepMs((int)$this->cfg['sleep_ms']);
        }

        return $all;
    }

    /**
     * GET /{lang}/lessons/{id}/ — single lesson detail (v2). Returns decoded
     * dict, hoặc null nếu 404 (already deleted / không tồn tại). Throw 4xx khác /
     * 5xx-after-retry. Dùng cho delete preview (fallback khi CSV thiếu data) +
     * verify sau push.
     */
    public function getLesson($id)
    {
        $lang = $this->cfg['language'];
        $base = rtrim($this->cfg['base_url'], '/');
        $id   = (string)$id;
        $url  = "{$base}/{$lang}/lessons/{$id}/";
        $resp = $this->requestWithRetry('GET', $url);

        if ($resp['http_code'] === 404) {
            return null;
        }
        if ($resp['http_code'] < 200 || $resp['http_code'] >= 300) {
            $snippet = substr($resp['body'], 0, 300);
            call_user_func($this->log, 'ERROR', "GET lesson id={$id} HTTP {$resp['http_code']} body=" . $snippet);
            throw new RuntimeException("GET lesson id={$id} failed HTTP {$resp['http_code']} — {$snippet}");
        }
        $decoded = json_decode($resp['body'], true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * DELETE /{lang}/lessons/{id}/ — xoá lesson (v2).
     * Returns ['http_code'=>int, 'body'=>string]. 204/200 = success, 404 =
     * already gone (KHÔNG throw — giống deleteCard). 4xx khác / 5xx-after-retry → throw.
     */
    public function deleteLesson($id)
    {
        $lang = $this->cfg['language'];
        $base = rtrim($this->cfg['base_url'], '/');
        $id   = (string)$id;
        $url  = "{$base}/{$lang}/lessons/{$id}/";
        $resp = $this->requestWithRetry('DELETE', $url);

        if ($resp['http_code'] === 204 || $resp['http_code'] === 200 || $resp['http_code'] === 404) {
            return $resp;
        }
        if ($resp['http_code'] >= 400 && $resp['http_code'] < 500) {
            $snippet = substr($resp['body'], 0, 300);
            call_user_func($this->log, 'ERROR', "DELETE lesson id={$id} HTTP {$resp['http_code']} body=" . $snippet);
            throw new RuntimeException("DELETE lesson id={$id} failed HTTP {$resp['http_code']} — {$snippet}");
        }
        throw new RuntimeException("DELETE lesson id={$id} unexpected HTTP {$resp['http_code']}");
    }

    /**
     * Derive v3 API base từ config base_url (v2). search endpoint chỉ tồn tại ở v3.
     * `https://www.lingq.com/api/v2` → `https://www.lingq.com/api/v3`.
     */
    private function v3Base()
    {
        $base = rtrim($this->cfg['base_url'], '/');
        if (strpos($base, '/api/v2') !== false) {
            return str_replace('/api/v2', '/api/v3', $base);
        }
        return $base;
    }

    /**
     * Phase F helper — build hints array cho POST/PATCH payload.
     * LingQ API v2 dùng plural `hints` field = array of {text, locale}.
     * Returns [] nếu text empty → cho phép caller clear hints server-side.
     */
    public static function buildHintsArray($text, $locale)
    {
        $t = trim((string)$text);
        if ($t === '') return [];
        return [['text' => $t, 'locale' => (string)$locale]];
    }

    /**
     * Phase F helper — pick text VI từ API hints array.
     * Filter theo locale, lấy first match. Trả '' nếu không có.
     * Hint ngôn ngữ khác (en/de/fr) → ignore (CSV mono-locale).
     * `hints: null` hoặc thiếu → treat as empty (không crash).
     */
    public static function pickHintText($hints, $locale, callable $logger = null, $pkForLog = '')
    {
        if (!is_array($hints) || empty($hints)) return '';
        $matches = [];
        foreach ($hints as $h) {
            if (!is_array($h)) continue;
            $loc = isset($h['locale']) ? (string)$h['locale'] : '';
            $txt = isset($h['text']) ? (string)$h['text'] : '';
            if ($loc === (string)$locale && $txt !== '') {
                $matches[] = $txt;
            }
        }
        if (count($matches) > 1 && $logger !== null) {
            call_user_func($logger, 'WARN', "pk={$pkForLog} có " . count($matches) . " hints locale={$locale}; using first");
        }
        return !empty($matches) ? $matches[0] : '';
    }

    /**
     * Public helper — caller dùng để rate-limit giữa các request.
     */
    public function sleepMs($ms)
    {
        if ($ms <= 0) return;
        usleep((int)$ms * 1000);
    }

    /**
     * Public getter cho rate-limit config (push.php cần biết).
     */
    public function getSleepMs()
    {
        return (int)$this->cfg['sleep_ms'];
    }

    // -------------------------------------------------------------------------
    // Internal — generic HTTP với retry.
    // -------------------------------------------------------------------------

    /**
     * Generic HTTP request với retry trên 5xx + network errors.
     *
     * 2xx + 4xx → return ['http_code'=>int, 'body'=>string] (caller xử lý 4xx).
     * 5xx-after-retry → throws RuntimeException.
     * network-error-after-retry → throws RuntimeException.
     *
     * $bodyJson: pre-encoded JSON string (hoặc null cho GET/DELETE).
     */
    private function requestWithRetry($method, $url, $bodyJson = null)
    {
        $attempts   = max(1, (int)$this->cfg['retry']);
        $backoff    = [1, 3, 9, 27, 60];
        $backoff429 = isset($this->cfg['retry_429_backoff']) && is_array($this->cfg['retry_429_backoff'])
            ? $this->cfg['retry_429_backoff']
            : [5, 15, 30, 60, 120];
        $lastErr    = '';
        $method     = strtoupper($method);

        for ($i = 0; $i < $attempts; $i++) {
            $ch = curl_init();
            $headers = [
                "Authorization: Token {$this->cfg['api_key']}",
                "Accept: application/json",
            ];
            if ($bodyJson !== null) {
                $headers[] = "Content-Type: application/json";
            }

            // Capture Retry-After header nếu server trả về (chuẩn cho 429).
            $retryAfter = 0;
            $headerFn = function ($ch, $header) use (&$retryAfter) {
                if (stripos($header, 'Retry-After:') === 0) {
                    $val = trim(substr($header, strlen('Retry-After:')));
                    if (ctype_digit($val)) {
                        $retryAfter = (int)$val;
                    }
                }
                return strlen($header);
            };

            $opts = [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int)$this->cfg['timeout'],
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_USERAGENT      => "lingq-sync-deutsch/1.0",
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HEADERFUNCTION => $headerFn,
            ];
            if ($bodyJson !== null) {
                $opts[CURLOPT_POSTFIELDS] = $bodyJson;
            }
            curl_setopt_array($ch, $opts);

            $body  = curl_exec($ch);
            $errno = curl_errno($ch);
            $err   = curl_error($ch);
            $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0 || $body === false) {
                $lastErr = "cURL errno={$errno} {$err}";
                call_user_func($this->log, 'WARN', "{$method} {$url} attempt " . ($i + 1) . " — {$lastErr}");
                if ($i < $attempts - 1) {
                    $this->sleepMs(($backoff[$i] ?? end($backoff)) * 1000);
                    continue;
                }
                throw new RuntimeException("Network error after {$attempts} attempts on {$method} {$url}: {$lastErr}");
            }

            // 429 — rate-limited, retry với backoff dài (ưu tiên Retry-After header).
            if ($code === 429) {
                $waitSec = $retryAfter > 0 ? $retryAfter : ($backoff429[$i] ?? end($backoff429));
                $lastErr = "HTTP 429 Too Many Requests" . ($retryAfter > 0 ? " (Retry-After={$retryAfter}s)" : "");
                call_user_func($this->log, 'WARN', "{$method} {$url} attempt " . ($i + 1) . " — rate-limited, sleeping {$waitSec}s before retry");
                if ($i < $attempts - 1) {
                    $this->sleepMs($waitSec * 1000);
                    continue;
                }
                // Hết retry — return 429 cho caller xử lý (skip row, không throw).
                return ['http_code' => 429, 'body' => (string)$body];
            }

            // 2xx + 4xx (trừ 429): return immediately (no retry).
            if (($code >= 200 && $code < 300) || ($code >= 400 && $code < 500)) {
                return ['http_code' => $code, 'body' => (string)$body];
            }

            // 5xx — retry với backoff ngắn.
            $lastErr = "HTTP {$code}";
            $snippet = substr((string)$body, 0, 200);
            call_user_func($this->log, 'WARN', "{$method} {$url} attempt " . ($i + 1) . " — {$lastErr} {$snippet}");
            if ($i < $attempts - 1) {
                $this->sleepMs(($backoff[$i] ?? end($backoff)) * 1000);
                continue;
            }
            throw new RuntimeException("Server error after {$attempts} attempts on {$method} {$url}: {$lastErr}");
        }

        throw new RuntimeException("Unreachable retry loop exit: {$lastErr}");
    }

    private function httpReason($code)
    {
        $map = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
        ];
        return isset($map[$code]) ? $map[$code] : '';
    }
}
