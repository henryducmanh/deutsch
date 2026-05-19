<?php
/**
 * LingQ Push — diff lingq_target.csv vs lingq_cards.csv → POST/PATCH/DELETE.
 *
 * Phase D step 2 (manual one-time wipe + push) và step 3 (daily incremental).
 *
 * Usage (default = dry-run):
 *   php push.php
 *   php push.php --apply
 *   php push.php --apply --limit=1 --skip-delete
 *   php push.php --apply --confirm-delete=2728 --force-delete-all
 *   php push.php --apply --auto-confirm           (cron mode — threshold 20%)
 *   php push.php --apply --refresh                (auto chạy sync.php sau khi xong)
 *
 * Flags:
 *   --apply              : thật sự gọi API. Mặc định dry-run.
 *   --dry-run            : explicit dry-run (cùng default).
 *   --limit=N            : cap mỗi phase (CREATE/UPDATE/DELETE) tối đa N hành động.
 *                          Hữu ích cho test single-card.
 *   --skip-create        : bỏ qua phase CREATE
 *   --skip-update        : bỏ qua phase UPDATE
 *   --skip-delete        : bỏ qua phase DELETE
 *   --confirm-delete=N   : MANUAL mode bắt buộc match exact số DELETE planned.
 *   --force-delete-all   : MANUAL mode bypass threshold (manual_max_delete_pct).
 *   --auto-confirm       : CRON mode — skip interactive, dùng threshold thấp hơn
 *                          (auto_max_delete_pct + auto_max_delete_abs).
 *   --refresh            : sau apply, exec `php sync.php` để refresh snapshot.
 *                          Mặc định OFF (orchestrator cron.bat tự sync ở step 4).
 *   --no-lock            : (advanced) bỏ qua lock file check. KHÔNG khuyến nghị.
 *
 * Diff key: strtolower(trim(term)).
 *   target only          → CREATE (POST với status=1 từ target)
 *   both, fragment/hint/tags khác → UPDATE (PATCH các field khác — KHÔNG đụng status)
 *   snapshot only        → DELETE (DELETE bằng lingq_id từ snapshot)
 *
 * Backup: copy data/lingq_cards.csv → data/lingq_cards_backup_<ts>.csv TRƯỚC khi DELETE.
 *
 * Exit codes:
 *   0 — OK (kể cả khi có row fail nhưng tiếp tục)
 *   1 — fatal: config/IO/threshold abort/lock conflict
 */

declare(strict_types=0);

require_once __DIR__ . '/lingq_client.php';
require_once __DIR__ . '/notes_builder.php';

// -----------------------------------------------------------------------------
// Args.
// -----------------------------------------------------------------------------

$moduleDir   = __DIR__;
$repoRoot    = realpath(__DIR__ . '/../..');
$targetPath  = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'lingq_target.csv';
$snapshotPath= $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'lingq_cards.csv';
$logsDir     = $moduleDir . DIRECTORY_SEPARATOR . 'logs';
$logFile     = $logsDir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
$configPath  = $moduleDir . DIRECTORY_SEPARATOR . 'config.php';
$lockPath    = $repoRoot . DIRECTORY_SEPARATOR . '.ai-locks' . DIRECTORY_SEPARATOR . 'lingq_push_running.lock';
$syncScript  = $moduleDir . DIRECTORY_SEPARATOR . 'sync.php';

