<?php
/**
 * LingQ Lessons Push (Phase K2 text / K3 audio / Phase L batch) — push bài local lên LingQ.
 *
 * Usage (default = DRY-RUN):
 *   SINGLE (1 folder/lần):
 *     C:\php\php74\php.exe module\lingq_sync\lessons_push.php input\html\deutsch-vorbereitung\lesen\1.1\
 *     C:\php\php74\php.exe module\lingq_sync\lessons_push.php <folder> --apply
 *     C:\php\php74\php.exe module\lingq_sync\lessons_push.php <folder> --apply --force-update
 *     C:\php\php74\php.exe module\lingq_sync\lessons_push.php <folder> --apply --no-course
 *   BATCH (Phase L — nhiều folder qua glob):
 *     C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\*"
 *     C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "...\horen\*" --apply --limit 5 --sleep 2.0
 *
 * Flags:
 *   --apply        : thật sự POST lên LingQ. Mặc định DRY-RUN (in payload/plan).
 *   --dry-run      : explicit dry-run (= default).
 *   --force-update : bài đã push (source_local có trong CSV) → PATCH thay vì skip.
 *   --no-course    : cho phép --apply khi lessons_course_id rỗng (push không collection).
 *   --no-resync    : KHÔNG tự re-sync CSV (mặc định re-sync để bắt lesson_id; batch chỉ 1 lần cuối).
 *   --batch <glob> : push tất cả folder match glob (mutually exclusive với positional <folder>).
 *   --limit N      : (batch) dừng sau N folder ĐƯỢC PROCESS (không tính skip).
 *   --sleep S      : (batch) ngủ S giây sau mỗi push thật (chống rate-limit). Default 2.0. Skip không sleep.
 *
 * Input folder:
 *   Lesen:  <folder>/X.X_text.md        (frontmatter: bai/teil/teil_desc/chu_de/url)
 *   Hören:  <folder>/X.X_transcript.md  (+ X.X.mp3 → audio, xử lý K3)
 *
 * Idempotency: source_local (relative folder path) trong data/lingq_lessons.csv.
 *   Đã có → skip (cảnh báo lesson_id). --force-update → PATCH.
 *
 * Exit codes: 0 OK · 1 fatal (config/IO/parse/course-missing/bad args). Batch: lỗi 1 folder
 *   KHÔNG abort (counter errors), exit 0 khi loop xong — xem BATCH SUMMARY.
 *
 * K2/K3 verified live 2026-05-24 (POST 201 trả id, PATCH multipart audio 200).
 *   Phase L thêm batch loop + resume (dedupe theo source_local) + re-sync 1 lần cuối.
 */

declare(strict_types=0);

require_once __DIR__ . '/lingq_client.php';

$moduleDir  = __DIR__;
$repoRoot   = realpath(__DIR__ . '/../..');
$csvPath    = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'lingq_lessons.csv';
$logsDir    = $moduleDir . DIRECTORY_SEPARATOR . 'logs';
$logFile    = $logsDir . DIRECTORY_SEPARATOR . 'lessons_' . date('Y-m-d') . '.log';
$configPath = $moduleDir . DIRECTORY_SEPARATOR . 'config.php';
$syncScript = $moduleDir . DIRECTORY_SEPARATOR . 'lessons_sync.php';

$apply = false;
$forceUpdate = false;
$noCourse = false;
$resync = true;
$folderArg = null;
$batchGlob = null;
$limit = null;     // null = không giới hạn (chỉ batch).
$sleepSec = 2.0;   // batch: ngủ sau mỗi push thật. Skip không sleep.

$args = array_slice($argv, 1);
for ($i = 0; $i < count($args); $i++) {
    $arg = $args[$i];
    if ($arg === '--apply')        { $apply = true; continue; }
    if ($arg === '--dry-run')      { $apply = false; continue; }
    if ($arg === '--force-update') { $forceUpdate = true; continue; }
    if ($arg === '--no-course')    { $noCourse = true; continue; }
    if ($arg === '--no-resync')    { $resync = false; continue; }
    if ($arg === '-h' || $arg === '--help') {
        echo "Usage:\n";
        echo "  Single: php lessons_push.php <folder> [--apply] [--force-update] [--no-course] [--no-resync]\n";
        echo "  Batch:  php lessons_push.php --batch \"<glob>\" [--apply] [--limit N] [--sleep S] [--force-update] [--no-course] [--no-resync]\n";
        exit(0);
    }
    // Value-taking flags: chấp nhận cả '--flag value' lẫn '--flag=value'.
    if ($arg === '--batch') {
        $batchGlob = isset($args[$i + 1]) ? $args[++$i] : null;
        if ($batchGlob === null) { fwrite(STDERR, "ERROR: --batch cần <glob>.\n"); exit(1); }
        continue;
    }
    if (strpos($arg, '--batch=') === 0) { $batchGlob = substr($arg, 8); continue; }
    if ($arg === '--limit' || strpos($arg, '--limit=') === 0) {
        $v = ($arg === '--limit') ? (isset($args[$i + 1]) ? $args[++$i] : null) : substr($arg, 8);
        if ($v === null || !ctype_digit((string)$v)) { fwrite(STDERR, "ERROR: --limit cần số nguyên ≥ 0.\n"); exit(1); }
        $limit = (int)$v;
        continue;
    }
    if ($arg === '--sleep' || strpos($arg, '--sleep=') === 0) {
        $v = ($arg === '--sleep') ? (isset($args[$i + 1]) ? $args[++$i] : null) : substr($arg, 8);
        if ($v === null || !is_numeric($v)) { fwrite(STDERR, "ERROR: --sleep cần số giây (float).\n"); exit(1); }
        $sleepSec = (float)$v;
        continue;
    }
    if (strpos($arg, '--') === 0) {
        fwrite(STDERR, "Unknown flag: {$arg}\n");
        exit(1);
    }
    if ($folderArg === null) { $folderArg = $arg; continue; }
    fwrite(STDERR, "Chỉ nhận 1 folder (single mode). Dư arg: {$arg}\n");
    exit(1);
}

