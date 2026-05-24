<?php
/**
 * LingQ Lessons Sync (Phase K1) — fetch toàn bộ lessons của user → CSV shadow.
 *
 * Usage:
 *   C:\php\php74\php.exe module\lingq_sync\lessons_sync.php
 *   C:\php\php74\php.exe module\lingq_sync\lessons_sync.php --dry-run
 *
 * Exit codes:
 *   0 — OK
 *   1 — config / network / API / IO error
 *
 * Source: GET /api/v3/{lang}/search/?shelf=my_lessons (paginate qua 'next').
 *
 * Idempotency key: lesson_id (= 'id' trong search result).
 * Update rule per row:
 *   - lesson_id có trong CSV → update field từ API, GIỮ first_seen + source_local +
 *     words_count cũ (search endpoint không trả total wordCount); last_synced=today.
 *   - lesson_id mới          → append, first_seen=today, source_local='' (chỉ set khi
 *     push qua lessons_push.php ở K2/K3).
 *   - Trong CSV nhưng KHÔNG có trong API (user xoá trên web) → KHÔNG drop, đếm "Removed".
 *
 * Atomic write: lingq_lessons.csv.tmp → rename (Windows backup dance).
 *
 * Lưu ý schema: search my_lessons trả 'newWordsCount' (→ unknown_count) nhưng KHÔNG
 * trả tổng wordCount. words_count để trống (preserve nếu push từng set). Per-lesson GET
 * để lấy wordCount sẽ phá ngân sách 30s với 200+ bài → cố ý bỏ.
 */

declare(strict_types=0);

require_once __DIR__ . '/lingq_client.php';

$moduleDir  = __DIR__;
$repoRoot   = realpath(__DIR__ . '/../..');
$csvPath    = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'lingq_lessons.csv';
$logsDir    = $moduleDir . DIRECTORY_SEPARATOR . 'logs';
$logFile    = $logsDir . DIRECTORY_SEPARATOR . 'lessons_' . date('Y-m-d') . '.log';
$configPath = $moduleDir . DIRECTORY_SEPARATOR . 'config.php';

$dryRun = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') { $dryRun = true; continue; }
    if ($arg === '-h' || $arg === '--help') {
        echo "Usage: php lessons_sync.php [--dry-run]\n";
        exit(0);
    }
    fwrite(STDERR, "Unknown arg: {$arg}\n");
    exit(1);
}

if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0775, true);
}

$logger = function ($level, $msg) use ($logFile) {
    $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $msg);
    @file_put_contents($logFile, $line, FILE_APPEND);
};

$startedAt = microtime(true);

echo "[LingQ Lessons Sync] " . date('Y-m-d H:i:s') . PHP_EOL;
echo "Config: " . lessons_relative_path($configPath, $repoRoot) . PHP_EOL;

if (!file_exists($configPath)) {
    fwrite(STDERR, "ERROR: config.php không tồn tại. Copy config.example.php → config.php và paste API token.\n");
    $logger('ERROR', 'config.php not found');
    exit(1);
}