$apply = false;
$limit = null;
$skipCreate = false;
$skipUpdate = false;
$skipDelete = false;
$confirmDelete = null;
$forceDeleteAll = false;
$autoConfirm = false;
$refresh = false;
$useLock = true;
$forceOverwriteNotes = false;   // Phase J — override user_text trong notes (bypass merge).

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') { $apply = true; continue; }
    if ($arg === '--dry-run') { $apply = false; continue; }
    if ($arg === '--skip-create') { $skipCreate = true; continue; }
    if ($arg === '--skip-update') { $skipUpdate = true; continue; }
    if ($arg === '--skip-delete') { $skipDelete = true; continue; }
    if ($arg === '--force-delete-all') { $forceDeleteAll = true; continue; }
    if ($arg === '--auto-confirm') { $autoConfirm = true; continue; }
    if ($arg === '--refresh') { $refresh = true; continue; }
    if ($arg === '--no-lock') { $useLock = false; continue; }
    if ($arg === '--force-overwrite-notes') { $forceOverwriteNotes = true; continue; }
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) { $limit = (int)$m[1]; continue; }
    if (preg_match('/^--confirm-delete=(\d+)$/', $arg, $m)) { $confirmDelete = (int)$m[1]; continue; }
    if ($arg === '-h' || $arg === '--help') {
        echo file_get_contents(__FILE__) ? get_help() : "see source comments";
        exit(0);
    }
    fwrite(STDERR, "Unknown arg: {$arg}\n");
    exit(1);
}

if (!is_dir($logsDir)) @mkdir($logsDir, 0775, true);

$logger = function ($level, $msg) use ($logFile) {
    $line = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $level, $msg);
    @file_put_contents($logFile, $line, FILE_APPEND);
};

$startedAt = microtime(true);
$modeLabel = $apply ? 'LIVE' : '--dry-run default';
echo "[LingQ Push] " . date('Y-m-d H:i:s') . " ({$modeLabel})" . PHP_EOL;
$logger('INFO', "push start apply=" . ($apply ? 1 : 0) . " auto_confirm=" . ($autoConfirm ? 1 : 0) . " limit=" . ($limit === null ? 'none' : $limit));

// -----------------------------------------------------------------------------
// Lock (running lock — chống race với manual + cron).
// -----------------------------------------------------------------------------

$lockAcquired = false;
if ($useLock) {
    if (file_exists($lockPath)) {
        $age = time() - filemtime($lockPath);
        if ($age < 30 * 60) {
            fwrite(STDERR, "ERROR: Another push in progress (lock age=" . ($age) . "s). Lock: {$lockPath}\n");
            fwrite(STDERR, "Nếu chắc chắn không có process khác — xoá lock file thủ công.\n");
            $logger('ERROR', "lock conflict age={$age}s");
            exit(1);
        }
        // Stale lock — overwrite.
        $logger('WARN', "stale lock found age={$age}s — overwriting");
    }
    @file_put_contents($lockPath, "push.php pid=" . getmypid() . " started=" . date('c') . "\n");
    $lockAcquired = true;
    register_shutdown_function(function () use ($lockPath) {
        if (file_exists($lockPath)) @unlink($lockPath);
    });
}

// -----------------------------------------------------------------------------
// Config.
// -----------------------------------------------------------------------------

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

$thresholds = isset($cfg['push_thresholds']) && is_array($cfg['push_thresholds']) ? $cfg['push_thresholds'] : [];
$th_manual_pct  = isset($thresholds['manual_max_delete_pct']) ? (int)$thresholds['manual_max_delete_pct'] : 80;
$th_auto_pct    = isset($thresholds['auto_max_delete_pct'])   ? (int)$thresholds['auto_max_delete_pct']   : 20;
$th_auto_abs    = isset($thresholds['auto_max_delete_abs'])   ? (int)$thresholds['auto_max_delete_abs']   : 50;

$hintLocale = isset($cfg['hint_locale']) ? (string)$cfg['hint_locale'] : 'vi';

// -----------------------------------------------------------------------------
// Read target + snapshot.
// -----------------------------------------------------------------------------

if (!file_exists($targetPath)) {
    fwrite(STDERR, "ERROR: " . relative_path($targetPath, $repoRoot) . " không tồn tại. Chạy update_local.php trước.\n");
    $logger('ERROR', 'target missing');
    exit(1);
}
$target = read_csv_11col($targetPath, $logger);
echo "Read target: " . relative_path($targetPath, $repoRoot) . " (" . count($target) . " rows)" . PHP_EOL;

$snapshot = [];
if (file_exists($snapshotPath)) {
    $snapshot = read_csv_11col($snapshotPath, $logger);
}
echo "Read snapshot: " . relative_path($snapshotPath, $repoRoot) . " (" . count($snapshot) . " rows)" . PHP_EOL;
$logger('INFO', "loaded target=" . count($target) . " snapshot=" . count($snapshot));