// Mutual exclusion + required.
if ($batchGlob !== null && $folderArg !== null) {
    fwrite(STDERR, "ERROR: không thể vừa --batch vừa single <folder>. Chọn 1.\n");
    exit(1);
}
if ($batchGlob === null && $folderArg === null) {
    fwrite(STDERR, "ERROR: cần <folder> (single) hoặc --batch \"<glob>\".\n");
    fwrite(STDERR, "  Single: php lessons_push.php input/html/deutsch-vorbereitung/horen/1.1/\n");
    fwrite(STDERR, "  Batch:  php lessons_push.php --batch \"input/html/deutsch-vorbereitung/horen/*\" --apply\n");
    exit(1);
}
$batchMode = ($batchGlob !== null);

if (!is_dir($logsDir)) @mkdir($logsDir, 0775, true);
$logger = function ($level, $msg) use ($logFile) {
    $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $msg);
    @file_put_contents($logFile, $line, FILE_APPEND);
};

$startedAt = microtime(true);
$modeLabel = $apply ? 'LIVE --apply' : 'DRY-RUN default';
echo "[LingQ Lessons Push] " . date('Y-m-d H:i:s') . " ({$modeLabel}" . ($batchMode ? ", BATCH" : "") . ")" . PHP_EOL;

// Config (load 1 lần cho cả single + batch).
if (!file_exists($configPath)) {
    fwrite(STDERR, "ERROR: config.php không tồn tại.\n");
    exit(1);
}
try {
    $cfg = require $configPath;
    if (!is_array($cfg)) throw new RuntimeException('config phải return array.');
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR loading config: " . $e->getMessage() . "\n");
    exit(1);
}
$lang        = isset($cfg['language']) ? (string)$cfg['language'] : 'de';
$courseId    = isset($cfg['lessons_course_id']) ? (string)$cfg['lessons_course_id'] : '';
$level       = isset($cfg['lessons_level']) ? (int)$cfg['lessons_level'] : 3;
$status      = isset($cfg['lessons_status']) ? (string)$cfg['lessons_status'] : 'private';
$defaultTags = isset($cfg['lessons_default_tags']) && is_array($cfg['lessons_default_tags']) ? $cfg['lessons_default_tags'] : ['DTZ', 'B1'];

$settings = [
    'lang' => $lang, 'courseId' => $courseId, 'level' => $level,
    'status' => $status, 'defaultTags' => $defaultTags,
    'apply' => $apply, 'forceUpdate' => $forceUpdate, 'noCourse' => $noCourse,
    'batch' => $batchMode,
];

// Course guard (1 lần, trước mọi API call). Chỉ áp dụng khi --apply.
if ($apply && $courseId === '' && !$noCourse) {
    fwrite(STDERR, PHP_EOL . "ABORT: lessons_course_id chưa set trong config.php.\n");
    fwrite(STDERR, "Hướng dẫn:\n");
    fwrite(STDERR, "  1. Mở LingQ web → tạo 1 Course (Collection) DE để chứa bài, vd 'DTZ Vorbereitung'.\n");
    fwrite(STDERR, "  2. Lấy PK course từ URL (.../library/course/<PK>) hoặc collections/my API.\n");
    fwrite(STDERR, "  3. Paste vào config.php: 'lessons_course_id' => '<PK>'.\n");
    fwrite(STDERR, "  Hoặc chạy với --no-course để push không gắn collection (bài sẽ rời).\n");
    $logger('ERROR', "apply abort: lessons_course_id empty (no --no-course)");
    exit(1);
}

