<?php
/**
 * LingQ Lessons Delete (Phase K1) — xoá 1+ lesson theo id.
 *
 * Usage (default = DRY-RUN):
 *   C:\php\php74\php.exe module\lingq_sync\lessons_delete.php <id1> <id2> ...
 *   C:\php\php74\php.exe module\lingq_sync\lessons_delete.php <id1> --apply
 *   C:\php\php74\php.exe module\lingq_sync\lessons_delete.php <id1> --apply --no-resync
 *
 * Flags:
 *   --apply       : thật sự gọi DELETE API. Mặc định DRY-RUN (chỉ in preview).
 *   --dry-run     : explicit dry-run (= default).
 *   --no-resync   : sau --apply KHÔNG tự chạy lessons_sync.php (mặc định re-sync).
 *
 * Behaviour:
 *   - Đọc data/lingq_lessons.csv để show preview (title, audio?, unknown_count, course).
 *   - id KHÔNG có trong CSV → vẫn cho xoá, thử getLesson() để lấy title; cảnh báo.
 *   - --apply: snapshot backup CSV trước → DELETE từng id (404 = already gone) →
 *     re-sync CSV (trừ khi --no-resync) để CSV không còn id đã xoá.
 *
 * Exit codes:
 *   0 — OK (kể cả có id fail nhưng tiếp tục)
 *   1 — fatal: config/IO/bad args
 */

declare(strict_types=0);

require_once __DIR__ . '/lingq_client.php';

$moduleDir   = __DIR__;
$repoRoot    = realpath(__DIR__ . '/../..');
$csvPath     = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'lingq_lessons.csv';
$logsDir     = $moduleDir . DIRECTORY_SEPARATOR . 'logs';
$logFile     = $logsDir . DIRECTORY_SEPARATOR . 'lessons_' . date('Y-m-d') . '.log';
$configPath  = $moduleDir . DIRECTORY_SEPARATOR . 'config.php';
$syncScript  = $moduleDir . DIRECTORY_SEPARATOR . 'lessons_sync.php';

$apply  = false;
$resync = true;
$ids    = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply')     { $apply = true; continue; }
    if ($arg === '--dry-run')   { $apply = false; continue; }
    if ($arg === '--no-resync') { $resync = false; continue; }
    if ($arg === '-h' || $arg === '--help') {
        echo "Usage: php lessons_delete.php <id1> [<id2> ...] [--apply] [--no-resync]\n";
        exit(0);
    }
    if (preg_match('/^\d+$/', $arg)) { $ids[] = $arg; continue; }
    fwrite(STDERR, "Unknown arg (không phải lesson_id số hoặc flag hợp lệ): {$arg}\n");
    exit(1);
}

if (empty($ids)) {
    fwrite(STDERR, "ERROR: cần ít nhất 1 lesson_id. Vd: php lessons_delete.php 37229549 --apply\n");
    exit(1);
}
$ids = array_values(array_unique($ids));

if (!is_dir($logsDir)) @mkdir($logsDir, 0775, true);

$logger = function ($level, $msg) use ($logFile) {
    $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $msg);
    @file_put_contents($logFile, $line, FILE_APPEND);
};

$startedAt = microtime(true);
$modeLabel = $apply ? 'LIVE --apply' : 'DRY-RUN default';
echo "[LingQ Lessons Delete] " . date('Y-m-d H:i:s') . " ({$modeLabel})" . PHP_EOL;
$logger('INFO', "delete start apply=" . ($apply ? 1 : 0) . " ids=" . implode(',', $ids));

if (!file_exists($configPath)) {
    fwrite(STDERR, "ERROR: config.php không tồn tại.\n");
    $logger('ERROR', 'config.php not found');
    exit(1);
}
try {
    $cfg = require $configPath;
    if (!is_array($cfg)) throw new RuntimeException('config phải return array.');
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR loading config: " . $e->getMessage() . "\n");
    $logger('ERROR', 'config load: ' . $e->getMessage());
    exit(1);
}

// Load CSV preview map.
$csvRows = lessons_del_load_csv($csvPath);
echo "Read CSV: " . lessons_del_relative_path($csvPath, $repoRoot) . " (" . count($csvRows) . " rows)" . PHP_EOL . PHP_EOL;

// Build client (cần cho fallback getLesson trong preview + DELETE trong apply).
try {
    $client = new LingqClient($cfg, $logger);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    $logger('ERROR', $e->getMessage());
    exit(1);
}

// -----------------------------------------------------------------------------
// Preview.
// -----------------------------------------------------------------------------

echo "Sẽ xoá " . count($ids) . " lesson:" . PHP_EOL;
$notInCsv = 0;
foreach ($ids as $id) {
    if (isset($csvRows[$id])) {
        $r = $csvRows[$id];
        $hasAudio = ($r['audio_url'] !== '') ? 'yes' : 'no';
        printf(
            "  id=%-10s | %-50s | audio=%-3s | unknown=%-4s | course=%s%s\n",
            $id,
            lessons_del_trunc($r['title'], 50),
            $hasAudio,
            $r['unknown_count'] !== '' ? $r['unknown_count'] : '?',
            $r['course_id'] !== '' ? $r['course_id'] : '?',
            $r['source_local'] !== '' ? "  src=" . $r['source_local'] : ''
        );
    } else {
        $notInCsv++;
        // Fallback: thử GET để lấy title (chỉ khi cần — vài id).
        $title = '(không có trong CSV)';
        $hasAudio = '?';
        try {
            $lesson = $client->getLesson($id);
            if ($lesson === null) {
                $title = '(404 — không tồn tại / đã xoá)';
            } else {
                $title = isset($lesson['title']) ? (string)$lesson['title'] : '(no title)';
                $hasAudio = (!empty($lesson['audio']) || !empty($lesson['audioUrl'])) ? 'yes' : 'no';
            }
        } catch (Throwable $e) {
            $logger('WARN', "preview getLesson id={$id} fail: " . $e->getMessage());
        }
        printf("  id=%-10s | %-50s | audio=%-3s | (NOT in local CSV)\n", $id, lessons_del_trunc($title, 50), $hasAudio);
    }
}
echo PHP_EOL;
printf("Tổng: %d lesson sẽ DELETE", count($ids));
if ($notInCsv > 0) printf(" (%d không có trong CSV local)", $notInCsv);
echo PHP_EOL;