// -----------------------------------------------------------------------------
// Build diff bằng term_lower.
// -----------------------------------------------------------------------------

$targetByKey = [];
foreach ($target as $row) {
    $k = key_of($row['term']);
    if ($k === '') continue;
    // Last write wins nếu trùng (vocab_master không nên có duplicate, nhưng phòng hờ).
    $targetByKey[$k] = $row;
}
$snapshotByKey = [];
foreach ($snapshot as $row) {
    $k = key_of($row['term']);
    if ($k === '') continue;
    $snapshotByKey[$k] = $row;
}

$creates = [];   // target rows chưa có trên snapshot
$updates = [];   // ['target'=>..., 'snapshot'=>..., 'patch'=>{fragment?,hint?,tags?}]
$deletes = [];   // snapshot rows không có trong target

foreach ($targetByKey as $k => $trow) {
    if (!isset($snapshotByKey[$k])) {
        $creates[] = $trow;
        continue;
    }
    $srow = $snapshotByKey[$k];
    $patch = diff_for_patch($trow, $srow, $hintLocale, $forceOverwriteNotes);
    if (!empty($patch)) {
        $updates[] = ['target' => $trow, 'snapshot' => $srow, 'patch' => $patch];
    }
}
foreach ($snapshotByKey as $k => $srow) {
    if (!isset($targetByKey[$k])) {
        $deletes[] = $srow;
    }
}

$nC = count($creates);
$nU = count($updates);
$nD = count($deletes);
$snapCount = count($snapshot);
$delPct = $snapCount > 0 ? round($nD * 100.0 / $snapCount, 1) : 0.0;

// Phase J — break down UPDATE diff theo field.
$nFragChanged = 0;
$nHintChanged = 0;
$nTagsChanged = 0;
$nNotesChanged = 0;
foreach ($updates as $u) {
    if (isset($u['patch']['fragment'])) $nFragChanged++;
    if (isset($u['patch']['hints']))    $nHintChanged++;
    if (isset($u['patch']['tags']))     $nTagsChanged++;
    if (isset($u['patch']['notes']))    $nNotesChanged++;
}

echo PHP_EOL . "Plan:" . PHP_EOL;
printf("  CREATE: %4d   (target only — sẽ POST)\n", $nC);
printf("  UPDATE: %4d   (cả 2 có, field khác → PATCH giữ status)\n", $nU);
if ($nU > 0) {
    printf("    Of which notes-changed=%d, fragment-changed=%d, hint-changed=%d, tags-changed=%d\n",
        $nNotesChanged, $nFragChanged, $nHintChanged, $nTagsChanged);
}
printf("  DELETE: %4d   %s%% snapshot → sẽ XOÁ\n", $nD, number_format($delPct, 1));

if ($skipCreate) echo "  (--skip-create)" . PHP_EOL;
if ($skipUpdate) echo "  (--skip-update)" . PHP_EOL;
if ($skipDelete) echo "  (--skip-delete)" . PHP_EOL;
if ($limit !== null) echo "  (--limit={$limit} per phase)" . PHP_EOL;
if ($forceOverwriteNotes) echo "  (--force-overwrite-notes — bypass merge)" . PHP_EOL;

$logger('INFO', "plan create={$nC} update={$nU} (notes={$nNotesChanged} frag={$nFragChanged} hint={$nHintChanged} tags={$nTagsChanged}) delete={$nD} delete_pct={$delPct}%");

// -----------------------------------------------------------------------------
// Safety checks (chỉ enforce khi --apply).
// -----------------------------------------------------------------------------

$plannedDelete = $skipDelete ? 0 : ($limit !== null ? min($nD, $limit) : $nD);