// Client (chỉ khi apply). Dry-run không cần.
$client = null;
if ($apply) {
    try {
        $client = new LingqClient($cfg, $logger);
    } catch (Throwable $e) {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// CSV state + resume set (load 1 lần).
$existingRows  = push_load_csv($csvPath);
$pushedSet     = build_pushed_set($existingRows);
$csvRowsBefore = count($existingRows);

// =============================================================================
// SINGLE MODE — giữ behavior Phase K (1 folder, re-sync sau push).
// =============================================================================
if (!$batchMode) {
    $folderAbs = push_resolve_folder($folderArg, $repoRoot);
    if ($folderAbs === null) {
        fwrite(STDERR, "ERROR: folder không tồn tại: {$folderArg}\n");
        $logger('ERROR', "folder not found: {$folderArg}");
        exit(1);
    }
    $logger('INFO', "push start folder=" . push_source_local($folderAbs, $repoRoot) . " apply=" . ($apply ? 1 : 0) . " force_update=" . ($forceUpdate ? 1 : 0));

    $res = push_one_folder($folderAbs, $settings, $client, $pushedSet, $repoRoot, $logger, '');

    // Single mode: parse/no-file là fatal (giữ exit 1 như Phase K).
    if ($res['status'] === 'skip_missing') {
        fwrite(STDERR, "ERROR: không tìm thấy *_text.md hoặc *_transcript.md trong folder.\n");
        exit(1);
    }
    if ($res['status'] === 'error') {
        fwrite(STDERR, "ERROR: " . $res['message'] . "\n");
        exit(1);
    }
    // Dry-run / skip-already → push_one_folder đã in; exit 0.
    if (!$apply || $res['status'] === 'dry' || $res['status'] === 'skip_already') {
        exit(0);
    }

    // Apply xong (pushed/patched). Re-sync + upsert (upsert SAU re-sync để audio_url giữ).
    $lessonId = $res['lesson_id'];
    if ($resync) {
        push_run_resync($syncScript, $logger);
        if ($lessonId === '' && $res['needs_title_match']) {
            $rows = push_load_csv($csvPath);
            $best = null;
            foreach ($rows as $r) {
                if (push_norm($r['title']) === push_norm($res['title'])) {
                    if ($best === null || (int)$r['lesson_id'] > (int)$best['lesson_id']) $best = $r;
                }
            }
            if ($best !== null) {
                $lessonId = (string)$best['lesson_id'];
                echo "  Matched via re-sync: lesson_id={$lessonId}" . PHP_EOL;
            }
        }
    }
    if ($lessonId !== '') {
        $n = push_upsert_source_local($csvPath, $lessonId, $res['source_local'], $logger, $res['audio_url']);
        echo "  CSV: set source_local" . ($res['audio_url'] !== '' ? " + audio_url" : "") . " cho lesson_id={$lessonId} ({$n} rows total)." . PHP_EOL;
        echo PHP_EOL . "✅ Pushed. Xem: https://www.lingq.com/learn/{$lang}/web/reader/{$lessonId}" . PHP_EOL;
        $logger('INFO', "pushed source={$res['source_local']} lesson_id={$lessonId}");
    } else {
        fwrite(STDERR, "WARN: không xác định được lesson_id (response shape lạ). Check LingQ web + log.\n");
        $logger('WARN', "lesson_id undetermined source={$res['source_local']}");
    }
    $elapsed = number_format(microtime(true) - $startedAt, 1);
    echo PHP_EOL . "Done in {$elapsed}s. Exit 0." . PHP_EOL;
    echo "Log: " . push_relative_path($logFile, $repoRoot) . PHP_EOL;
    exit(0);
}

// =============================================================================
// BATCH MODE (Phase L) — loop glob, resume dedupe, re-sync 1 lần cuối.
// =============================================================================
echo "Glob: {$batchGlob}" . PHP_EOL;
$folders = push_expand_glob($batchGlob, $repoRoot);
if (empty($folders)) {
    fwrite(STDERR, "ERROR: glob không match folder nào: {$batchGlob}\n");
    $logger('ERROR', "batch glob no match: {$batchGlob}");
    exit(1);
}
$total = count($folders);
echo "Matched {$total} folder | sleep={$sleepSec}s" . ($limit !== null ? " | limit={$limit}" : "") . " | mode=" . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL . PHP_EOL;
$logger('INFO', "batch start glob={$batchGlob} folders={$total} apply=" . ($apply ? 1 : 0) . " limit=" . ($limit === null ? '-' : $limit) . " sleep={$sleepSec} force_update=" . ($forceUpdate ? 1 : 0));

$cnt = [
    'already' => 0, 'missing' => 0, 'text' => 0, 'audio' => 0, 'audio_fail' => 0,
    'patched' => 0, 'errors' => 0, 'processed' => 0, 'dry_plan' => 0,
];
$upserts = [];   // lesson_id => ['source_local'=>, 'audio_url'=>]
$pending = [];   // title_norm => ['source_local'=>, 'audio_url'=>]  (lesson_id chưa rõ từ POST)

$idx = 0;
foreach ($folders as $folderAbs) {
    $idx++;
    if ($limit !== null && $cnt['processed'] >= $limit) {
        echo "[limit {$limit}] reached — dừng process thêm folder." . PHP_EOL;
        break;
    }
    $prefix = "[{$idx}/{$total}] ";
    $res = push_one_folder($folderAbs, $settings, $client, $pushedSet, $repoRoot, $logger, $prefix);

    switch ($res['status']) {
        case 'skip_already': $cnt['already']++; break;
        case 'skip_missing': $cnt['missing']++; break;
        case 'error':        $cnt['errors']++;  break;
        case 'dry':          $cnt['dry_plan']++; $cnt['processed']++; break;  // dry plan-item cũng tính cho --limit preview
        case 'patched':      $cnt['patched']++;  $cnt['processed']++; break;
        case 'pushed_audio': $cnt['audio']++;    $cnt['processed']++; break;
        case 'pushed_text':  $cnt['text']++;     $cnt['processed']++; break;
    }

    if (!empty($res['audio_failed'])) $cnt['audio_fail']++;

    if (in_array($res['status'], ['pushed_audio', 'pushed_text', 'patched'], true)) {
        $sl = $res['source_local'];
        if ($res['lesson_id'] !== '') {
            $upserts[$res['lesson_id']] = ['source_local' => $sl, 'audio_url' => $res['audio_url']];
            $pushedSet[rtrim($sl, '/')] = $res['lesson_id'];   // resume trong cùng run
        } elseif ($res['needs_title_match']) {
            $pending[push_norm($res['title'])] = ['source_local' => $sl, 'audio_url' => $res['audio_url']];
        }
        // Sleep chống rate-limit chỉ sau push thật.
        if ($apply && $sleepSec > 0) usleep((int)round($sleepSec * 1e6));
    }
}

// Re-sync 1 lần cuối + upsert hàng loạt (chỉ apply).
$csvRowsAfter = $csvRowsBefore;
if ($apply) {
    if ($resync) {
        push_run_resync($syncScript, $logger);
        $rowsAfterSync = push_load_csv($csvPath);
        $csvRowsAfter  = count($rowsAfterSync);
        // Resolve pending (POST không trả id) qua title match.
        foreach ($pending as $tnorm => $info) {
            $best = null;
            foreach ($rowsAfterSync as $r) {
                if (push_norm($r['title']) === $tnorm) {
                    if ($best === null || (int)$r['lesson_id'] > (int)$best['lesson_id']) $best = $r;
                }
            }
            if ($best !== null) {
                $upserts[(string)$best['lesson_id']] = $info;
            } else {
                $logger('WARN', "batch: lesson_id unresolved title-norm='{$tnorm}' source={$info['source_local']}");
                fwrite(STDERR, "WARN: không match lesson_id cho '{$info['source_local']}' (title sau re-sync). Check LingQ web.\n");
            }
        }
    } elseif (!empty($pending)) {
        $logger('WARN', "batch: " . count($pending) . " folder thiếu lesson_id nhưng --no-resync → bỏ qua upsert source_local.");
        fwrite(STDERR, "WARN: --no-resync + " . count($pending) . " POST không trả id → source_local chưa set cho các bài đó.\n");
    }
    if (!empty($upserts)) {
        $csvRowsAfter = push_upsert_many($csvPath, $upserts, $logger);
    }
}

$secs = (int)round(microtime(true) - $startedAt);
$elapsedHuman = $secs >= 60 ? (floor($secs / 60) . "m" . str_pad((string)($secs % 60), 2, '0', STR_PAD_LEFT) . "s") : ($secs . "s");
$newCount = max(0, $csvRowsAfter - $csvRowsBefore);

echo PHP_EOL . "=== BATCH SUMMARY ===" . PHP_EOL;
echo "glob:             {$batchGlob}" . PHP_EOL;
echo "total folders:    {$total}" . PHP_EOL;
if (!$apply) {
    echo "dry-run plan:     {$cnt['dry_plan']}   (sẽ push/patch nếu --apply)" . PHP_EOL;
}
echo "already-pushed:   {$cnt['already']}   (skip)" . PHP_EOL;
echo "missing files:    {$cnt['missing']}   (skip — không có *_transcript.md/*_text.md)" . PHP_EOL;
echo "pushed text-only: {$cnt['text']}   (có transcript, không mp3)" . PHP_EOL;
echo "pushed audio:     {$cnt['audio']}" . PHP_EOL;
if ($apply) {
    echo "  ↳ audio FAILED: {$cnt['audio_fail']}   (file không phải mp3 thật: WAV/M4A sai đuôi .mp3 → bài chỉ có text)" . PHP_EOL;
}
echo "patched:          {$cnt['patched']}   (--force-update)" . PHP_EOL;
echo "errors:           {$cnt['errors']}" . PHP_EOL;
echo "elapsed:          {$elapsedHuman}" . PHP_EOL;
if ($apply && $resync) {
    echo "re-sync CSV:      {$csvRowsBefore} → {$csvRowsAfter} rows (new={$newCount})" . PHP_EOL;
} elseif ($apply) {
    echo "CSV upsert:       {$csvRowsBefore} → {$csvRowsAfter} rows (--no-resync; new={$newCount})" . PHP_EOL;
}
echo "log file:         " . push_relative_path($logFile, $repoRoot) . PHP_EOL;
$logger('INFO', "batch done total={$total} audio={$cnt['audio']} text={$cnt['text']} patched={$cnt['patched']} already={$cnt['already']} missing={$cnt['missing']} errors={$cnt['errors']} elapsed={$elapsedHuman}");
exit(0);

// =============================================================================
// Helpers.
// =============================================================================

function push_find_text_file($folderAbs)
{
    $text = glob($folderAbs . DIRECTORY_SEPARATOR . '*_text.md');
    if (!empty($text)) return $text[0];
    $tr = glob($folderAbs . DIRECTORY_SEPARATOR . '*_transcript.md');
    if (!empty($tr)) return $tr[0];
    return null;
}

/**
 * Parse markdown: tách YAML frontmatter (nếu có) + body. Trả
 * ['fm'=>assoc, 'title'=>str, 'text'=>plaintext].
 * - Lesen: frontmatter có chu_de/url/teil. title = chu_de.
 * - Hören transcript (KHÔNG frontmatter): title từ '# Transcript — X.X <title>',
 *   url từ dòng 'Source: <url>'.
 * Body → plaintext: bỏ '#' heading markers, '**'/'*' emphasis; giữ dòng.
 * Strip H1 đầu nếu trùng title (tránh lặp).
 */
function push_parse_markdown($raw)
{
    $raw = str_replace(["\r\n", "\r"], "\n", (string)$raw);
    $fm = [];
    $body = $raw;

    if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $raw, $m)) {
        $fm = push_parse_frontmatter($m[1]);
        $body = $m[2];
    }

    // Title.
    $title = '';
    if (isset($fm['chu_de']) && $fm['chu_de'] !== '') {
        $title = $fm['chu_de'];
    }

    // Hören transcript: '# Transcript — 1.1 ICE 577 nach Köln' + 'Source: <url>'.
    // /u bắt buộc: em/en-dash (—–) là multibyte, char-class byte-wise sẽ cắt hỏng UTF-8.
    if ($title === '' && preg_match('/^#\s*Transcript\s*(?:—|–|-|:)\s*(.+)$/mui', $body, $tm)) {
        $t = trim($tm[1]);
        // Bỏ tiền tố số bài 'X.X ' nếu có.
        $t = preg_replace('/^\d+(?:\.\d+)*\s+/u', '', $t);
        $title = trim($t);
    }
    if ($title === '' && preg_match('/^#\s+(.+)$/m', $body, $hm)) {
        $title = trim($hm[1]);
    }
    if (!isset($fm['url']) && preg_match('/^Source:\s*(\S+)/mi', $body, $sm)) {
        $fm['url'] = trim($sm[1]);
    }

    // Body → plaintext.
    $lines = explode("\n", $body);
    $out = [];
    $titleStripped = false;
    foreach ($lines as $ln) {
        $t = $ln;
        // Bỏ dòng 'Source: ...' (Hören meta) khỏi text bài.
        if (preg_match('/^Source:\s*\S+/i', trim($t))) continue;
        // Heading → bỏ dấu #.
        $isHeading = (bool)preg_match('/^\s*#{1,6}\s+/', $t);
        $t = preg_replace('/^\s*#{1,6}\s+/', '', $t);
        // Emphasis markers (/u tránh cắt giữa byte UTF-8 khi có umlaut quanh marker).
        $t = str_replace(['**', '__'], '', $t);
        $t = preg_replace('/(?<!\w)[*_](?=\S)(.+?)(?<=\S)[*_](?!\w)/u', '$1', $t);
        $tTrim = trim($t);
        // Strip heading đầu nếu trùng title HOẶC là meta '# Transcript — ...' (Hören).
        if ($isHeading && !$titleStripped
            && (($title !== '' && push_norm($tTrim) === push_norm($title)) || preg_match('/^Transcript\b/i', $tTrim))) {
            $titleStripped = true;
            continue;
        }
        $out[] = rtrim($t);
    }
    // Collapse 2+ dòng trống liên tiếp → 1.
    $collapsed = [];
    $prevBlank = false;
    foreach ($out as $l) {
        $blank = (trim($l) === '');
        if ($blank && $prevBlank) continue;
        $collapsed[] = $l;
        $prevBlank = $blank;
    }
    $out = $collapsed;
    // Trim leading/trailing blank lines.
    while (!empty($out) && trim($out[0]) === '') array_shift($out);
    while (!empty($out) && trim(end($out)) === '') array_pop($out);
    $text = implode("\n", $out);

    return ['fm' => $fm, 'title' => trim($title), 'text' => $text];
}

