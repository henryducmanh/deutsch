<?php
/**
 * LingQ Update Local — vocab_master → lingq_target.csv (NO API).
 *
 * Phase D step 1. Sinh desired state cho push.php diff vs snapshot.
 *
 * Usage:
 *   C:\php\php74\php.exe module\lingq_sync\update_local.php
 *   C:\php\php74\php.exe module\lingq_sync\update_local.php --dry-run
 *
 * Mapping (vocab_master 14-col → lingq_target 11-col):
 *   lingq_id              = ''  (chưa có trên server — push.php sẽ POST)
 *   term                  = wort
 *   fragment              = beispiel
 *   hint                  = bedeutung
 *   status                = 1   (default cho row mới; PATCH preserve status từ snapshot)
 *   extended_status       = 0
 *   tags                  = "wortart:<wortart>;level:<CEFR>;thema:<thema>;voc:<id>"
 *                           (CEFR detect bằng regex /^[ABC][12]$/ trên tokens trong vocab_master.tags)
 *   importance            = 0
 *   last_studied_correct  = ''
 *   first_seen            = today
 *   last_synced           = ''  (chưa sync với LingQ — push.php sẽ stamp)
 *
 * Atomic write: data/lingq_target.csv.tmp → rename. UTF-8 BOM.
 * Filter: row có wort + bedeutung không rỗng. Skip còn lại với WARN log.
 *
 * Exit:
 *   0 — OK
 *   1 — IO error / vocab_master missing
 */

declare(strict_types=0);

$moduleDir  = __DIR__;
$repoRoot   = realpath(__DIR__ . '/../..');
$vocabPath  = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . '03_unified' . DIRECTORY_SEPARATOR . 'vocab_master.csv';
$targetPath = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'lingq_target.csv';
$logsDir    = $moduleDir . DIRECTORY_SEPARATOR . 'logs';
$logFile    = $logsDir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';