if (!$apply) {
    echo PHP_EOL;
    // Trong dry-run, vẫn in warnings để user thấy trước.
    if ($nD > 0 && $delPct > $th_manual_pct) {
        echo "⚠️  MANUAL threshold abort: DELETE > {$th_manual_pct}% → cần --force-delete-all" . PHP_EOL;
    }
    if ($nD > 0) {
        echo "⚠️  Manual mode cần --confirm-delete={$nD} để xác nhận số chính xác" . PHP_EOL;
    }
    if ($autoConfirm) {
        // Hiển thị check cron threshold trong dry-run + --auto-confirm để user test xem
        // production cron có pass không.
        $autoPctExceed = $delPct > $th_auto_pct;
        $autoAbsExceed = $nD > $th_auto_abs;
        if ($autoPctExceed || $autoAbsExceed) {
            $reason = [];
            if ($autoPctExceed) $reason[] = "{$delPct}% > {$th_auto_pct}%";
            if ($autoAbsExceed) $reason[] = "{$nD} > {$th_auto_abs} abs";
            echo "⚠️  CRON threshold WOULD abort: DELETE " . implode(' AND ', $reason) . PHP_EOL;
        }
    }
    echo PHP_EOL . "Dry run only. Exit 0." . PHP_EOL;
    $logger('INFO', "dry-run done");
    exit(0);
}

// --- apply mode below ---

// Phase J — --force-overwrite-notes cần explicit confirm (sẽ override user_text).
if ($forceOverwriteNotes && $nNotesChanged > 0) {
    if ($autoConfirm) {
        $msg = "--force-overwrite-notes KHÔNG tương thích với --auto-confirm (cron mode). Abort.";
        fwrite(STDERR, "ABORT: {$msg}\n");
        $logger('ERROR', $msg);
        exit(1);
    }
    echo PHP_EOL . "⚠️  --force-overwrite-notes sẽ ĐÈ {$nNotesChanged} card notes (bao gồm user_text)." . PHP_EOL;
    echo "Gõ chính xác OVERWRITE-NOTES rồi Enter để xác nhận: ";
    $line = trim((string)fgets(STDIN));
    if ($line !== 'OVERWRITE-NOTES') {
        fwrite(STDERR, "ABORT: confirm string mismatch (got '{$line}', need 'OVERWRITE-NOTES').\n");
        $logger('ERROR', "force-overwrite-notes confirm mismatch");
        exit(1);
    }
    $logger('INFO', "force-overwrite-notes confirmed for {$nNotesChanged} cards");
}

if ($plannedDelete > 0) {
    if ($autoConfirm) {
        // Cron mode: stricter, abort hẳn.
        $autoPctExceed = $delPct > $th_auto_pct;
        $autoAbsExceed = $plannedDelete > $th_auto_abs;
        if ($autoPctExceed || $autoAbsExceed) {
            $reason = [];
            if ($autoPctExceed) $reason[] = "{$delPct}% > {$th_auto_pct}% (cron pct)";
            if ($autoAbsExceed) $reason[] = "{$plannedDelete} > {$th_auto_abs} abs (cron abs)";
            $msg = "DELETE " . implode(' AND ', $reason) . " — cron auto-confirm abort.";
            fwrite(STDERR, "ABORT: {$msg}\n");
            $logger('ERROR', "auto-confirm abort: {$msg}");
            exit(1);
        }
    } else {
        // Manual mode.
        if ($delPct > $th_manual_pct && !$forceDeleteAll) {
            $msg = "DELETE {$delPct}% > {$th_manual_pct}% — manual mode cần --force-delete-all để xác nhận.";
            fwrite(STDERR, "ABORT: {$msg}\n");
            $logger('ERROR', "manual threshold abort: {$msg}");
            exit(1);
        }
        if ($confirmDelete === null) {
            $msg = "Manual mode --apply với DELETE>0 cần --confirm-delete={$plannedDelete} để xác nhận số.";
            fwrite(STDERR, "ABORT: {$msg}\n");
            $logger('ERROR', "manual missing --confirm-delete: {$msg}");
            exit(1);
        }
        if ($confirmDelete !== $plannedDelete) {
            $msg = "--confirm-delete={$confirmDelete} != planned {$plannedDelete}. Abort.";
            fwrite(STDERR, "ABORT: {$msg}\n");
            $logger('ERROR', "confirm-delete mismatch: got={$confirmDelete} expected={$plannedDelete}");
            exit(1);
        }
    }
}