function push_parse_frontmatter($block)
{
    $fm = [];
    foreach (explode("\n", $block) as $ln) {
        if (!preg_match('/^([a-zA-Z0-9_]+):\s*(.*)$/', $ln, $m)) continue;
        $key = $m[1];
        $val = trim($m[2]);
        // Bỏ quote bao.
        if ((strlen($val) >= 2) && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
            $val = substr($val, 1, -1);
        }
        $fm[$key] = $val;
    }
    return $fm;
}

function push_norm($s)
{
    $s = trim((string)$s);
    if (function_exists('mb_strtolower')) $s = mb_strtolower($s, 'UTF-8');
    else $s = strtolower($s);
    return preg_replace('/\s+/', ' ', $s);
}

function push_charlen($s)
{
    return function_exists('mb_strlen') ? mb_strlen((string)$s, 'UTF-8') : strlen((string)$s);
}

function push_substr($s, $start, $len)
{
    return function_exists('mb_substr') ? mb_substr((string)$s, $start, $len, 'UTF-8') : substr((string)$s, $start, $len);
}

function push_basename($p)
{
    return basename(str_replace('\\', '/', (string)$p));
}

function push_source_local($folderAbs, $repoRoot)
{
    $rel = push_relative_path($folderAbs, $repoRoot);
    return rtrim($rel, '/') . '/';
}

