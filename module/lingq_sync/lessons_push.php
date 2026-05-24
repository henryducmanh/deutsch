<?php
/**
 * LingQ Lessons Push (Phase K2 text / K3 audio) — push 1 bài từ folder local lên LingQ.
 *
 * Usage (default = DRY-RUN):
 *   C:\php\php74\php.exe module\lingq_sync\lessons_push.php input\html\deutsch-vorbereitung\lesen\1.1\
 *   C:\php\php74\php.exe module\lingq_sync\lessons_push.php <folder> --apply
 *   C:\php\php74\php.exe module\lingq_sync\lessons_push.php <folder> --apply --force-update
 *   C:\php\php74\php.exe module\lingq_sync\lessons_push.php <folder> --apply --no-course
 *
 * Flags:
 *   --apply        : thật sự POST lên LingQ. Mặc định DRY-RUN (in payload).
 *   --dry-run      : explicit dry-run (= default).
 *   --force-update : bài đã push (source_local có trong CSV) → PATCH thay vì skip.
 *   --no-course    : cho phép --apply khi lessons_course_id rỗng (push không collection).
 *   --no-resync    : sau --apply KHÔNG tự re-sync CSV (mặc định re-sync để bắt lesson_id).
 *
 * Input folder:
 *   Lesen:  <folder>/X.X_text.md        (frontmatter: bai/teil/teil_desc/chu_de/url)
 *   Hören:  <folder>/X.X_transcript.md  (+ X.X.mp3 → audio, xử lý K3)
 *
 * Idempotency: source_local (relative folder path) trong data/lingq_lessons.csv.
 *   Đã có → skip (cảnh báo lesson_id). --force-update → PATCH.
 *
 * Exit codes: 0 OK · 1 fatal (config/IO/parse/course-missing/bad args)
 *
 * LƯU Ý K2: shape response POST /lessons/ chưa verify live (không auto-write account).
 *   Code xử lý defensive (dict|list) + re-sync match title để lấy lesson_id.
 *   Audio upload (K3) chưa cài — folder có .mp3 sẽ push TEXT-ONLY + cảnh báo.
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

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply')        { $apply = true; continue; }
    if ($arg === '--dry-run')      { $apply = false; continue; }
    if ($arg === '--force-update') { $forceUpdate = true; continue; }
    if ($arg === '--no-course')    { $noCourse = true; continue; }
    if ($arg === '--no-resync')    { $resync = false; continue; }
    if ($arg === '-h' || $arg === '--help') {
        echo "Usage: php lessons_push.php <folder> [--apply] [--force-update] [--no-course] [--no-resync]\n";
        exit(0);
    }
    if (strpos($arg, '--') === 0) {
        fwrite(STDERR, "Unknown flag: {$arg}\n");
        exit(1);
    }
    if ($folderArg === null) { $folderArg = $arg; continue; }
    fwrite(STDERR, "Chỉ nhận 1 folder. Dư arg: {$arg}\n");
    exit(1);
}

if ($folderArg === null) {
    fwrite(STDERR, "ERROR: cần folder path. Vd: php lessons_push.php input/html/deutsch-vorbereitung/lesen/1.1/\n");
    exit(1);
}

if (!is_dir($logsDir)) @mkdir($logsDir, 0775, true);
$logger = function ($level, $msg) use ($logFile) {
    $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $msg);
    @file_put_contents($logFile, $line, FILE_APPEND);
};

$startedAt = microtime(true);
$modeLabel = $apply ? 'LIVE --apply' : 'DRY-RUN default';
echo "[LingQ Lessons Push] " . date('Y-m-d H:i:s') . " ({$modeLabel})" . PHP_EOL;

// Resolve folder (absolute).
$folderAbs = realpath($folderArg);
if ($folderAbs === false || !is_dir($folderAbs)) {
    // Thử ghép với repoRoot nếu là relative.
    $try = realpath($repoRoot . DIRECTORY_SEPARATOR . $folderArg);
    if ($try !== false && is_dir($try)) {
        $folderAbs = $try;
    } else {
        fwrite(STDERR, "ERROR: folder không tồn tại: {$folderArg}\n");
        $logger('ERROR', "folder not found: {$folderArg}");
        exit(1);
    }
}
$sourceLocal = push_source_local($folderAbs, $repoRoot);
echo "Folder: {$sourceLocal}" . PHP_EOL;
$logger('INFO', "push start folder={$sourceLocal} apply=" . ($apply ? 1 : 0) . " force_update=" . ($forceUpdate ? 1 : 0));

// Config.
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
$lang       = isset($cfg['language']) ? (string)$cfg['language'] : 'de';
$courseId   = isset($cfg['lessons_course_id']) ? (string)$cfg['lessons_course_id'] : '';
$level      = isset($cfg['lessons_level']) ? (int)$cfg['lessons_level'] : 3;
$status     = isset($cfg['lessons_status']) ? (string)$cfg['lessons_status'] : 'private';
$defaultTags= isset($cfg['lessons_default_tags']) && is_array($cfg['lessons_default_tags']) ? $cfg['lessons_default_tags'] : ['DTZ', 'B1'];

// Detect input file + audio.
$textFile = push_find_text_file($folderAbs);
if ($textFile === null) {
    fwrite(STDERR, "ERROR: không tìm thấy *_text.md hoặc *_transcript.md trong folder.\n");
    $logger('ERROR', "no text/transcript file in {$sourceLocal}");
    exit(1);
}
$mp3Files = glob($folderAbs . DIRECTORY_SEPARATOR . '*.mp3');
$hasAudio = !empty($mp3Files);
$isHoren  = (stripos(str_replace('\\', '/', $folderAbs), '/horen/') !== false);
$skill    = $isHoren ? 'Hören' : 'Lesen';

echo "Input: " . push_basename($textFile) . " | skill=" . $skill . ($hasAudio ? " | audio: " . push_basename($mp3Files[0]) . " (upload sau khi tạo lesson)" : "") . PHP_EOL;

// Parse markdown.
$parsed = push_parse_markdown(file_get_contents($textFile));
$title = $parsed['title'];
$text  = $parsed['text'];
if ($title === '' || $text === '') {
    fwrite(STDERR, "ERROR: parse fail — title hoặc text rỗng. Check file.\n");
    $logger('ERROR', "parse empty title/text in {$sourceLocal}");
    exit(1);
}

// Tags: default + skill + Teil<N>.
$tags = $defaultTags;
$tags[] = $skill;
if (isset($parsed['fm']['teil']) && $parsed['fm']['teil'] !== '') {
    $tags[] = 'Teil' . preg_replace('/\D/', '', (string)$parsed['fm']['teil']);
}
$tags = array_values(array_unique($tags));

$originalUrl = isset($parsed['fm']['url']) ? (string)$parsed['fm']['url'] : '';

// Build payload.
$payload = [
    'title'  => $title,
    'text'   => $text,
    'status' => $status,
    'level'  => $level,
    'tags'   => $tags,
];
if ($originalUrl !== '') $payload['original_url'] = $originalUrl;
if ($courseId !== '')    $payload['collection'] = (int)$courseId;

// Idempotency check.
$existingRows = push_load_csv($csvPath);
$already = null;
foreach ($existingRows as $row) {
    if ($row['source_local'] !== '' && rtrim($row['source_local'], '/') === rtrim($sourceLocal, '/')) {
        $already = $row;
        break;
    }
}

echo PHP_EOL . "Title: {$title}" . PHP_EOL;
echo "Text:  " . push_charlen($text) . " chars, " . (substr_count($text, "\n") + 1) . " dòng" . PHP_EOL;
echo "Tags:  [" . implode(', ', $tags) . "]" . PHP_EOL;
echo "Course: " . ($courseId !== '' ? $courseId : "(chưa set lessons_course_id)") . " | level={$level} | status={$status}" . PHP_EOL;

if ($already !== null) {
    if (!$forceUpdate) {
        echo PHP_EOL . "⚠️  Already pushed (lesson_id={$already['lesson_id']}). Dùng --force-update để PATCH." . PHP_EOL;
        if (!$apply) {
            echo "(dry-run) Exit 0." . PHP_EOL;
        }
        $logger('INFO', "skip already-pushed source={$sourceLocal} lesson_id={$already['lesson_id']}");
        exit(0);
    }
    echo PHP_EOL . "→ --force-update: sẽ PATCH lesson_id={$already['lesson_id']}" . PHP_EOL;
}

// Dry-run: print payload JSON.
if (!$apply) {
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
    if ($hasAudio) {
        echo PHP_EOL . "Audio: " . push_basename($mp3Files[0]) . " → sẽ PATCH multipart sau khi tạo lesson (dry-run: KHÔNG upload)." . PHP_EOL;
    }
    echo PHP_EOL . "Dry run. Thêm --apply để POST. Exit 0." . PHP_EOL;
    $logger('INFO', "dry-run payload built source={$sourceLocal}");
    exit(0);
}

// --- apply ---

// Course guard.
if ($courseId === '' && !$noCourse) {
    fwrite(STDERR, PHP_EOL . "ABORT: lessons_course_id chưa set trong config.php.\n");
    fwrite(STDERR, "Hướng dẫn:\n");
    fwrite(STDERR, "  1. Mở LingQ web → tạo 1 Course (Collection) DE để chứa bài, vd 'DTZ Vorbereitung'.\n");
    fwrite(STDERR, "  2. Lấy PK course từ URL (.../library/course/<PK>) hoặc collections/my API.\n");
    fwrite(STDERR, "  3. Paste vào config.php: 'lessons_course_id' => '<PK>'.\n");
    fwrite(STDERR, "  Hoặc chạy với --no-course để push không gắn collection (bài sẽ rời).\n");
    $logger('ERROR', "apply abort: lessons_course_id empty (no --no-course)");
    exit(1);
}

try {
    $client = new LingqClient($cfg, $logger);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

$sleepMs = (int)$cfg['sleep_ms'];

// --force-update path → PATCH.
if ($already !== null && $forceUpdate) {
    $lessonId = (string)$already['lesson_id'];
    $patch = ['title' => $title, 'text' => $text, 'tags' => $tags];
    echo PHP_EOL . "PATCH lesson_id={$lessonId} ..." . PHP_EOL;
    try {
        $client->updateLesson($lessonId, $patch);
        echo "  PATCH OK." . PHP_EOL;
        $logger('INFO', "PATCH lesson id={$lessonId} source={$sourceLocal}");
    } catch (Throwable $e) {
        fwrite(STDERR, "PATCH FAIL: " . $e->getMessage() . "\n");
        $logger('ERROR', "PATCH lesson id={$lessonId} — " . $e->getMessage());
        exit(1);
    }
    // K3 — re-upload audio nếu folder có mp3.
    $fuAudioUrl = '';
    if ($hasAudio) {
        $fuAudioUrl = push_upload_audio($client, $lessonId, $mp3Files[0], $logger);
    }
    if ($resync) push_run_resync($syncScript, $logger);
    // Upsert SAU re-sync để audio_url vừa upload không bị sync (index lag) ghi đè rỗng.
    push_upsert_source_local($csvPath, $lessonId, $sourceLocal, $logger, $fuAudioUrl);
    $elapsed = number_format(microtime(true) - $startedAt, 1);
    echo PHP_EOL . "Done in {$elapsed}s. Exit 0." . PHP_EOL;
    exit(0);
}

// POST new lesson.
echo PHP_EOL . "POST /lessons/ ..." . PHP_EOL;
$start = microtime(true);
try {
    $resp = $client->createLesson($payload);
} catch (Throwable $e) {
    fwrite(STDERR, "POST FAIL: " . $e->getMessage() . "\n");
    $logger('ERROR', "POST lesson source={$sourceLocal} — " . $e->getMessage());
    exit(1);
}
$ms = (int)round((microtime(true) - $start) * 1000);
$lessonId = LingqClient::extractLessonId($resp['body']);
echo "  HTTP {$resp['http_code']} ({$ms}ms)" . ($lessonId !== '' ? " lesson_id={$lessonId}" : " (id chưa rõ từ response — sẽ re-sync match title)") . PHP_EOL;
$logger('INFO', "POST lesson source={$sourceLocal} HTTP {$resp['http_code']} id=" . ($lessonId !== '' ? $lessonId : '?') . " {$ms}ms");

// K3 — audio upload (multipart PATCH) nếu folder có mp3. Cần lessonId từ POST.
$serverAudioUrl = '';
if ($hasAudio) {
    if ($lessonId === '') {
        fwrite(STDERR, "WARN: chưa có lesson_id từ POST → bỏ qua audio. Chạy lại với --force-update sau khi sync để attach.\n");
    } else {
        $serverAudioUrl = push_upload_audio($client, $lessonId, $mp3Files[0], $logger);
    }
}

// Re-sync để bắt lesson mới + (nếu id chưa rõ) match theo title.
if ($resync) {
    push_run_resync($syncScript, $logger);
    // Reload CSV; nếu chưa có lesson_id, match newest row trùng title.
    $rows = push_load_csv($csvPath);
    if ($lessonId === '') {
        $best = null;
        foreach ($rows as $r) {
            if (push_norm($r['title']) === push_norm($title)) {
                if ($best === null || (int)$r['lesson_id'] > (int)$best['lesson_id']) $best = $r;
            }
        }
        if ($best !== null) {
            $lessonId = (string)$best['lesson_id'];
            echo "  Matched via re-sync: lesson_id={$lessonId}" . PHP_EOL;
        }
    }
}

// Upsert source_local vào CSV cho lesson_id.
if ($lessonId !== '') {
    $n = push_upsert_source_local($csvPath, $lessonId, $sourceLocal, $logger, $serverAudioUrl);
    echo "  CSV: set source_local" . ($serverAudioUrl !== '' ? " + audio_url" : "") . " cho lesson_id={$lessonId} ({$n} rows total)." . PHP_EOL;
    echo PHP_EOL . "✅ Pushed. Xem: https://www.lingq.com/learn/{$lang}/web/reader/{$lessonId}" . PHP_EOL;
    $logger('INFO', "pushed source={$sourceLocal} lesson_id={$lessonId}");
} else {
    fwrite(STDERR, "WARN: không xác định được lesson_id (response shape lạ). Check LingQ web + log.\n");
    $logger('WARN', "lesson_id undetermined source={$sourceLocal} raw=" . substr((string)$resp['raw'], 0, 300));
}

$elapsed = number_format(microtime(true) - $startedAt, 1);
echo PHP_EOL . "Done in {$elapsed}s. Exit 0." . PHP_EOL;
echo "Log: " . push_relative_path($logFile, $repoRoot) . PHP_EOL;
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
 * Upload audio cho lesson (K3). Returns server audio URL ('' nếu fail/không có).
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
        return '';
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
