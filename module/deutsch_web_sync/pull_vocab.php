<?php
/**
 * pull_vocab.php — CLI kéo từ web-add (curated=0) từ deutsch.twv.app → staging local.
 *
 * Cầu MỘT CHIỀU Web → local. GET /api/vocab/new?since=<ts> (Bearer). KHÔNG merge thẳng
 * vào "não dữ liệu": chỉ append output/drills/vocab_new_web.csv (staging). User review
 * trong Cowork rồi mới import thủ công vào data/03_unified/vocab_master.csv.
 *
 * Flags:
 *   --dry-run        GET + in plan (count). KHÔNG ghi CSV, KHÔNG update state.
 *   --since=<ISO>    override last_vocab_pull (vd --since=2026-05-01T00:00:00Z).
 *
 * Exit: 0 OK; 1 lỗi (config thiếu, HTTP 4xx, network sau retry).
 *
 * Ranh giới (cấm): KHÔNG sửa vocab_master.csv / weak_words.csv; KHÔNG gọi lingq_sync.
 */

if (!extension_loaded('curl')) {
    fwrite(STDERR, "FATAL: PHP cURL extension chưa bật. Bật extension=curl trong php.ini.\n");
    exit(1);
}

define('DWS_DIR', __DIR__);
$stateDir = DWS_DIR . '/state';
$logsDir  = DWS_DIR . '/logs';
foreach ([$stateDir, $logsDir] as $d) {
    if (!is_dir($d)) { @mkdir($d, 0777, true); }
}
$lastPullFile = $stateDir . '/last_vocab_pull.json';
$logFile      = $logsDir . '/' . date('Y-m-d') . '.log';

$logFh = @fopen($logFile, 'a');
$log = function ($level, $msg) use ($logFh) {
    $line = '[' . date('Y-m-d H:i:s') . "] $level [pull_vocab] $msg";
    if ($logFh) { fwrite($logFh, $line . "\n"); }
    fwrite(STDOUT, $line . "\n");
};

function csv_field($v)
{
    $v = (string)$v;
    if (preg_match('/[",\r\n]/', $v)) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}
function csv_row(array $cols) { return implode(',', array_map('csv_field', $cols)); }

function atomic_put($path, $content)
{
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $content) === false) {
        throw new RuntimeException("Không ghi được tmp: $tmp");
    }
    if (!@rename($tmp, $path)) {
        @unlink($path);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Không rename được tmp → $path");
        }
    }
}

function atomic_append_csv($path, $header, array $rowStrings)
{
    if (count($rowStrings) === 0) { return; }
    $dir = dirname($path);
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $content = file_exists($path) ? file_get_contents($path) : '';
    if ($content === '' || $content === false) {
        $content = $header . "\n";
    } elseif (substr($content, -1) !== "\n") {
        $content .= "\n";
    }
    $content .= implode("\n", $rowStrings) . "\n";
    atomic_put($path, $content);
}

// GET JSON với retry 1s/3s/9s trên 5xx/timeout/network. 4xx fast-fail.
function http_get_json($url, $apiKey, $cfg, $log)
{
    $attempts = max(1, (int)$cfg['retry']);
    $backoff  = [1, 3, 9];
    for ($i = 0; $i < $attempts; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int)$cfg['timeout'],
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code === 0) {
            $log('WARN', "GET network error (attempt " . ($i + 1) . "/$attempts): $err");
            if ($i < $attempts - 1) { sleep($backoff[min($i, 2)]); continue; }
            throw new RuntimeException("GET network error sau $attempts lần: $err");
        }
        if ($code >= 500) {
            $log('WARN', "GET HTTP $code (attempt " . ($i + 1) . "/$attempts)");
            if ($i < $attempts - 1) { sleep($backoff[min($i, 2)]); continue; }
            throw new RuntimeException("GET HTTP $code sau $attempts lần");
        }
        if ($code >= 400) {
            throw new RuntimeException("GET HTTP $code: " . substr((string)$body, 0, 300));
        }
        $data = json_decode($body, true);
        if (!is_array($data)) { throw new RuntimeException("GET trả JSON không hợp lệ"); }
        return $data;
    }
    throw new RuntimeException("GET thất bại"); // unreachable
}

// ── Parse args ──
$dryRun = false;
$sinceOverride = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (strpos($arg, '--since=') === 0) {
        $sinceOverride = substr($arg, strlen('--since='));
    } else {
        fwrite(STDERR, "Tham số lạ: $arg\n");
        fwrite(STDERR, "Dùng: php pull_vocab.php [--dry-run] [--since=YYYY-MM-DDTHH:MM:SSZ]\n");
        exit(1);
    }
}