function push_columns()
{
    return [
        'lesson_id', 'course_id', 'title', 'language', 'audio_url',
        'words_count', 'unknown_count', 'source_local', 'first_seen', 'last_synced',
    ];
}

function push_load_csv($path)
{
    if (!file_exists($path)) return [];
    $fh = fopen($path, 'r');
    if (!$fh) return [];
    $cols = push_columns();
    $header = fgetcsv($fh);
    if ($header === false) { fclose($fh); return []; }
    if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
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

/**
 * Upload audio cho lesson (K3). Returns:
 *   - string URL (có thể '') khi server CHẤP NHẬN (upload OK).
 *   - false khi upload FAIL (vd HTTP 400 format không hỗ trợ — file WAV/M4A sai đuôi .mp3).
 * Lỗi audio KHÔNG abort — lesson text đã tạo; user có thể --force-update lại.
 */
function push_upload_audio($client, $lessonId, $mp3Path, callable $logger)
{
    echo "Upload audio: " . push_basename($mp3Path) . " ..." . PHP_EOL;
    try {
        $au = $client->uploadLessonAudio($lessonId, $mp3Path);
        $url = isset($au['audio']) ? (string)$au['audio'] : (isset($au['audioUrl']) ? (string)$au['audioUrl'] : '');
        $dur = isset($au['duration']) ? $au['duration'] : '?';
        echo "  Audio OK (duration={$dur}s)" . ($url !== '' ? " — url set" : "") . PHP_EOL;
        $logger('INFO', "audio upload lesson id={$lessonId} duration={$dur} url=" . ($url !== '' ? 'set' : 'none'));
        return $url;
    } catch (Throwable $e) {
        fwrite(STDERR, "WARN: audio upload fail: " . $e->getMessage() . " (lesson text đã tạo; thử --force-update để attach lại).\n");
        $logger('ERROR', "audio upload id={$lessonId} — " . $e->getMessage());
        return false;
    }
}

/**
 * Set source_local (+ optional audio_url) cho lesson_id trong CSV (read-modify-write
 * atomic, BOM, sort). Nếu lesson_id chưa có (re-sync chưa bắt) → tạo row tối thiểu.
 * $audioUrl != '' → ghi đè audio_url. Returns số rows.
 */
function push_upsert_source_local($path, $lessonId, $sourceLocal, callable $logger, $audioUrl = '')
{
    $rows = push_load_csv($path);
    $today = date('Y-m-d');
    if (isset($rows[$lessonId])) {
        $rows[$lessonId]['source_local'] = $sourceLocal;
        if ($audioUrl !== '') $rows[$lessonId]['audio_url'] = $audioUrl;
        if ($rows[$lessonId]['last_synced'] === '') $rows[$lessonId]['last_synced'] = $today;
        if ($rows[$lessonId]['first_seen'] === '') $rows[$lessonId]['first_seen'] = $today;
    } else {
        $rows[$lessonId] = [
            'lesson_id' => (string)$lessonId, 'course_id' => '', 'title' => '',
            'language' => '', 'audio_url' => $audioUrl, 'words_count' => '', 'unknown_count' => '',
            'source_local' => $sourceLocal, 'first_seen' => $today, 'last_synced' => $today,
        ];
    }
    return push_write_csv($path, $rows);
}

/**
 * Phase L: upsert NHIỀU lesson trong 1 lần ghi (batch mode — tránh 344 lần read-modify-write).
 * $upserts = [lesson_id => ['source_local'=>str, 'audio_url'=>str], ...].
 * Returns tổng số rows.
 */
function push_upsert_many($path, array $upserts, callable $logger)
{
    if (empty($upserts)) return count(push_load_csv($path));
    $rows  = push_load_csv($path);
    $today = date('Y-m-d');
    foreach ($upserts as $lessonId => $info) {
        $lessonId = (string)$lessonId;
        $sl = isset($info['source_local']) ? (string)$info['source_local'] : '';
        $au = isset($info['audio_url']) ? (string)$info['audio_url'] : '';
        if (isset($rows[$lessonId])) {
            if ($sl !== '') $rows[$lessonId]['source_local'] = $sl;
            if ($au !== '') $rows[$lessonId]['audio_url'] = $au;
            if ($rows[$lessonId]['last_synced'] === '') $rows[$lessonId]['last_synced'] = $today;
            if ($rows[$lessonId]['first_seen'] === '') $rows[$lessonId]['first_seen'] = $today;
        } else {
            $rows[$lessonId] = [
                'lesson_id' => $lessonId, 'course_id' => '', 'title' => '',
                'language' => '', 'audio_url' => $au, 'words_count' => '', 'unknown_count' => '',
                'source_local' => $sl, 'first_seen' => $today, 'last_synced' => $today,
            ];
        }
    }
    $n = push_write_csv($path, $rows);
    $logger('INFO', "batch upsert " . count($upserts) . " lesson → CSV {$n} rows");
    return $n;
}

/**
 * Atomic write CSV (UTF-8 BOM, sort lesson_id asc numeric). Returns số rows ghi.
 * Dùng chung cho push_upsert_source_local (single) + push_upsert_many (batch).
 */
function push_write_csv($path, array $rows)
{
    $cols = push_columns();
    $tmp = $path . '.tmp';
    $fh = fopen($tmp, 'w');
    if (!$fh) throw new RuntimeException("Cannot open {$tmp}");
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, $cols);
    $keys = array_keys($rows);
    usort($keys, function ($a, $b) {
        $ai = is_numeric($a) ? (int)$a : 0; $bi = is_numeric($b) ? (int)$b : 0;
        if ($ai === $bi) return strcmp((string)$a, (string)$b);
        return $ai <=> $bi;
    });
    $n = 0;
    foreach ($keys as $id) {
        $line = [];
        foreach ($cols as $c) $line[] = isset($rows[$id][$c]) ? (string)$rows[$id][$c] : '';
        fputcsv($fh, $line);
        $n++;
    }
    fflush($fh);
    if (function_exists('fsync')) @fsync($fh);
    fclose($fh);
    if (file_exists($path)) {
        $bak = $path . '.bak';
        if (file_exists($bak)) @unlink($bak);
        if (!@rename($path, $bak)) throw new RuntimeException("Cannot backup {$path}");
        if (!@rename($tmp, $path)) { @rename($bak, $path); throw new RuntimeException("Cannot move tmp → {$path}"); }
        @unlink($bak);
    } else {
        if (!@rename($tmp, $path)) throw new RuntimeException("Cannot move tmp → {$path}");
    }
    return $n;
}