// -----------------------------------------------------------------------------
// Backup snapshot before DELETE.
// -----------------------------------------------------------------------------

if (!$skipDelete && $plannedDelete > 0 && file_exists($snapshotPath)) {
    $ts = date('Y-m-d_His');
    $backupPath = $repoRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . "lingq_cards_backup_{$ts}.csv";
    if (!@copy($snapshotPath, $backupPath)) {
        fwrite(STDERR, "ERROR: không backup được " . relative_path($snapshotPath, $repoRoot) . " → " . relative_path($backupPath, $repoRoot) . "\n");
        $logger('ERROR', "backup failed snapshot → {$backupPath}");
        exit(1);
    }
    echo "Backup: " . relative_path($backupPath, $repoRoot) . " (" . count($snapshot) . " rows)" . PHP_EOL;
    $logger('INFO', "backup snapshot → " . relative_path($backupPath, $repoRoot));
}

echo PHP_EOL;

// -----------------------------------------------------------------------------
// Client.
// -----------------------------------------------------------------------------

try {
    $client = new LingqClient($cfg, $logger);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    $logger('ERROR', $e->getMessage());
    exit(1);
}

$sleepMs = (int)$cfg['sleep_ms'];

$delOk = $delFail = 0;
$delFailList = [];
$createOk = $createFail = 0;
$createFailList = [];
$updateOk = $updateFail = 0;
$updateFailList = [];

// -----------------------------------------------------------------------------
// DELETE phase.
// -----------------------------------------------------------------------------

if (!$skipDelete && count($deletes) > 0) {
    $todo = $limit !== null ? array_slice($deletes, 0, $limit) : $deletes;
    echo "DELETE " . count($todo) . " entries..." . PHP_EOL;
    foreach ($todo as $srow) {
        $pk = (string)$srow['lingq_id'];
        if ($pk === '') {
            $logger('WARN', "DELETE skip — empty lingq_id for term=" . $srow['term']);
            $delFail++;
            $delFailList[] = $srow['term'];
            continue;
        }
        $start = microtime(true);
        try {
            $resp = $client->deleteCard($pk);
            $ms = (int)round((microtime(true) - $start) * 1000);
            if ($resp['http_code'] === 404) {
                $logger('WARN', "DELETE pk={$pk} HTTP 404 (already deleted?) — skip");
                echo "  DELETE pk={$pk} ... HTTP 404 (already gone, {$ms}ms)" . PHP_EOL;
                $delOk++;  // treat as success — desired state already achieved
            } else {
                $logger('INFO', "DELETE pk={$pk} HTTP {$resp['http_code']} {$ms}ms");
                echo "  DELETE pk={$pk} ... HTTP {$resp['http_code']} OK ({$ms}ms)" . PHP_EOL;
                $delOk++;
            }
        } catch (Throwable $e) {
            $delFail++;
            $delFailList[] = $srow['term'];
            $logger('ERROR', "DELETE pk={$pk} term=" . $srow['term'] . " — " . $e->getMessage());
            echo "  DELETE pk={$pk} ... FAIL " . $e->getMessage() . PHP_EOL;
        }
        $client->sleepMs($sleepMs);
    }
    echo "  DELETE: {$delOk} OK / {$delFail} fail" . PHP_EOL . PHP_EOL;
} else {
    if ($skipDelete) echo "DELETE: skipped (--skip-delete)" . PHP_EOL . PHP_EOL;
}

// -----------------------------------------------------------------------------
// CREATE phase.
// -----------------------------------------------------------------------------