$dryRun = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') { $dryRun = true; continue; }
    if ($arg === '-h' || $arg === '--help') {
        echo "Usage: php update_local.php [--dry-run]\n";
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
echo "[LingQ Update Local] " . date('Y-m-d H:i:s') . PHP_EOL;
$logger('INFO', "update_local start (dry_run=" . ($dryRun ? '1' : '0') . ")");

if (!file_exists($vocabPath)) {
    fwrite(STDERR, "ERROR: vocab_master.csv không tồn tại tại {$vocabPath}\n");
    $logger('ERROR', "vocab_master missing: {$vocabPath}");
    exit(1);
}

// -----------------------------------------------------------------------------
// Read vocab_master.
// -----------------------------------------------------------------------------

$fh = fopen($vocabPath, 'r');
if (!$fh) {
    fwrite(STDERR, "ERROR: không mở được {$vocabPath}\n");
    $logger('ERROR', "vocab_master cannot open");
    exit(1);
}

$header = fgetcsv($fh);
if ($header === false) {
    fclose($fh);
    fwrite(STDERR, "ERROR: vocab_master.csv rỗng\n");
    $logger('ERROR', "vocab_master empty");
    exit(1);
}
if (isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
}

$expected = ['id','wort','wortart','formen','bedeutung','beispiel','uebersetzung','thema','lerndatum','level','quelle','source_type','tags','notes'];
if ($header !== $expected) {
    $logger('WARN', 'vocab_master header mismatch — expected=' . implode(',', $expected) . ' got=' . implode(',', $header));
    fwrite(STDERR, "WARN: header vocab_master không khớp expected. Tiếp tục dùng index theo header tìm thấy.\n");
}

$idx = array_flip($header);
$readRows = 0;
$validRows = [];
$skipped = 0;

while (($row = fgetcsv($fh)) !== false) {
    $readRows++;
    if (count($row) < count($header)) {
        // Pad short rows.
        $row = array_pad($row, count($header), '');
    }
    $get = function ($key) use ($row, $idx) {
        return isset($idx[$key]) && isset($row[$idx[$key]]) ? (string)$row[$idx[$key]] : '';
    };

    $id        = trim($get('id'));
    $wort      = trim($get('wort'));
    $wortart   = trim($get('wortart'));
    $bedeutung = trim($get('bedeutung'));
    $beispiel  = trim($get('beispiel'));
    $thema     = trim($get('thema'));
    $tagsRaw   = trim($get('tags'));

    if ($wort === '' || $bedeutung === '') {
        $skipped++;
        $logger('WARN', "skip row id={$id} wort='{$wort}' bedeutung='{$bedeutung}' — empty required");
        continue;
    }

    $tags = build_tags($wortart, $thema, $id, $tagsRaw);

    $validRows[] = [
        'lingq_id'             => '',
        'term'                 => $wort,
        'fragment'             => $beispiel,
        'hint'                 => $bedeutung,
        'status'               => '1',
        'extended_status'      => '0',
        'tags'                 => $tags,
        'importance'           => '0',
        'last_studied_correct' => '',
        'first_seen'           => date('Y-m-d'),
        'last_synced'          => '',
    ];
}
fclose($fh);

echo "Read: " . relative_path($vocabPath, $repoRoot) . " ({$readRows} rows)" . PHP_EOL;
echo "Filter: rows có wort + bedeutung không rỗng → " . count($validRows) . " valid";
if ($skipped > 0) echo " ({$skipped} skipped, xem log)";
echo PHP_EOL;

$logger('INFO', "read={$readRows} valid=" . count($validRows) . " skipped={$skipped}");

// Sort stable by term ASC (case-insensitive) cho stable diff.
usort($validRows, function ($a, $b) {
    return strcasecmp($a['term'], $b['term']);
});

if ($dryRun) {
    echo PHP_EOL . "Dry run: KHÔNG write " . relative_path($targetPath, $repoRoot) . PHP_EOL;
    $elapsed = number_format(microtime(true) - $startedAt, 1);
    echo "Done in {$elapsed}s. Exit 0." . PHP_EOL;
    $logger('INFO', "update_local dry-run done in {$elapsed}s");
    exit(0);
}

// -----------------------------------------------------------------------------
// Atomic write lingq_target.csv.
// -----------------------------------------------------------------------------

try {
    $wrote = write_target_atomic($targetPath, $validRows);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR writing target: " . $e->getMessage() . "\n");
    $logger('ERROR', 'target write failed: ' . $e->getMessage());
    exit(1);
}

echo "Wrote: " . relative_path($targetPath, $repoRoot) . " ({$wrote} rows)" . PHP_EOL;
$elapsed = number_format(microtime(true) - $startedAt, 1);
echo "Done in {$elapsed}s. Exit 0." . PHP_EOL;
$logger('INFO', "update_local wrote {$wrote} rows in {$elapsed}s");
exit(0);

// =============================================================================

function target_columns()
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
 * Build tags string từ vocab_master fields.
 * Format: "wortart:Substantiv;level:B1;thema:Technologie;voc:VOC-20260518-001"
 * CEFR level extract bằng regex /^[ABC][12]$/i trên tokens trong vocab_master.tags col.
 * Tokens khác trong tags col bị drop (theo design — chỉ giữ 4 typed tags).
 */
function build_tags($wortart, $thema, $id, $tagsRaw)
{
    $parts = [];
    if ($wortart !== '') $parts[] = 'wortart:' . $wortart;

    // Detect CEFR level từ tags col.
    $level = '';
    if ($tagsRaw !== '') {
        $tokens = preg_split('/[;,]/', $tagsRaw);
        foreach ($tokens as $tok) {
            $tok = trim($tok);
            if (preg_match('/^[ABC][12]$/i', $tok)) {
                $level = strtoupper($tok);
                break;
            }
        }
    }
    if ($level !== '') $parts[] = 'level:' . $level;

    if ($thema !== '') $parts[] = 'thema:' . $thema;
    if ($id !== '')    $parts[] = 'voc:' . $id;

    return implode(';', $parts);
}

/**
 * Atomic CSV write — UTF-8 BOM, 11 cột match lingq_cards.csv.
 */
function write_target_atomic($path, array $rows)
{
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $tmp = $path . '.tmp';

    $fh = fopen($tmp, 'w');
    if (!$fh) throw new RuntimeException("Cannot open {$tmp} for write");

    fwrite($fh, "\xEF\xBB\xBF");
    $cols = target_columns();
    fputcsv($fh, $cols);

    $n = 0;
    foreach ($rows as $row) {
        $line = [];
        foreach ($cols as $c) {
            $v = isset($row[$c]) ? (string)$row[$c] : '';
            if ($c === 'fragment') {
                // Escape newlines giống Phase C (mỗi CSV row 1 line).
                $v = str_replace(["\r\n", "\r", "\n"], '\\n', $v);
            }
            $line[] = $v;
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
            throw new RuntimeException("Cannot backup existing target to {$backup}");
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

function relative_path($abs, $base)
{
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($abs, $base, strlen($base)) === 0) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($abs, strlen($base)));
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', $abs);
}