function push_run_resync($syncScript, callable $logger)
{
    echo PHP_EOL . "Re-sync CSV..." . PHP_EOL;
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($syncScript);
    $rc = 0;
    passthru($cmd, $rc);
    $logger('INFO', "re-sync lessons_sync.php exit={$rc}");
    if ($rc !== 0) fwrite(STDERR, "WARN: lessons_sync.php exit {$rc}.\n");
}

function push_relative_path($abs, $base)
{
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($abs, $base, strlen($base)) === 0) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($abs, strlen($base)));
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', $abs);
}

// =============================================================================
// Phase L helpers — resume set, folder resolve, glob expand, per-folder push.
// =============================================================================

/**
 * Build resume set từ rows CSV: [source_local-không-trailing-slash => lesson_id].
 * Dùng cho idempotency check (folder đã push → skip trừ --force-update).
 */
function build_pushed_set(array $rows)
{
    $set = [];
    foreach ($rows as $r) {
        $sl = isset($r['source_local']) ? trim((string)$r['source_local']) : '';
        if ($sl === '') continue;
        $set[rtrim($sl, '/')] = (string)$r['lesson_id'];
    }
    return $set;
}

/**
 * Resolve folder arg → absolute path (thử relative vs repoRoot). Null nếu không phải dir.
 */
