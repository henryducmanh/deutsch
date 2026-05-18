<?php
/**
 * LingQ Sync — entry point CLI.
 *
 * Usage:
 *   C:\php\php74\php.exe module\lingq_sync\sync.php           # live sync
 *   C:\php\php74\php.exe module\lingq_sync\sync.php --dry-run # plan only, không write csv
 *
 * Exit codes:
 *   0 — OK
 *   1 — config / network / API / IO error
 *
 * Idempotency key: lingq_id (= pk in API response).
 *
 * Update rule per row:
 *   - lingq_id exists in CSV → update all fields EXCEPT first_seen; set last_synced=today.
 *   - lingq_id new           → append, first_seen=today, last_synced=today.
 *   - In CSV nhưng KHÔNG có trong API response (user xoá trên web)
 *     → KHÔNG xoá, log "Removed: N" để user review.
 *
 * Atomic write: lingq_cards.csv.tmp → fsync → rename.
 */

declare(strict_types=0);

require_once __DIR__ . '/lingq_client.php';

$moduleDir = __DIR__;
$repoRoot  = realpath(__DIR__ . '/../..');
$csvPath   = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'lingq_cards.csv';
$logsDir   = $moduleDir . DIRECTORY_SEPARATOR . 'logs';
$logFile   = $logsDir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
$configPath= $moduleDir . DIRECTORY_SEPARATOR . 'config.php';

