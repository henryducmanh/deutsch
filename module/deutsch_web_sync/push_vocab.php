<?php
/**
 * push_vocab.php — CLI đẩy vocab từ data/03_unified/vocab_master.csv → deutsch.twv.app.
 *
 * Cầu MỘT CHIỀU local → Web. POST /api/vocab/bulk (Bearer). Server upsert theo wort_key
 * (UNIQUE) → idempotent: chạy lại không tạo trùng. curated=1 cho tất cả row từ CSV.
 *
 * vocab_master.csv là READ-ONLY ở đây (chỉ đọc, KHÔNG ghi).
 *
 * Flags:
 *   --dry-run     parse CSV + in plan "X rows sẽ upsert". KHÔNG POST, KHÔNG ghi state.
 *   --limit=N     chỉ xử lý N row đầu (test nhanh).
 *   --chunk=N     số row/request (default 100, server cap 100).
 *
 * Exit: 0 OK; 1 lỗi (config thiếu, CSV thiếu, HTTP 4xx, network sau retry).
 *
 * Mapping CSV → payload (xem §8 spec):
 *   id        → vocab_id
 *   wort      → wort (server tự lower → wort_key)
 *   wortart   → wortart
 *   formen    → parse 'der/die/das' đầu chuỗi → artikel
 *   bedeutung → bedeutung
 *   thema     → thema
 *   level     → level (int 1-4)
 *   tags      → tags  (+ extract A1/A2/B1/B2/C1/C2 → niveau, default B1)
 *   (const)   → curated = 1
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
$lastPushFile = $stateDir . '/last_push.json';
$logFile      = $logsDir . '/' . date('Y-m-d') . '.log';

$logFh = @fopen($logFile, 'a');
$log = function ($level, $msg) use ($logFh) {
    $line = '[' . date('Y-m-d H:i:s') . "] $level [push_vocab] $msg";
    if ($logFh) { fwrite($logFh, $line . "\n"); }
    fwrite(STDOUT, $line . "\n");
};

// Ghi atomic: tmp → rename (Windows fallback unlink).
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

// Parse artikel từ 'formen': 'die Entwicklung, -en' → 'die'. Verb/Adj → '' (NULL).
function parse_artikel($formen)
{
    if (preg_match('/^\s*(der|die|das)\b/iu', (string)$formen, $m)) {
        return strtolower($m[1]);
    }
    return null;
}

// Extract niveau từ tags: 'B1;DTZ;Technologie' → 'B1'. Default 'B1'.
function parse_niveau($tags)
{
    if (preg_match('/\b([ABC][12])\b/u', (string)$tags, $m)) {
        return strtoupper($m[1]);
    }
    return 'B1';
}

// POST JSON với retry 1s/3s/9s trên 5xx/timeout/network. 4xx fast-fail.
function http_post_json($url, $apiKey, $payload, $cfg, $log)
{
    $attempts = max(1, (int)$cfg['retry']);
    $backoff  = [1, 3, 9];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    for ($i = 0; $i < $attempts; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int)$cfg['timeout'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code === 0) {
            $log('WARN', "POST network error (attempt " . ($i + 1) . "/$attempts): $err");
            if ($i < $attempts - 1) { sleep($backoff[min($i, 2)]); continue; }
            throw new RuntimeException("POST network error sau $attempts lần: $err");
        }
        if ($code >= 500) {
            $log('WARN', "POST HTTP $code (attempt " . ($i + 1) . "/$attempts)");
            if ($i < $attempts - 1) { sleep($backoff[min($i, 2)]); continue; }
            throw new RuntimeException("POST HTTP $code sau $attempts lần");
        }
        if ($code >= 400) {
            throw new RuntimeException("POST HTTP $code: " . substr((string)$body, 0, 300));
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
    throw new RuntimeException("POST thất bại"); // unreachable
}

// ── Parse args ──
$dryRun = false;
$limit  = null;
$chunkSize = 100;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (strpos($arg, '--limit=') === 0) {
        $limit = max(1, (int)substr($arg, strlen('--limit=')));
    } elseif (strpos($arg, '--chunk=') === 0) {
        $chunkSize = max(1, min(100, (int)substr($arg, strlen('--chunk='))));
    } else {
        fwrite(STDERR, "Tham số lạ: $arg\n");
        fwrite(STDERR, "Dùng: php push_vocab.php [--dry-run] [--limit=N] [--chunk=N]\n");
        exit(1);
    }
}

try {
    // 1. Config
    $cfgFile = DWS_DIR . '/config.php';
    if (!file_exists($cfgFile)) {
        throw new RuntimeException("Thiếu config.php. Copy config.example.php → config.php và dán api_key + vocab_csv.");
    }
    $cfg = require $cfgFile;
    foreach (['api_base', 'api_key', 'timeout', 'retry', 'vocab_csv'] as $k) {
        if (!isset($cfg[$k])) { throw new RuntimeException("config.php thiếu key: $k"); }
    }
    if ($cfg['api_key'] === '' || $cfg['api_key'] === 'PASTE_SAME_BEARER_TOKEN_AS_SERVER_CONFIG') {
        throw new RuntimeException("config.php: api_key chưa được set (vẫn là placeholder).");
    }
    $apiBase = rtrim($cfg['api_base'], '/');
    $csvPath = $cfg['vocab_csv'];
    if (!file_exists($csvPath)) {
        throw new RuntimeException("Không tìm thấy vocab_csv: $csvPath");
    }

    // 2. Parse CSV (fgetcsv xử lý field quote/đa-dòng). Header → map cột theo tên.
    $fh = fopen($csvPath, 'r');
    if ($fh === false) { throw new RuntimeException("Không mở được CSV: $csvPath"); }
    $header = fgetcsv($fh);
    if ($header === false) { throw new RuntimeException("CSV rỗng: $csvPath"); }
    $col = array_flip($header); // tên cột → index
    foreach (['id', 'wort', 'wortart', 'formen', 'bedeutung', 'thema', 'level', 'tags'] as $need) {
        if (!isset($col[$need])) { throw new RuntimeException("CSV thiếu cột: $need"); }
    }

    $payloads = [];
    $seenKey  = [];   // dedup theo wort_key TRONG CSV (server cũng dedup, nhưng gọn request)
    $skipped  = 0;
    $rowNum   = 0;
    while (($row = fgetcsv($fh)) !== false) {
        $rowNum++;
        $get = function ($name) use ($row, $col) {
            return isset($col[$name], $row[$col[$name]]) ? trim((string)$row[$col[$name]]) : '';
        };
        $wort = $get('wort');
        if ($wort === '') { $skipped++; continue; }                  // row rỗng/comment
        $wkey = mb_strtolower($wort, 'UTF-8');
        if (isset($seenKey[$wkey])) { $skipped++; continue; }        // trùng trong CSV → bỏ
        $seenKey[$wkey] = true;

        $levelRaw = $get('level');
        $level = is_numeric($levelRaw) ? max(1, min(4, (int)$levelRaw)) : 1;

        $payloads[] = [
            'vocab_id'  => $get('id') !== '' ? $get('id') : null,
            'wort'      => $wort,
            'wortart'   => $get('wortart') !== '' ? $get('wortart') : null,
            'artikel'   => parse_artikel($get('formen')),
            'bedeutung' => $get('bedeutung') !== '' ? $get('bedeutung') : null,
            'niveau'    => parse_niveau($get('tags')),
            'level'     => $level,
            'thema'     => $get('thema') !== '' ? $get('thema') : null,
            'tags'      => $get('tags') !== '' ? $get('tags') : null,
        ];

        if ($limit !== null && count($payloads) >= $limit) { break; }
    }
    fclose($fh);

    $total = count($payloads);
    $log('INFO', ($dryRun ? '[DRY-RUN] ' : '') . sprintf(
        "Parsed %d row CSV → %d payload duy nhất (wort_key) | skip %d (rỗng/trùng) | limit=%s | chunk=%d",
        $rowNum, $total, $skipped, $limit === null ? 'all' : $limit, $chunkSize
    ));

    if ($total === 0) {
        $log('INFO', "Không có row nào để đẩy. Exit 0.");
        if ($logFh) { fclose($logFh); }
        exit(0);
    }

    // 3. Chunk
    $chunks = array_chunk($payloads, $chunkSize);
    $log('INFO', ($dryRun ? '[DRY-RUN] ' : '') . "$total rows sẽ upsert qua " . count($chunks) . " request (POST /api/vocab/bulk).");

    if ($dryRun) {
        // In mẫu 3 payload đầu để soi mapping.
        foreach (array_slice($payloads, 0, 3) as $i => $p) {
            $log('INFO', "[DRY-RUN] mẫu #" . ($i + 1) . ": " . json_encode($p, JSON_UNESCAPED_UNICODE));
        }
        $log('INFO', "[DRY-RUN] KHÔNG POST, KHÔNG ghi state. Exit 0.");
        if ($logFh) { fclose($logFh); }
        exit(0);
    }

    // 4. POST từng chunk
    $upserted = 0;
    $skippedSrv = 0;
    $url = $apiBase . '/api/vocab/bulk';
    foreach ($chunks as $idx => $chunk) {
        $resp = http_post_json($url, $cfg['api_key'], ['rows' => array_values($chunk)], $cfg, $log);
        $up = isset($resp['upserted']) ? (int)$resp['upserted'] : 0;
        $sk = isset($resp['skipped']) ? (int)$resp['skipped'] : 0;
        $upserted += $up;
        $skippedSrv += $sk;
        $log('INFO', sprintf("Chunk %d/%d: upserted=%d skipped=%d", $idx + 1, count($chunks), $up, $sk));
        usleep(100000); // 0.1s/chunk (spec §6)
    }

    // 5. State
    $state = [
        'last_push'      => gmdate('Y-m-d\TH:i:s\Z'),
        'rows_parsed'    => $total,
        'upserted'       => $upserted,
        'skipped_server' => $skippedSrv,
    ];
    atomic_put($lastPushFile, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");

    $log('INFO', sprintf("DONE: upserted=%d skipped_server=%d / %d rows. last_push ghi state.", $upserted, $skippedSrv, $total));
    if ($logFh) { fclose($logFh); }
    exit(0);

} catch (Exception $e) {
    $log('ERROR', $e->getMessage());
    if ($logFh) { fclose($logFh); }
    exit(1);
}