function push_resolve_folder($arg, $repoRoot)
{
    $abs = realpath($arg);
    if ($abs !== false && is_dir($abs)) return $abs;
    $try = realpath($repoRoot . DIRECTORY_SEPARATOR . $arg);
    if ($try !== false && is_dir($try)) return $try;
    return null;
}

/**
 * Expand glob → list folder absolute (chỉ dir), sort lexicographic kiểu glob() mặc định
 * (1.1, 1.10, 1.11, 1.2 — KHÔNG numeric; chấp nhận theo spec Phase L). Thử relative vs repoRoot.
 */
function push_expand_glob($pattern, $repoRoot)
{
    $norm = str_replace('\\', '/', (string)$pattern);
    $matches = glob($norm, GLOB_ONLYDIR);
    if (empty($matches)) {
        $alt = rtrim(str_replace('\\', '/', $repoRoot), '/') . '/' . ltrim($norm, '/');
        $matches = glob($alt, GLOB_ONLYDIR);
    }
    if ($matches === false || empty($matches)) return [];
    $out = [];
    foreach ($matches as $m) {     // giữ thứ tự glob() (đã sort sẵn).
        $abs = realpath($m);
        if ($abs !== false && is_dir($abs)) $out[] = $abs;
    }
    return $out;
}

/**
 * Xử lý 1 folder: parse → payload → idempotency → dry-run print | apply POST/PATCH (+audio).
 * KHÔNG re-sync, KHÔNG upsert CSV (caller lo — batch chỉ re-sync 1 lần cuối).
 *
 * $st = settings array (lang/courseId/level/status/defaultTags/apply/forceUpdate/noCourse/batch).
 * $pushedSet = [source_local => lesson_id] (resume). $prefix = nhãn '[i/total] ' cho batch.
 *
 * Returns assoc:
 *   status: skip_missing | error | skip_already | dry | pushed_audio | pushed_text | patched
 *   lesson_id, title, source_local, audio_url, has_audio, needs_title_match, message
 */