$dryRun = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') { $dryRun = true; continue; }
    if ($arg === '-h' || $arg === '--help') {
        echo "Usage: php sync.php [--dry-run]\n";
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

echo "[LingQ Sync] " . date('Y-m-d H:i:s') . PHP_EOL;
echo "Config: " . str_replace($repoRoot . DIRECTORY_SEPARATOR, '', $configPath) . PHP_EOL;

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

echo "Language: {$cfg['language']} | Page size: {$cfg['page_size']}" . PHP_EOL;
if ($dryRun) echo "(--dry-run: sẽ KHÔNG write data/lingq_cards.csv)" . PHP_EOL;
echo PHP_EOL;

$logger('INFO', "sync start (dry_run=" . ($dryRun ? '1' : '0') . ")");

try {
    $client = new LingqClient($cfg, $logger);
    $cards  = $client->fetchAllCards();
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    $logger('ERROR', $e->getMessage());
    exit(1);
}

$total = count($cards);
echo PHP_EOL . "Total fetched: {$total}" . PHP_EOL;
$logger('INFO', "fetched {$total} cards");

// Load existing CSV (if any) into map by lingq_id.
$existing = load_existing_csv($csvPath, $logger);

$today = date('Y-m-d');
$new = $updated = $unchanged = 0;
$skipped = 0;
$apiIds = [];
$outRows = $existing;  // start from existing rows; we'll modify in place.

foreach ($cards as $card) {
    $row = normalize_card($card, $logger);
    if ($row === null) { $skipped++; continue; }

    $id = $row['lingq_id'];
    $apiIds[$id] = true;

    if (isset($existing[$id])) {
        $prev = $existing[$id];
        $merged = $row;
        $merged['first_seen']  = $prev['first_seen'];   // KHÔNG đổi first_seen
        $merged['last_synced'] = $today;

        if (rows_equal_excluding_last_synced($prev, $merged)) {
            // No content change — still bump last_synced.
            $merged['last_synced'] = $today;
            // Treat as unchanged iff last_synced cũ cũng = today; else still counts unchanged
            // (per spec: "Updated" = any field difference other than last_synced).
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

// Detect removed: in CSV nhưng không có trong API response (do NOT drop them).
$removed = 0;
foreach ($existing as $id => $row) {
    if (!isset($apiIds[$id])) {
        $removed++;
    }
}

echo "Diff vs " . relative_path($csvPath, $repoRoot) . ":" . PHP_EOL;
printf("  New:       %d\n", $new);
printf("  Updated:   %d\n", $updated);
printf("  Unchanged: %d\n", $unchanged);
printf("  Removed:   %d  (LingQ xoá khỏi account)\n", $removed);
if ($skipped > 0) printf("  Skipped:   %d  (malformed rows — xem log)\n", $skipped);

$logger('INFO', "diff new={$new} updated={$updated} unchanged={$unchanged} removed={$removed} skipped={$skipped}");

if ($dryRun) {
    echo PHP_EOL . "Dry run: KHÔNG write csv." . PHP_EOL;
    echo "Log:   " . relative_path($logFile, $repoRoot) . PHP_EOL;
    $elapsed = number_format(microtime(true) - $startedAt, 1);
    echo "Done in {$elapsed}s. Exit 0." . PHP_EOL;
    $logger('INFO', "dry-run done in {$elapsed}s");
    exit(0);
}

// Atomic write
try {
    $rowsWritten = write_csv_atomic($csvPath, $outRows);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR writing csv: " . $e->getMessage() . "\n");
    $logger('ERROR', 'csv write failed: ' . $e->getMessage());
    exit(1);
}

echo PHP_EOL . "Wrote: " . relative_path($csvPath, $repoRoot) . " ({$rowsWritten} rows)" . PHP_EOL;
echo "Log:   " . relative_path($logFile, $repoRoot) . PHP_EOL;
$elapsed = number_format(microtime(true) - $startedAt, 1);
echo "Done in {$elapsed}s. Exit 0." . PHP_EOL;
$logger('INFO', "wrote {$rowsWritten} rows; done in {$elapsed}s");

exit(0);

// -----------------------------------------------------------------------------

function csv_columns()
{
    return [
        'lingq_id',
        'term',
        'fragment',
        'hint',
        'status',
        'extended_status',
        'tags',
        'importance',
        'last_studied_correct',
        'first_seen',
        'last_synced',
    ];
}

/**
 * Normalize one API card → CSV row associative array.
 * Returns null + logs WARN if required fields missing.
 */
function normalize_card($card, callable $logger)
{
    if (!is_array($card)) {
        $logger('WARN', 'skip non-array card');
        return null;
    }
    if (!isset($card['pk']) || !isset($card['term'])) {
        $snippet = json_encode($card, JSON_UNESCAPED_UNICODE);
        $logger('WARN', 'skip card missing pk/term: ' . substr((string)$snippet, 0, 200));
        return null;
    }

    $tags = '';
    if (isset($card['tags']) && is_array($card['tags'])) {
        $tags = implode(';', array_map('strval', $card['tags']));
    }

    return [
        'lingq_id'             => (string)$card['pk'],
        'term'                 => (string)$card['term'],
        'fragment'             => isset($card['fragment']) ? (string)$card['fragment'] : '',
        'hint'                 => isset($card['hint']) ? (string)$card['hint'] : '',
        'status'               => isset($card['status']) ? (string)(int)$card['status'] : '',
        'extended_status'      => isset($card['extended_status']) ? (string)(int)$card['extended_status'] : '0',
        'tags'                 => $tags,
        'importance'           => isset($card['importance']) ? (string)(int)$card['importance'] : '0',
        'last_studied_correct' => isset($card['last_studied_correct']) ? (string)$card['last_studied_correct'] : '',
        // first_seen + last_synced filled by caller.
        'first_seen'           => '',
        'last_synced'          => '',
    ];
}

/**
 * Read existing csv (with BOM) → map[lingq_id] => row.
 * Returns empty array if file missing or header-only.
 */
function load_existing_csv($path, callable $logger)
{
    if (!file_exists($path)) return [];
    $fh = fopen($path, 'r');
    if (!$fh) return [];

    $cols = csv_columns();
    $header = fgetcsv($fh);
    if ($header === false) { fclose($fh); return []; }

    // Strip BOM from first cell if present.
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }

    if ($header !== $cols) {
        $logger('WARN', 'existing csv header mismatch — treating as empty. expected=' . implode(',', $cols) . ' got=' . implode(',', $header));
        fclose($fh);
        return [];
    }

    $out = [];
    while (($r = fgetcsv($fh)) !== false) {
        if (count($r) !== count($cols)) continue;
        $row = array_combine($cols, $r);
        if (!isset($row['lingq_id']) || $row['lingq_id'] === '') continue;
        // Un-escape \n inside fragment.
        $row['fragment'] = str_replace('\\n', "\n", $row['fragment']);
        $out[$row['lingq_id']] = $row;
    }
    fclose($fh);
    return $out;
}

function rows_equal_excluding_last_synced(array $a, array $b)
{
    foreach (csv_columns() as $c) {
        if ($c === 'last_synced') continue;
        $av = isset($a[$c]) ? (string)$a[$c] : '';
        $bv = isset($b[$c]) ? (string)$b[$c] : '';
        if ($av !== $bv) return false;
    }
    return true;
}

/**
 * Atomic CSV write — tmp + rename. UTF-8 BOM.
 * Returns number of data rows written.
 */
function write_csv_atomic($path, array $rowsById)
{
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $tmp = $path . '.tmp';

    $fh = fopen($tmp, 'w');
    if (!$fh) throw new RuntimeException("Cannot open {$tmp} for write");

    // BOM
    fwrite($fh, "\xEF\xBB\xBF");

    $cols = csv_columns();
    fputcsv($fh, $cols);

    // Sort by lingq_id (numeric asc) for stable diff.
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
            $v = isset($row[$c]) ? (string)$row[$c] : '';
            if ($c === 'fragment') {
                // Escape newlines inside fragment → "\n" literal (so each csv row stays single-line).
                $v = str_replace(["\r\n", "\r", "\n"], '\\n', $v);
            }
            $line[] = $v;
        }
        fputcsv($fh, $line);
        $n++;
    }

    fflush($fh);
    if (function_exists('fsync')) {
        @fsync($fh);   // PHP 8.1+; no-op via @ on PHP 7.4
    }
    fclose($fh);

    // On Windows, rename fails if target exists — replace via temp dance.
    if (file_exists($path)) {
        $backup = $path . '.bak';
        if (file_exists($backup)) @unlink($backup);
        if (!@rename($path, $backup)) {
            throw new RuntimeException("Cannot backup existing csv to {$backup}");
        }
        if (!@rename($tmp, $path)) {
            // Try to restore.
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

function relative_path($abs, $base)
{
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($abs, $base, strlen($base)) === 0) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($abs, strlen($base)));
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', $abs);
}