if (!$skipCreate && count($creates) > 0) {
    $todo = $limit !== null ? array_slice($creates, 0, $limit) : $creates;
    echo "CREATE " . count($todo) . " entries..." . PHP_EOL;
    foreach ($todo as $trow) {
        $payload = build_post_payload($trow, $hintLocale);
        $start = microtime(true);
        try {
            $resp = $client->createCard($payload);
            $ms = (int)round((microtime(true) - $start) * 1000);
            $pk = isset($resp['pk']) ? $resp['pk'] : '?';
            $logger('INFO', "POST term=" . $trow['term'] . " HTTP 201 pk={$pk} {$ms}ms");
            echo "  POST term=" . $trow['term'] . " ... HTTP 201 pk={$pk} ({$ms}ms)" . PHP_EOL;
            $createOk++;
        } catch (Throwable $e) {
            $createFail++;
            $createFailList[] = $trow['term'];
            $logger('ERROR', "POST term=" . $trow['term'] . " — " . $e->getMessage());
            echo "  POST term=" . $trow['term'] . " ... FAIL " . $e->getMessage() . PHP_EOL;
        }
        $client->sleepMs($sleepMs);
    }
    echo "  CREATE: {$createOk} OK / {$createFail} fail" . PHP_EOL . PHP_EOL;
} else {
    if ($skipCreate) echo "CREATE: skipped (--skip-create)" . PHP_EOL . PHP_EOL;
}

// -----------------------------------------------------------------------------
// UPDATE phase (PATCH — KHÔNG đụng status).
// -----------------------------------------------------------------------------

if (!$skipUpdate && count($updates) > 0) {
    $todo = $limit !== null ? array_slice($updates, 0, $limit) : $updates;
    echo "UPDATE " . count($todo) . " entries..." . PHP_EOL;
    foreach ($todo as $u) {
        $trow  = $u['target'];
        $srow  = $u['snapshot'];
        $patch = $u['patch'];   // ĐÃ KHÔNG include status (xem diff_for_patch)
        $pk    = (string)$srow['lingq_id'];
        if ($pk === '') {
            $logger('WARN', "PATCH skip — empty lingq_id for term=" . $trow['term']);
            $updateFail++;
            $updateFailList[] = $trow['term'];
            continue;
        }
        $start = microtime(true);
        try {
            $client->updateCard($pk, $patch);
            $ms = (int)round((microtime(true) - $start) * 1000);
            $diffKeys = implode(',', array_keys($patch));
            $logger('INFO', "PATCH pk={$pk} term=" . $trow['term'] . " diff={$diffKeys} {$ms}ms");
            echo "  PATCH pk={$pk} term=" . $trow['term'] . " ... HTTP 200 diff=[{$diffKeys}] ({$ms}ms)" . PHP_EOL;
            $updateOk++;
        } catch (Throwable $e) {
            $updateFail++;
            $updateFailList[] = $trow['term'];
            $logger('ERROR', "PATCH pk={$pk} term=" . $trow['term'] . " — " . $e->getMessage());
            echo "  PATCH pk={$pk} term=" . $trow['term'] . " ... FAIL " . $e->getMessage() . PHP_EOL;
        }
        $client->sleepMs($sleepMs);
    }
    echo "  UPDATE: {$updateOk} OK / {$updateFail} fail" . PHP_EOL . PHP_EOL;
} else {
    if ($skipUpdate) echo "UPDATE: skipped (--skip-update)" . PHP_EOL . PHP_EOL;
}

// -----------------------------------------------------------------------------
// Summary.
// -----------------------------------------------------------------------------

$summary = sprintf(
    "DELETE: %d OK / %d fail%s. CREATE: %d OK / %d fail%s. UPDATE: %d OK / %d fail%s.",
    $delOk, $delFail, $delFail > 0 ? ' (' . implode(',', array_slice($delFailList, 0, 5)) . ')' : '',
    $createOk, $createFail, $createFail > 0 ? ' (' . implode(',', array_slice($createFailList, 0, 5)) . ')' : '',
    $updateOk, $updateFail, $updateFail > 0 ? ' (' . implode(',', array_slice($updateFailList, 0, 5)) . ')' : ''
);
$logger('INFO', "SUMMARY {$summary}");

// -----------------------------------------------------------------------------
// Refresh snapshot (optional).
// -----------------------------------------------------------------------------