function push_one_folder($folderAbs, array $st, $client, array $pushedSet, $repoRoot, callable $logger, $prefix = '')
{
    $batch       = !empty($st['batch']);
    $apply       = !empty($st['apply']);
    $forceUpdate = !empty($st['forceUpdate']);

    $sourceLocal = push_source_local($folderAbs, $repoRoot);
    $result = [
        'status' => '', 'lesson_id' => '', 'title' => '', 'source_local' => $sourceLocal,
        'audio_url' => '', 'has_audio' => false, 'audio_failed' => false,
        'needs_title_match' => false, 'message' => '',
    ];

    // Detect input file + audio.
    $textFile = push_find_text_file($folderAbs);
    if ($textFile === null) {
        echo $prefix . $sourceLocal . " — WARN: không có *_text.md/*_transcript.md → skip." . PHP_EOL;
        $logger('WARN', "skip missing text file source={$sourceLocal}");
        $result['status'] = 'skip_missing';
        return $result;
    }
    $mp3Files = glob($folderAbs . DIRECTORY_SEPARATOR . '*.mp3');
    $hasAudio = !empty($mp3Files);
    $result['has_audio'] = $hasAudio;
    $isHoren  = (stripos(str_replace('\\', '/', $folderAbs), '/horen/') !== false);
    $skill    = $isHoren ? 'Hören' : 'Lesen';

    // Parse markdown.
    $parsed = push_parse_markdown(file_get_contents($textFile));
    $title  = $parsed['title'];
    $text   = $parsed['text'];
    $result['title'] = $title;
    if ($title === '' || $text === '') {
        echo $prefix . $sourceLocal . " — WARN: parse fail (title/text rỗng) → skip." . PHP_EOL;
        $logger('ERROR', "parse empty title/text source={$sourceLocal}");
        $result['status'] = 'error';
        $result['message'] = "parse fail title/text rỗng ({$sourceLocal})";
        return $result;
    }

    // Tags: default + skill + Teil<N>.
    $tags = $st['defaultTags'];
    $tags[] = $skill;
    if (isset($parsed['fm']['teil']) && $parsed['fm']['teil'] !== '') {
        $tags[] = 'Teil' . preg_replace('/\D/', '', (string)$parsed['fm']['teil']);
    }
    $tags = array_values(array_unique($tags));
    $originalUrl = isset($parsed['fm']['url']) ? (string)$parsed['fm']['url'] : '';

    // Build payload.
    $payload = [
        'title' => $title, 'text' => $text, 'status' => $st['status'],
        'level' => $st['level'], 'tags' => $tags,
    ];
    if ($originalUrl !== '')    $payload['original_url'] = $originalUrl;
    if ($st['courseId'] !== '') $payload['collection'] = (int)$st['courseId'];

    // Idempotency (resume set).
    $already = isset($pushedSet[rtrim($sourceLocal, '/')]) ? $pushedSet[rtrim($sourceLocal, '/')] : null;

    // Verbose header (single mode).
    if (!$batch) {
        echo "Folder: {$sourceLocal}" . PHP_EOL;
        echo "Input: " . push_basename($textFile) . " | skill={$skill}" . ($hasAudio ? " | audio: " . push_basename($mp3Files[0]) : "") . PHP_EOL;
        echo PHP_EOL . "Title: {$title}" . PHP_EOL;
        echo "Text:  " . push_charlen($text) . " chars, " . (substr_count($text, "\n") + 1) . " dòng" . PHP_EOL;
        echo "Tags:  [" . implode(', ', $tags) . "]" . PHP_EOL;
        echo "Course: " . ($st['courseId'] !== '' ? $st['courseId'] : "(chưa set lessons_course_id)") . " | level={$st['level']} | status={$st['status']}" . PHP_EOL;
    }

    // Already pushed → skip (trừ --force-update).
    if ($already !== null && !$forceUpdate) {
        if ($batch) {
            echo $prefix . $sourceLocal . " — skip (already-pushed lesson_id={$already})." . PHP_EOL;
        } else {
            echo PHP_EOL . "⚠️  Already pushed (lesson_id={$already}). Dùng --force-update để PATCH." . PHP_EOL;
            if (!$apply) echo "(dry-run) Exit 0." . PHP_EOL;
        }
        $logger('INFO', "skip already-pushed source={$sourceLocal} lesson_id={$already}");
        $result['status']    = 'skip_already';
        $result['lesson_id'] = (string)$already;
        return $result;
    }

    // Dry-run.
    if (!$apply) {
        if ($batch) {
            $act = ($already !== null) ? "PATCH (force-update)" : ($hasAudio ? "PUSH +audio" : "PUSH text-only");
            echo $prefix . $sourceLocal . " — {$act} | \"{$title}\" | " . push_charlen($text) . " chars"
                . ($hasAudio ? " | mp3:" . push_basename($mp3Files[0]) : " | (no mp3)") . PHP_EOL;
        } else {
            echo PHP_EOL . "--- Payload JSON (dry-run, KHÔNG gửi) ---" . PHP_EOL;
            $preview = $payload;
            if (push_charlen($preview['text']) > 500) {
                $preview['text'] = push_substr($preview['text'], 0, 500) . " …(+" . (push_charlen($payload['text']) - 500) . " chars)";
            }
            $json = json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                fwrite(STDERR, "WARN: json_encode fail (" . json_last_error_msg() . ") — text có thể chứa UTF-8 hỏng.\n");
                $json = json_encode($preview, JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
            echo $json . PHP_EOL;
            if ($hasAudio) echo PHP_EOL . "Audio: " . push_basename($mp3Files[0]) . " → sẽ PATCH multipart sau khi tạo lesson (dry-run: KHÔNG upload)." . PHP_EOL;
            echo PHP_EOL . "Dry run. Thêm --apply để POST. Exit 0." . PHP_EOL;
        }
        $logger('INFO', "dry-run plan source={$sourceLocal} action=" . ($already !== null ? 'patch' : 'push'));
        $result['status'] = 'dry';
        return $result;
    }

    // --- APPLY: force-update PATCH path ---
    if ($already !== null && $forceUpdate) {
        $lessonId = (string)$already;
        $patch = ['title' => $title, 'text' => $text, 'tags' => $tags];
        echo $prefix . "PATCH lesson_id={$lessonId} ({$sourceLocal}) ..." . PHP_EOL;
        try {
            $client->updateLesson($lessonId, $patch);
            echo "  PATCH OK." . PHP_EOL;
            $logger('INFO', "PATCH lesson id={$lessonId} source={$sourceLocal}");
        } catch (Throwable $e) {
            fwrite(STDERR, "PATCH FAIL ({$sourceLocal}): " . $e->getMessage() . "\n");
            $logger('ERROR', "PATCH lesson id={$lessonId} source={$sourceLocal} — " . $e->getMessage());
            $result['status']  = 'error';
            $result['message'] = "PATCH fail {$sourceLocal}: " . $e->getMessage();
            return $result;
        }
        if ($hasAudio) {
            $au = push_upload_audio($client, $lessonId, $mp3Files[0], $logger);
            if ($au === false) $result['audio_failed'] = true;
            else $result['audio_url'] = (string)$au;
        }
        $result['status']    = 'patched';
        $result['lesson_id'] = $lessonId;
        return $result;
    }

    // --- APPLY: POST new lesson ---
    echo $prefix . "POST /lessons/ \"{$title}\" ({$sourceLocal}) ..." . PHP_EOL;
    $t0 = microtime(true);
    try {
        $resp = $client->createLesson($payload);
    } catch (Throwable $e) {
        fwrite(STDERR, "POST FAIL ({$sourceLocal}): " . $e->getMessage() . "\n");
        $logger('ERROR', "POST lesson source={$sourceLocal} — " . $e->getMessage());
        $result['status']  = 'error';
        $result['message'] = "POST fail {$sourceLocal}: " . $e->getMessage();
        return $result;
    }
    $ms = (int)round((microtime(true) - $t0) * 1000);
    $lessonId = LingqClient::extractLessonId($resp['body']);
    echo "  HTTP {$resp['http_code']} ({$ms}ms)" . ($lessonId !== '' ? " lesson_id={$lessonId}" : " (id chưa rõ — re-sync match title)") . PHP_EOL;
    $logger('INFO', "POST lesson source={$sourceLocal} HTTP {$resp['http_code']} id=" . ($lessonId !== '' ? $lessonId : '?') . " {$ms}ms");

    $result['lesson_id'] = $lessonId;
    if ($lessonId === '') $result['needs_title_match'] = true;

    if ($hasAudio) {
        if ($lessonId === '') {
            fwrite(STDERR, "WARN ({$sourceLocal}): chưa có lesson_id từ POST → bỏ qua audio. --force-update sau khi sync để attach.\n");
            $logger('WARN', "audio skipped (no lesson_id) source={$sourceLocal}");
            $result['audio_failed'] = true;
        } else {
            $au = push_upload_audio($client, $lessonId, $mp3Files[0], $logger);
            if ($au === false) $result['audio_failed'] = true;
            else $result['audio_url'] = (string)$au;
        }
        $result['status'] = 'pushed_audio';
    } else {
        $result['status'] = 'pushed_text';
    }
    return $result;
}