try {
    // 1. Config
    $cfgFile = DWS_DIR . '/config.php';
    if (!file_exists($cfgFile)) {
        throw new RuntimeException("Thiếu config.php. Copy config.example.php → config.php và dán api_key + vocab_new_output.");
    }
    $cfg = require $cfgFile;
    foreach (['api_base', 'api_key', 'timeout', 'retry', 'vocab_new_output'] as $k) {
        if (!isset($cfg[$k])) { throw new RuntimeException("config.php thiếu key: $k"); }
    }
    if ($cfg['api_key'] === '' || $cfg['api_key'] === 'PASTE_SAME_BEARER_TOKEN_AS_SERVER_CONFIG') {
        throw new RuntimeException("config.php: api_key chưa được set (vẫn là placeholder).");
    }
    $apiBase = rtrim($cfg['api_base'], '/');
    $outPath = $cfg['vocab_new_output'];

    // 2. last_vocab_pull
    $since = '1970-01-01T00:00:00Z';
    if ($sinceOverride !== null && $sinceOverride !== '') {
        $since = $sinceOverride;
        $log('INFO', "since override qua --since=$since");
    } elseif (file_exists($lastPullFile)) {
        $st = json_decode((string)file_get_contents($lastPullFile), true);
        if (is_array($st) && !empty($st['last_vocab_pull'])) { $since = $st['last_vocab_pull']; }
    }
    $log('INFO', ($dryRun ? '[DRY-RUN] ' : '') . "Pull web-add vocab since=$since");

    // 3. GET
    $url = $apiBase . '/api/vocab/new?since=' . rawurlencode($since) . '&limit=1000';
    $resp = http_get_json($url, $cfg['api_key'], $cfg, $log);
    $rows = isset($resp['vocab']) && is_array($resp['vocab']) ? $resp['vocab'] : [];

    // 4. count=0
    if (count($rows) === 0) {
        $log('INFO', "Không có từ mới. Exit 0.");
        if ($logFh) { fclose($logFh); }
        exit(0);
    }

    // 5. Chuẩn bị rows CSV + tính max(created_at).
    $csvRows = [];
    $maxCreated = $since;
    foreach ($rows as $r) {
        $created = isset($r['created_at']) ? (string)$r['created_at'] : '';
        $csvRows[] = csv_row([
            isset($r['id']) ? (string)$r['id'] : '',
            isset($r['wort']) ? (string)$r['wort'] : '',
            isset($r['wort_key']) ? (string)$r['wort_key'] : '',
            isset($r['bedeutung']) ? (string)$r['bedeutung'] : '',
            isset($r['wortart']) ? (string)$r['wortart'] : '',
            isset($r['artikel']) ? (string)$r['artikel'] : '',
            isset($r['source']) ? (string)$r['source'] : '',
            $created,
        ]);
        if ($created !== '' && $created > $maxCreated) { $maxCreated = $created; }
    }

    $log('INFO', ($dryRun ? '[DRY-RUN] ' : '') . sprintf("Server trả %d từ web-add mới.", count($csvRows)));
    foreach (array_slice($rows, 0, 5) as $i => $r) {
        $log('INFO', ($dryRun ? '[DRY-RUN] ' : '') . "  - " . ($r['wort'] ?? '?') . " (" . ($r['source'] ?? '') . ") → " . ($r['bedeutung'] ?? ''));
    }

    if ($dryRun) {
        $log('INFO', "[DRY-RUN] sẽ append " . count($csvRows) . " row → " . $outPath);
        $log('INFO', "[DRY-RUN] last_vocab_pull → $maxCreated. KHÔNG ghi file/state. Exit 0.");
        if ($logFh) { fclose($logFh); }
        exit(0);
    }

    // 6. Append staging CSV (TRƯỚC khi update state — crash-safe).
    atomic_append_csv($outPath, 'web_id,wort,wort_key,bedeutung,wortart,artikel,source_lesson,created_at', $csvRows);
    $log('INFO', "Append " . count($csvRows) . " row → " . $outPath);

    // 7. state
    atomic_put($lastPullFile, json_encode(['last_vocab_pull' => $maxCreated], JSON_UNESCAPED_SLASHES) . "\n");
    $log('INFO', "last_vocab_pull → $maxCreated. DONE.");

    if ($logFh) { fclose($logFh); }
    exit(0);

} catch (Exception $e) {
    $log('ERROR', $e->getMessage());
    $log('ERROR', "Dừng — KHÔNG ghi staging, KHÔNG update state (nếu lỗi trước bước ghi).");
    if ($logFh) { fclose($logFh); }
    exit(1);
}