if ($refresh) {
    echo "Refreshing snapshot..." . PHP_EOL;
    $phpBin = PHP_BINARY;
    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($syncScript);
    echo "  → Calling sync.php ..." . PHP_EOL;
    $rc = 0;
    passthru($cmd, $rc);
    $logger('INFO', "refresh sync.php exit={$rc}");
    if ($rc !== 0) {
        fwrite(STDERR, "WARN: sync.php exited with {$rc}. Push complete nhưng snapshot có thể stale.\n");
    }
}

$elapsed = number_format(microtime(true) - $startedAt, 1);
echo PHP_EOL . "Done in {$elapsed}s. Exit 0." . PHP_EOL;
echo "Log: " . relative_path($logFile, $repoRoot) . PHP_EOL;

exit(0);

// =============================================================================
// Helpers.
// =============================================================================

function get_help()
{
    return "Usage: php push.php [--apply] [--limit=N] [--skip-create|--skip-update|--skip-delete]\n"
         . "                    [--confirm-delete=N] [--force-delete-all] [--auto-confirm]\n"
         . "                    [--force-overwrite-notes] [--refresh] [--no-lock]\n"
         . "\n"
         . "Phase J flag:\n"
         . "  --force-overwrite-notes  Bypass merge (đè user_text trong notes).\n"
         . "                           Bắt buộc STDIN confirm 'OVERWRITE-NOTES'.\n";
}

function key_of($term)
{
    return strtolower(trim((string)$term));
}

/**
 * 12 cột v2 (Phase J) — lingq_cards.csv + lingq_target.csv schema mới.
 */
function csv_columns_12()
{
    return [
        'lingq_id','term','fragment','hint','status','extended_status',
        'tags','importance','last_studied_correct','first_seen','last_synced','notes',
    ];
}

/**
 * 11 cột v1 — pre-Phase-J. Dùng để backward-compat khi snapshot chưa được
 * sync.php upgrade (vd manual chạy push trước sync).
 */
function csv_columns_11()
{
    return [
        'lingq_id','term','fragment','hint','status','extended_status',
        'tags','importance','last_studied_correct','first_seen','last_synced',
    ];
}

/**
 * Read target/snapshot CSV. UTF-8 BOM aware. Phase J: chấp nhận v1 (11 cột)
 * hoặc v2 (12 cột); v1 bootstrap notes=''. Un-escape '\n' trong fragment + notes.
 */
function read_csv_11col($path, callable $logger)
{
    if (!file_exists($path)) return [];
    $fh = fopen($path, 'r');
    if (!$fh) return [];

    $colsV2 = csv_columns_12();
    $colsV1 = csv_columns_11();
    $header = fgetcsv($fh);
    if ($header === false) { fclose($fh); return []; }
    if (isset($header[0])) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }
    $isV2 = ($header === $colsV2);
    $isV1 = ($header === $colsV1);
    if (!$isV1 && !$isV2) {
        $logger('WARN', "header mismatch trong {$path} — got=" . implode(',', $header));
        fclose($fh);
        return [];
    }
    if ($isV1) {
        $logger('WARN', "Reading v1(11) CSV {$path} — notes col bootstrap empty (run sync.php to upgrade).");
    }

    $cols = $isV2 ? $colsV2 : $colsV1;
    $out = [];
    while (($r = fgetcsv($fh)) !== false) {
        if (count($r) !== count($cols)) continue;
        $row = array_combine($cols, $r);
        $row['fragment'] = str_replace('\\n', "\n", $row['fragment']);
        if ($isV1) {
            $row['notes'] = '';
        } else {
            $row['notes'] = str_replace('\\n', "\n", isset($row['notes']) ? $row['notes'] : '');
        }
        $out[] = $row;
    }
    fclose($fh);
    return $out;
}