if (!$apply) {
    echo PHP_EOL . "⚠️  DRY-RUN — chưa gọi API. Thêm --apply để thực sự xoá." . PHP_EOL;
    echo "Done. Exit 0." . PHP_EOL;
    $logger('INFO', "dry-run preview done for " . count($ids) . " ids");
    exit(0);
}

// -----------------------------------------------------------------------------
// Apply: backup → DELETE → re-sync.
// -----------------------------------------------------------------------------

echo PHP_EOL . "⚠️  --apply: bắt đầu DELETE thật." . PHP_EOL;

// Snapshot backup CSV (nếu có).
if (file_exists($csvPath)) {
    $ts = date('Y-m-d_His');
    $backupPath = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . "lingq_lessons_backup_{$ts}.csv";
    if (!@copy($csvPath, $backupPath)) {
        fwrite(STDERR, "ERROR: không backup được " . lessons_del_relative_path($csvPath, $repoRoot) . "\n");
        $logger('ERROR', "backup failed → {$backupPath}");
        exit(1);
    }
    echo "Backup: " . lessons_del_relative_path($backupPath, $repoRoot) . " (" . count($csvRows) . " rows)" . PHP_EOL;
    $logger('INFO', "backup CSV → " . lessons_del_relative_path($backupPath, $repoRoot));
}

echo PHP_EOL;
$sleepMs = (int)$cfg['sleep_ms'];
$ok = $fail = 0;
$failList = [];

foreach ($ids as $id) {
    $start = microtime(true);
    try {
        $resp = $client->deleteLesson($id);
        $ms = (int)round((microtime(true) - $start) * 1000);
        if ($resp['http_code'] === 404) {
            $logger('WARN', "DELETE lesson id={$id} HTTP 404 (already gone) — skip");
            echo "  DELETE id={$id} ... HTTP 404 (already gone, {$ms}ms)" . PHP_EOL;
            $ok++;
        } else {
            $logger('INFO', "DELETE lesson id={$id} HTTP {$resp['http_code']} {$ms}ms");
            echo "  DELETE id={$id} ... HTTP {$resp['http_code']} OK ({$ms}ms)" . PHP_EOL;
            $ok++;
        }
    } catch (Throwable $e) {
        $fail++;
        $failList[] = $id;
        $logger('ERROR', "DELETE lesson id={$id} — " . $e->getMessage());
        echo "  DELETE id={$id} ... FAIL " . $e->getMessage() . PHP_EOL;
    }
    $client->sleepMs($sleepMs);
}

echo PHP_EOL . "DELETE: {$ok} OK / {$fail} fail" . ($fail > 0 ? " (" . implode(',', $failList) . ")" : "") . PHP_EOL;
$logger('INFO', "delete result ok={$ok} fail={$fail}");

// Re-sync CSV để loại id đã xoá.
if ($resync) {
    echo PHP_EOL . "Re-sync CSV..." . PHP_EOL;
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($syncScript);
    $rc = 0;
    passthru($cmd, $rc);
    $logger('INFO', "re-sync lessons_sync.php exit={$rc}");
    if ($rc !== 0) {
        fwrite(STDERR, "WARN: lessons_sync.php exit {$rc} — CSV có thể stale.\n");
    }
} else {
    echo PHP_EOL . "(--no-resync: bỏ qua re-sync — chạy lessons_sync.php thủ công để cập nhật CSV)" . PHP_EOL;
}

$elapsed = number_format(microtime(true) - $startedAt, 1);
echo PHP_EOL . "Done in {$elapsed}s. Exit 0." . PHP_EOL;
echo "Log: " . lessons_del_relative_path($logFile, $repoRoot) . PHP_EOL;

exit(0);

// =============================================================================
// Helpers.
// =============================================================================

function lessons_del_columns()
{
    return [
        'lesson_id', 'course_id', 'title', 'language', 'audio_url',
        'words_count', 'unknown_count', 'source_local', 'first_seen', 'last_synced',
    ];
}

function lessons_del_load_csv($path)
{
    if (!file_exists($path)) return [];
    $fh = fopen($path, 'r');
    if (!$fh) return [];
    $cols = lessons_del_columns();
    $header = fgetcsv($fh);
    if ($header === false) { fclose($fh); return []; }
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    if ($header !== $cols) { fclose($fh); return []; }
    $out = [];
    while (($r = fgetcsv($fh)) !== false) {
        if (count($r) !== count($cols)) continue;
        $row = array_combine($cols, $r);
        if (!isset($row['lesson_id']) || $row['lesson_id'] === '') continue;
        $out[$row['lesson_id']] = $row;
    }
    fclose($fh);
    return $out;
}

function lessons_del_trunc($s, $max)
{
    $s = (string)$s;
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($s) <= $max) return $s;
        return mb_substr($s, 0, $max - 1) . '…';
    }
    if (strlen($s) <= $max) return $s;
    return substr($s, 0, $max - 1) . '…';
}

function lessons_del_relative_path($abs, $base)
{
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($abs, $base, strlen($base)) === 0) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($abs, strlen($base)));
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', $abs);
}