try {
    $cfg = require $configPath;
    if (!is_array($cfg)) {
        throw new RuntimeException('config.php phải return array.');
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR loading config.php: " . $e->getMessage() . "\n");
    $logger('ERROR', 'config load failed: ' . $e->getMessage());
    exit(1);
}

$lang = isset($cfg['language']) ? (string)$cfg['language'] : 'de';
echo "Language: {$lang} | Page size: {$cfg['page_size']} | shelf=my_lessons" . PHP_EOL;
if ($dryRun) echo "(--dry-run: sẽ KHÔNG write data/lingq_lessons.csv)" . PHP_EOL;
echo PHP_EOL;

$logger('INFO', "lessons sync start (dry_run=" . ($dryRun ? '1' : '0') . ")");

try {
    $client  = new LingqClient($cfg, $logger);
    $lessons = $client->fetchAllLessons();
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    $logger('ERROR', $e->getMessage());
    exit(1);
}

$total = count($lessons);
echo PHP_EOL . "Total fetched: {$total}" . PHP_EOL;
$logger('INFO', "fetched {$total} lessons");

$existing = lessons_load_existing_csv($csvPath, $logger);

$today = date('Y-m-d');
$new = $updated = $unchanged = 0;
$skipped = 0;
$apiIds = [];
$outRows = $existing;

foreach ($lessons as $lesson) {
    $row = lessons_normalize($lesson, $logger, $lang);
    if ($row === null) { $skipped++; continue; }

    $id = $row['lesson_id'];
    $apiIds[$id] = true;

    if (isset($existing[$id])) {
        $prev = $existing[$id];
        $merged = $row;
        // Preserve các field local-only (server không cung cấp / chỉ push set).
        $merged['first_seen']   = $prev['first_seen'];
        $merged['source_local'] = ($prev['source_local'] !== '') ? $prev['source_local'] : $row['source_local'];
        $merged['words_count']  = ($prev['words_count'] !== '') ? $prev['words_count'] : $row['words_count'];
        $merged['last_synced']  = $today;

        if (lessons_rows_equal_excluding_last_synced($prev, $merged)) {
            $unchanged++;
        } else {
            $updated++;
        }
        $outRows[$id] = $merged;
    } else {
        $row['first_seen']  = $today;
        $row['last_synced'] = $today;
        $outRows[$id] = $row;
        $new++;
    }
}

$removed = 0;
foreach ($existing as $id => $row) {
    if (!isset($apiIds[$id])) {
        $removed++;
    }
}

echo "Diff vs " . lessons_relative_path($csvPath, $repoRoot) . ":" . PHP_EOL;
printf("  New:       %d\n", $new);
printf("  Updated:   %d\n", $updated);
printf("  Unchanged: %d\n", $unchanged);
printf("  Removed:   %d  (có trong CSV cũ, không còn trên LingQ)\n", $removed);
if ($skipped > 0) printf("  Skipped:   %d  (malformed rows — xem log)\n", $skipped);

$logger('INFO', "diff new={$new} updated={$updated} unchanged={$unchanged} removed={$removed} skipped={$skipped}");

if ($dryRun) {
    echo PHP_EOL . "Dry run: KHÔNG write csv." . PHP_EOL;
    echo "Log:   " . lessons_relative_path($logFile, $repoRoot) . PHP_EOL;
    $elapsed = number_format(microtime(true) - $startedAt, 1);
    echo "Done in {$elapsed}s. Exit 0." . PHP_EOL;
    $logger('INFO', "dry-run done in {$elapsed}s");
    exit(0);
}

try {
    $rowsWritten = lessons_write_csv_atomic($csvPath, $outRows);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR writing csv: " . $e->getMessage() . "\n");
    $logger('ERROR', 'csv write failed: ' . $e->getMessage());
    exit(1);
}

echo PHP_EOL . "Wrote: " . lessons_relative_path($csvPath, $repoRoot) . " ({$rowsWritten} rows)" . PHP_EOL;
echo "Log:   " . lessons_relative_path($logFile, $repoRoot) . PHP_EOL;
$elapsed = number_format(microtime(true) - $startedAt, 1);
echo "Done in {$elapsed}s. Exit 0." . PHP_EOL;
$logger('INFO', "wrote {$rowsWritten} rows; done in {$elapsed}s");

exit(0);

// -----------------------------------------------------------------------------

function lessons_csv_columns()
{
    return [
        'lesson_id',
        'course_id',
        'title',
        'language',
        'audio_url',
        'words_count',     // tổng số từ — search endpoint không trả; '' trừ khi push set.
        'unknown_count',   // = newWordsCount (từ chưa biết).
        'source_local',    // ref ngược folder gốc; chỉ set khi push (K2/K3) — sync preserve.
        'first_seen',
        'last_synced',
    ];
}

/**
 * Normalize 1 search result → CSV row. Returns null + log WARN nếu thiếu id.
 * source_local + words_count + first_seen/last_synced do caller fill/preserve.
 */
function lessons_normalize($lesson, callable $logger, $lang = 'de')
{
    if (!is_array($lesson)) {
        $logger('WARN', 'skip non-array lesson');
        return null;
    }
    if (!isset($lesson['id'])) {
        $snippet = json_encode($lesson, JSON_UNESCAPED_UNICODE);
        $logger('WARN', 'skip lesson missing id: ' . substr((string)$snippet, 0, 200));
        return null;
    }

    $title = isset($lesson['title']) ? (string)$lesson['title'] : '';
    // title 1 dòng — strip CR/LF phòng hờ.
    $title = str_replace(["\r\n", "\r", "\n"], ' ', $title);

    return [
        'lesson_id'     => (string)$lesson['id'],
        'course_id'     => isset($lesson['collectionId']) && $lesson['collectionId'] !== null ? (string)$lesson['collectionId'] : '',
        'title'         => $title,
        'language'      => $lang,
        'audio_url'     => isset($lesson['audioUrl']) && $lesson['audioUrl'] !== null ? (string)$lesson['audioUrl'] : '',
        'words_count'   => '',
        'unknown_count' => isset($lesson['newWordsCount']) ? (string)(int)$lesson['newWordsCount'] : '',
        'source_local'  => '',
        'first_seen'    => '',
        'last_synced'   => '',
    ];
}

/**
 * Read existing csv (BOM-aware) → map[lesson_id] => row. Empty nếu file thiếu /
 * header mismatch (treat as rebuild).
 */
function lessons_load_existing_csv($path, callable $logger)
{
    if (!file_exists($path)) return [];
    $fh = fopen($path, 'r');
    if (!$fh) return [];

    $cols = lessons_csv_columns();
    $header = fgetcsv($fh);
    if ($header === false) { fclose($fh); return []; }
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    if ($header !== $cols) {
        $logger('WARN', 'existing lessons csv header mismatch — treating as empty. expected=' . implode(',', $cols) . ' got=' . implode(',', $header));
        fclose($fh);
        return [];
    }

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

function lessons_rows_equal_excluding_last_synced(array $a, array $b)
{
    foreach (lessons_csv_columns() as $c) {
        if ($c === 'last_synced') continue;
        $av = isset($a[$c]) ? (string)$a[$c] : '';
        $bv = isset($b[$c]) ? (string)$b[$c] : '';
        if ($av !== $bv) return false;
    }
    return true;
}

/**
 * Atomic CSV write — tmp + rename. UTF-8 BOM. Sort by lesson_id numeric asc.
 * Returns số data rows.
 */
function lessons_write_csv_atomic($path, array $rowsById)
{
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $tmp = $path . '.tmp';

    $fh = fopen($tmp, 'w');
    if (!$fh) throw new RuntimeException("Cannot open {$tmp} for write");

    fwrite($fh, "\xEF\xBB\xBF");

    $cols = lessons_csv_columns();
    fputcsv($fh, $cols);

    $keys = array_keys($rowsById);
    usort($keys, function ($a, $b) {
        $ai = is_numeric($a) ? (int)$a : 0;
        $bi = is_numeric($b) ? (int)$b : 0;
        if ($ai === $bi) return strcmp((string)$a, (string)$b);
        return $ai <=> $bi;
    });

    $n = 0;
    foreach ($keys as $id) {
        $row = $rowsById[$id];
        $line = [];
        foreach ($cols as $c) {
            $line[] = isset($row[$c]) ? (string)$row[$c] : '';
        }
        fputcsv($fh, $line);
        $n++;
    }

    fflush($fh);
    if (function_exists('fsync')) {
        @fsync($fh);
    }
    fclose($fh);

    if (file_exists($path)) {
        $backup = $path . '.bak';
        if (file_exists($backup)) @unlink($backup);
        if (!@rename($path, $backup)) {
            throw new RuntimeException("Cannot backup existing csv to {$backup}");
        }
        if (!@rename($tmp, $path)) {
            @rename($backup, $path);
            throw new RuntimeException("Cannot move {$tmp} → {$path}");
        }
        @unlink($backup);
    } else {
        if (!@rename($tmp, $path)) {
            throw new RuntimeException("Cannot move {$tmp} → {$path}");
        }
    }

    return $n;
}

function lessons_relative_path($abs, $base)
{
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($abs, $base, strlen($base)) === 0) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($abs, strlen($base)));
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', $abs);
}