/**
 * Diff giữa target row và snapshot row → trả patch array (chỉ field cần PATCH).
 * KHÔNG bao giờ include status / extended_status (status preserve từ snapshot).
 * Comparison normalize:
 *   - fragment: trim, equality string thuần.
 *   - hint:     trim, equality string thuần (cả 2 CSV đều plain string).
 *   - tags:     split ';' → trim → sort → compare set.
 * Nếu tag set differ → patch.tags = tags array (parsed từ target).
 *
 * Phase F: khi hint differ, payload emit `hints` array of {text, locale}
 *          (KHÔNG còn `hint` singular). target.hint trim='' → hints=[] (clear).
 *
 * Phase J: notes diff. target.notes là enriched block (marker + sections).
 *          merge_notes_for_patch ghép user_text (nếu có) + target → final value.
 *          So sánh final value vs snapshot.notes — khác → patch['notes'] = final.
 *          $forceOverwrite=true bỏ qua merge, dùng target trực tiếp (CLI flag).
 */
function diff_for_patch(array $target, array $snapshot, $hintLocale = 'vi', $forceOverwriteNotes = false)
{
    $patch = [];

    $tFrag = (string)$target['fragment'];
    $sFrag = (string)$snapshot['fragment'];
    if (trim($tFrag) !== trim($sFrag)) {
        $patch['fragment'] = $tFrag;
    }

    $tHint = (string)$target['hint'];
    $sHint = (string)$snapshot['hint'];
    if (trim($tHint) !== trim($sHint)) {
        $patch['hints'] = LingqClient::buildHintsArray($tHint, $hintLocale);
    }

    $tTags = normalize_tags($target['tags']);
    $sTags = normalize_tags($snapshot['tags']);
    if ($tTags !== $sTags) {
        // LingQ API muốn array of strings — re-parse target raw để giữ order/case nguyên gốc.
        $patch['tags'] = parse_tags_to_array($target['tags']);
    }

    // Phase J — notes diff (idempotent merge).
    $tNotes = (string)(isset($target['notes']) ? $target['notes'] : '');
    $sNotes = (string)(isset($snapshot['notes']) ? $snapshot['notes'] : '');
    $finalNotes = $forceOverwriteNotes ? $tNotes : merge_notes_for_patch($tNotes, $sNotes);
    if ($finalNotes !== $sNotes) {
        $patch['notes'] = $finalNotes;
    }

    return $patch;
}

/**
 * Tags string → sorted array of trimmed lowercase tokens (cho equality compare).
 */
function normalize_tags($s)
{
    $s = (string)$s;
    if ($s === '') return [];
    $parts = preg_split('/;+/', $s);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $out[] = strtolower($p);
    }
    sort($out);
    return $out;
}

/**
 * Tags string → array of original-cased strings (cho POST/PATCH payload).
 */
function parse_tags_to_array($s)
{
    $s = (string)$s;
    if ($s === '') return [];
    $parts = preg_split('/;+/', $s);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $out[] = $p;
    }
    return $out;
}

/**
 * Build POST payload từ target row.
 * status từ target (= 1 cho row mới) — DO NOT confuse với PATCH (preserve from snapshot).
 *
 * Phase F: gửi `hints` array of {text, locale} (plural, LingQ API v2 chuẩn).
 *          Trước đó gửi `hint` singular → server ignore → 53 entries empty trên UI.
 *          Empty hint → hints=[] (server cho phép, tương đương "no translation yet").
 */
function build_post_payload(array $row, $hintLocale = 'vi')
{
    $payload = [
        'term'            => (string)$row['term'],
        'fragment'        => (string)$row['fragment'],
        'hints'           => LingqClient::buildHintsArray($row['hint'], $hintLocale),
        'status'          => isset($row['status']) ? (int)$row['status'] : 1,
        'extended_status' => isset($row['extended_status']) ? (int)$row['extended_status'] : 0,
        'tags'            => parse_tags_to_array($row['tags']),
    ];
    // Phase J — include notes nếu target có (CREATE card mới với marker block ngay).
    $notes = (string)(isset($row['notes']) ? $row['notes'] : '');
    if ($notes !== '') {
        $payload['notes'] = $notes;
    }
    return $payload;
}

function relative_path($abs, $base)
{
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncmp($abs, $base, strlen($base)) === 0) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($abs, strlen($base)));
    }
    return str_replace(DIRECTORY_SEPARATOR, '/', $abs);
}
