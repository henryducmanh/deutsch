<?php
/**
 * pull_events.php — CLI pull event học tập từ deutsch.twv.app → stage local.
 *
 * Cầu MỘT CHIỀU Web → local. KHÔNG merge vào "não dữ liệu" (vocab_master /
 * weak_words) — chỉ:
 *   - auto-append output/drills/horen_progress.csv  (progress cơ học)
 *   - stage word_mark → staging/pending_words.csv   (chờ Cowork curate)
 *
 * Flags:
 *   --dry-run        chỉ bước 1–5 (in plan + count). KHÔNG ghi, KHÔNG ack, KHÔNG update state.
 *   --since=<ISO>    override last_sync (vd --since=2026-05-29T00:00:00Z).
 *
 * Exit code: 0 OK; 1 lỗi (config thiếu, HTTP 4xx, network sau retry).
 *
 * Ranh giới (cấm): KHÔNG sửa vocab_master.csv / weak_words.csv; KHÔNG gọi lingq_sync;
 * KHÔNG ack khi staging/progress chưa ghi xong; KHÔNG bịa field khi payload thiếu (WARN+skip).
 */

if (!extension_loaded('curl')) {
    fwrite(STDERR, "FATAL: PHP cURL extension chưa bật. Bật extension=curl trong php.ini.\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// Paths + dirs
// ─────────────────────────────────────────────────────────────────────────────
define('DWS_DIR', __DIR__);
$stateDir   = DWS_DIR . '/state';
$stagingDir = DWS_DIR . '/staging';
$logsDir    = DWS_DIR . '/logs';
foreach ([$stateDir, $stagingDir, $logsDir] as $d) {
    if (!is_dir($d)) { @mkdir($d, 0777, true); }
}
$lastSyncFile   = $stateDir . '/last_sync.json';
$processedFile  = DWS_DIR . '/processed_events.log';
$logFile        = $logsDir . '/' . date('Y-m-d') . '.log';

// ─────────────────────────────────────────────────────────────────────────────
// Logger
// ─────────────────────────────────────────────────────────────────────────────
$logFh = @fopen($logFile, 'a');
$log = function ($level, $msg) use ($logFh) {
    $line = '[' . date('Y-m-d H:i:s') . "] $level $msg";
    if ($logFh) { fwrite($logFh, $line . "\n"); }
    fwrite(STDOUT, $line . "\n");
};

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────
function csv_field($v)
{
    $v = (string)$v;
    if (preg_match('/[",\r\n]/', $v)) {
        return '"' . str_replace('"', '""', $v) . '"';
    }
    return $v;
}
function csv_row(array $cols)
{
    return implode(',', array_map('csv_field', $cols));
}

// Ghi atomic: tmp → rename (Windows rename qua file tồn tại có thể fail → unlink fallback).
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

// Append-only CSV atomic: đọc nội dung cũ + nối row mới + rewrite atomic.
function atomic_append_csv($path, $header, array $rowStrings)
{
    if (count($rowStrings) === 0) { return; }
    $content = file_exists($path) ? file_get_contents($path) : '';
    if ($content === '' || $content === false) {
        $content = $header . "\n";
    } elseif (substr($content, -1) !== "\n") {
        $content .= "\n";
    }
    $content .= implode("\n", $rowStrings) . "\n";
    atomic_put($path, $content);
}

// GET JSON với retry 1s/3s/9s chỉ trên 5xx/timeout/network. 4xx fast-fail.
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
        if (!is_array($data)) {
            throw new RuntimeException("GET trả JSON không hợp lệ");
        }
        return $data;
    }
    throw new RuntimeException("GET thất bại"); // unreachable
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

// ─────────────────────────────────────────────────────────────────────────────
// Parse args
// ─────────────────────────────────────────────────────────────────────────────
$dryRun = false;
$sinceOverride = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (strpos($arg, '--since=') === 0) {
        $sinceOverride = substr($arg, strlen('--since='));
    } else {
        fwrite(STDERR, "Tham số lạ: $arg\n");
        fwrite(STDERR, "Dùng: php pull_events.php [--dry-run] [--since=YYYY-MM-DDTHH:MM:SSZ]\n");
        exit(1);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Main
// ─────────────────────────────────────────────────────────────────────────────
try {
    // 1. Config
    $cfgFile = DWS_DIR . '/config.php';
    if (!file_exists($cfgFile)) {
        throw new RuntimeException("Thiếu config.php. Copy config.example.php → config.php và dán api_key.");
    }
    $cfg = require $cfgFile;
    foreach (['api_base', 'api_key', 'timeout', 'retry', 'horen_progress', 'pending_words'] as $k) {
        if (!isset($cfg[$k])) { throw new RuntimeException("config.php thiếu key: $k"); }
    }
    if ($cfg['api_key'] === '' || $cfg['api_key'] === 'PASTE_SAME_BEARER_TOKEN_AS_SERVER_CONFIG') {
        throw new RuntimeException("config.php: api_key chưa được set (vẫn là placeholder).");
    }
    $apiBase = rtrim($cfg['api_base'], '/');

    // 1b. last_sync
    $lastSync = '1970-01-01T00:00:00Z';
    if ($sinceOverride !== null && $sinceOverride !== '') {
        $lastSync = $sinceOverride;
        $log('INFO', "last_sync override qua --since=$lastSync");
    } elseif (file_exists($lastSyncFile)) {
        $st = json_decode((string)file_get_contents($lastSyncFile), true);
        if (is_array($st) && !empty($st['last_sync'])) {
            $lastSync = $st['last_sync'];
        }
    }
    $log('INFO', ($dryRun ? '[DRY-RUN] ' : '') . "Pull events since=$lastSync");

    // 2. GET events
    $url = $apiBase . '/api/events?since=' . rawurlencode($lastSync) . '&limit=2000';
    $resp = http_get_json($url, $cfg['api_key'], $cfg, $log);
    $events = isset($resp['events']) && is_array($resp['events']) ? $resp['events'] : [];

    // 3. Lọc đã xử lý (processed_events.log)
    $processed = [];
    if (file_exists($processedFile)) {
        foreach (file($processedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $id) {
            $processed[trim($id)] = true;
        }
    }
    $newEvents = [];
    foreach ($events as $ev) {
        $eid = isset($ev['event_id']) ? (string)$ev['event_id'] : '';
        if ($eid === '' || isset($processed[$eid])) { continue; }
        $newEvents[] = $ev;
    }

    // 5. Phân loại (chuẩn bị rows; chưa ghi)
    $horenRows   = [];
    $wordRows    = [];
    $countHoren  = 0;
    $countWord   = 0;
    $countOpen   = 0;
    $countSkip   = 0;
    $okEventIds  = [];   // event_id xử lý thành công → ack + processed log
    $maxCreated  = $lastSync;

    foreach ($newEvents as $ev) {
        $eid     = (string)$ev['event_id'];
        $type    = isset($ev['type']) ? $ev['type'] : '';
        $lesson  = isset($ev['lesson_id']) ? (string)$ev['lesson_id'] : '';
        $created = isset($ev['created_at']) ? (string)$ev['created_at'] : '';
        $payload = isset($ev['payload']) && is_array($ev['payload']) ? $ev['payload'] : [];

        if ($created !== '' && $created > $maxCreated) { $maxCreated = $created; }

        if ($type === 'horen_complete') {
            $correct = $payload['correct'] ?? null;
            $total   = $payload['total'] ?? null;
            if ($correct === null || $total === null || !is_numeric($total) || (int)$total <= 0) {
                $log('WARN', "horen_complete event $eid thiếu correct/total hợp lệ → skip (KHÔNG ack).");
                $countSkip++;
                continue;
            }
            $ngay  = $created !== '' ? substr($created, 0, 10) : date('Y-m-d');
            $pct   = round((int)$correct / (int)$total * 100) . '%';
            $notes = isset($payload['notes']) ? (string)$payload['notes'] : '';
            $horenRows[] = csv_row([$lesson, $ngay, (int)$correct, (int)$total, $pct, $notes]);
            $okEventIds[] = $eid;
            $countHoren++;
        } elseif ($type === 'word_mark') {
            $word = isset($payload['word']) ? (string)$payload['word'] : '';
            if ($word === '') {
                $log('WARN', "word_mark event $eid thiếu payload.word → skip (KHÔNG ack).");
                $countSkip++;
                continue;
            }
            $wstatus = isset($payload['word_status']) ? (string)$payload['word_status'] : '';
            $context = isset($payload['context']) ? (string)$payload['context'] : '';
            $wordRows[] = csv_row([$eid, $word, $wstatus, $lesson, $context, $created, '0']);
            $okEventIds[] = $eid;
            $countWord++;
        } elseif ($type === 'lesson_open') {
            // Chỉ nằm trong dump JSON, không xử lý thêm. Vẫn ack (đã pull thành công).
            $okEventIds[] = $eid;
            $countOpen++;
        } else {
            $log('WARN', "event $eid type lạ '$type' → chỉ dump JSON, ack.");
            $okEventIds[] = $eid;
        }
    }

    // 4./5. In plan
    $log('INFO', sprintf(
        "Server trả %d event, %d mới sau dedup (horen_complete: %d, word_mark: %d, lesson_open: %d, skip: %d)",
        count($events), count($newEvents), $countHoren, $countWord, $countOpen, $countSkip
    ));

    if ($dryRun) {
        $log('INFO', "[DRY-RUN] sẽ append horen_progress += $countHoren rows | pending_words += $countWord rows");
        $log('INFO', "[DRY-RUN] sẽ ack " . count($okEventIds) . " event | last_sync → $maxCreated");
        $log('INFO', "[DRY-RUN] KHÔNG ghi file, KHÔNG ack, KHÔNG update state. Exit 0.");
        if ($logFh) { fclose($logFh); }
        exit(0);
    }

    if (count($newEvents) === 0) {
        $log('INFO', "Pulled: 0 events. Không có gì để xử lý.");
        // Vẫn cập nhật last_sync nếu maxCreated tiến lên (giữ con trỏ gọn).
        if ($maxCreated !== $lastSync) {
            atomic_put($lastSyncFile, json_encode(['last_sync' => $maxCreated], JSON_UNESCAPED_SLASHES) . "\n");
        }
        if ($logFh) { fclose($logFh); }
        exit(0);
    }

    // 4. Dump raw audit (tất cả event MỚI, kể cả skip — để soi sau).
    $ts = date('Ymd-His');
    $dumpFile = $stagingDir . '/events_' . $ts . '.json';
    atomic_put($dumpFile, json_encode($newEvents, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    $log('INFO', "Dump audit → " . basename($dumpFile));

    // 6.→ ghi staging + progress TRƯỚC khi ack (crash-safe).
    atomic_append_csv($cfg['horen_progress'], 'bai,ngay,dung,tong,pct,ghi_chu', $horenRows);
    atomic_append_csv(
        $cfg['pending_words'],
        'event_id,word,word_status,lesson_id,context,clicked_at,curated',
        $wordRows
    );

    // 6. processed_events.log (chỉ event xử lý thành công).
    if (count($okEventIds) > 0) {
        $existing = file_exists($processedFile) ? file_get_contents($processedFile) : '';
        if ($existing !== '' && substr($existing, -1) !== "\n") { $existing .= "\n"; }
        $existing .= implode("\n", $okEventIds) . "\n";
        atomic_put($processedFile, $existing);
    }

    // 7. ACK (chỉ SAU khi staging+progress+processed đã ghi).
    $acked = 0;
    if (count($okEventIds) > 0) {
        $ackResp = http_post_json(
            $apiBase . '/api/events/ack',
            $cfg['api_key'],
            ['event_ids' => array_values($okEventIds)],
            $cfg,
            $log
        );
        $acked = isset($ackResp['acked']) ? (int)$ackResp['acked'] : 0;
    }

    // 8. last_sync → max created_at vừa pull (hoặc now nếu rỗng).
    $newLastSync = $maxCreated !== '' ? $maxCreated : gmdate('Y-m-d\TH:i:s\Z');
    atomic_put($lastSyncFile, json_encode(['last_sync' => $newLastSync], JSON_UNESCAPED_SLASHES) . "\n");

    // 9. Tổng kết
    $log('INFO', sprintf(
        "Pulled: %d events (horen_complete: %d, word_mark: %d, lesson_open: %d)",
        count($newEvents), $countHoren, $countWord, $countOpen
    ));
    $log('INFO', "horen_progress += $countHoren rows | pending_words += $countWord rows");
    $log('INFO', "Acked: $acked | last_sync → $newLastSync");

    if ($logFh) { fclose($logFh); }
    exit(0);

} catch (Exception $e) {
    $log('ERROR', $e->getMessage());
    $log('ERROR', "Dừng — KHÔNG ghi staging/progress, KHÔNG ack (nếu lỗi xảy ra trước bước ghi).");
    if ($logFh) { fclose($logFh); }
    exit(1);
}
