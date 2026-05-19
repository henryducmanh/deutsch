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

require_once __DIR__ . '/notes_builder.php';

$moduleDir   = __DIR__;
$repoRoot    = realpath(__DIR__ . '/../..');
$vocabPath   = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . '03_unified' . DIRECTORY_SEPARATOR . 'vocab_master.csv';
$targetPath  = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'lingq_target.csv';
$chunksPath  = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'chunks_master.csv';
$weakPath    = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'weak_words.csv';
$mistakesPath= $repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'MISTAKES_LOG.md';
$configPath  = $moduleDir . DIRECTORY_SEPARATOR . 'config.php';
$logsDir     = $moduleDir . DIRECTORY_SEPARATOR . 'logs';
$logFile     = $logsDir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';

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

// Load config for Phase J keys (notes_prefix, notes_max_chars, notes_max_collocations,
// notes_max_mistakes, notes_enrichment, notes_strict_chunk_match).
$cfg = [];
if (file_exists($configPath)) {
    try {
        $loaded = require $configPath;
        if (is_array($loaded)) $cfg = $loaded;
    } catch (Throwable $e) {
        $logger('WARN', "config.php load failed (using defaults): " . $e->getMessage());
    }
}
$enrich = !array_key_exists('notes_enrichment', $cfg) || !empty($cfg['notes_enrichment']);

// Phase J — load 3 source: chunks, weak_words, MISTAKES_LOG. Đọc 1 lần đầu run.
$allChunks   = $enrich ? load_chunks_master($chunksPath)   : [];
$allWeak     = $enrich ? load_weak_words($weakPath)        : [];
$allMistakes = $enrich ? parse_mistakes_log($mistakesPath) : [];
echo sprintf(
    "Phase J sources: chunks=%d, weak=%d, mistakes=%d (enrichment=%s)\n",
    count($allChunks), count($allWeak), count($allMistakes), $enrich ? 'ON' : 'OFF'
);
$logger('INFO', sprintf(
    "phase_j sources chunks=%d weak=%d mistakes=%d enrichment=%s",
    count($allChunks), count($allWeak), count($allMistakes), $enrich ? '1' : '0'
));

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
$enrichedCount = 0;
$truncatedCount = 0;
$today = date('Y-m-d');

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
    $vocNotes  = trim($get('notes'));

    if ($wort === '' || $bedeutung === '') {
        $skipped++;
        $logger('WARN', "skip row id={$id} wort='{$wort}' bedeutung='{$bedeutung}' — empty required");
        continue;
    }

    $tags = build_tags($wortart, $thema, $id, $tagsRaw);

    // Phase J — build enriched notes (markdown) hoặc fallback plain.
    $notesOut = '';
    if ($enrich) {
        $vocRow = [
            'id'    => $id,
            'wort'  => $wort,
            'notes' => $vocNotes,
        ];
        $notesOut = build_enriched_notes($vocRow, $allChunks, $allWeak, $allMistakes, $cfg, $today);
    } elseif ($vocNotes !== '') {
        // Fallback (notes_enrichment=false): chỉ marker + raw vocab.notes.
        $prefix = isset($cfg['notes_prefix']) ? (string)$cfg['notes_prefix'] : '[AI-sync %DATE% | %ID%]';
        $marker = strtr($prefix, ['%DATE%' => $today, '%ID%' => $id]);
        $notesOut = $marker . "\n\n" . $vocNotes;
    }
    if ($notesOut !== '') {
        $enrichedCount++;
        $maxChars = isset($cfg['notes_max_chars']) ? (int)$cfg['notes_max_chars'] : 50000;
        if (strlen($notesOut) > $maxChars - strlen("\n... (truncated at {$maxChars} chars)")) {
            // Heuristic — chính xác hơn so length sau truncate, nhưng tệ nhất chỉ over-count nhẹ.
            $truncatedCount++;
        }
    }

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
        'first_seen'           => $today,
        'last_synced'          => '',
        'notes'                => $notesOut,
    ];
}
fclose($fh);

echo "Read: " . relative_path($vocabPath, $repoRoot) . " ({$readRows} rows)" . PHP_EOL;
echo "Filter: rows có wort + bedeutung không rỗng → " . count($validRows) . " valid";
if ($skipped > 0) echo " ({$skipped} skipped, xem log)";
echo PHP_EOL;

$logger('INFO', "read={$readRows} valid=" . count($validRows) . " skipped={$skipped}");

echo sprintf(
    "Phase J build: %d/%d rows have enriched content; %d truncated to notes_max_chars.\n",
    $enrichedCount, count($validRows), $truncatedCount
);
$logger('INFO', "phase_j enriched={$enrichedCount}/" . count($validRows) . " truncated={$truncatedCount}");

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
        'notes',           // Phase J — enriched markdown (marker + sections).
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
            if ($c === 'fragment' || $c === 'notes') {
                // Escape newlines (mỗi CSV row 1 line).
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
