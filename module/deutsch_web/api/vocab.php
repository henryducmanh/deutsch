<?php
// vocab.php — API vocab cho deutsch_web.
//   GET  /api/vocab?words=a,b,c   (session HOẶC Bearer) → vocab khớp wort_key (panel load)
//   POST /api/vocab               (session)             → web-add 1 từ (tab "Neu wort"), curated=0
//   POST /api/vocab/bulk          (Bearer)              → push_vocab upsert theo wort_key, curated=1
//   GET  /api/vocab/new?since=    (Bearer)              → pull_vocab kéo web-add (curated=0)
//
// wort_key = mb_strtolower(wort) — khóa dedup UNIQUE. created_at lưu UTC (db() SET +00:00).

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api_auth.php';
require_once __DIR__ . '/../lib/auth.php';

// Cho qua nếu có session login HOẶC Bearer token đúng.
function api_vocab_guard()
{
    if (auth_check()) { return; }
    api_require_key();   // tự exit 401 nếu sai
}

// wort → wort_key (lowercase unicode-safe).
function vocab_key($wort)
{
    return mb_strtolower(trim((string)$wort), 'UTF-8');
}

// Ghép 'art' hiển thị panel từ artikel + wortart: 'die · Subst.', 'Verb', 'Adj.'.
function vocab_art($artikel, $wortart)
{
    $abbrMap = [
        'substantiv' => 'Subst.', 'nomen' => 'Subst.',
        'verb'       => 'Verb',
        'adjektiv'   => 'Adj.', 'adj.' => 'Adj.', 'adj' => 'Adj.',
        'adverb'     => 'Adv.',
        'präposition' => 'Präp.', 'praeposition' => 'Präp.',
    ];
    $parts = [];
    $artikel = trim((string)$artikel);
    if ($artikel !== '') { $parts[] = $artikel; }
    $wa = trim((string)$wortart);
    if ($wa !== '') {
        $key = mb_strtolower($wa, 'UTF-8');
        $parts[] = $abbrMap[$key] ?? $wa;
    }
    return implode(' · ', $parts);
}

// 'YYYY-MM-DDTHH:MM:SSZ' → 'YYYY-MM-DD HH:MM:SS' (khớp cột created_at).
function vocab_norm_ts($ts)
{
    if ($ts === '') { return ''; }
    $ts = trim(str_replace(['T', 'Z'], [' ', ''], $ts));
    if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $ts, $m)) { return $m[1]; }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $ts, $m)) { return $m[1] . ' 00:00:00'; }
    return $ts;
}

// 'YYYY-MM-DD HH:MM:SS' → 'YYYY-MM-DDTHH:MM:SSZ'.
function vocab_iso_z($ts)
{
    if ($ts === null || $ts === '') { return $ts; }
    return str_replace(' ', 'T', trim($ts)) . 'Z';
}

// ── GET /api/vocab?words=a,b,c ──
function api_vocab_get()
{
    api_vocab_guard();
    $raw = isset($_GET['words']) ? trim($_GET['words']) : '';
    if ($raw === '') { api_json(200, ['vocab' => []]); }

    // Tách + chuẩn hoá wort_key, dedup, cap 300 (global token scan LingQ-style: cần quét cả bài).
    $keys = [];
    foreach (explode(',', $raw) as $w) {
        $k = vocab_key($w);
        if ($k !== '' && !isset($keys[$k])) { $keys[$k] = true; }
        if (count($keys) >= 300) { break; }
    }
    if (count($keys) === 0) { api_json(200, ['vocab' => []]); }

    $keyList = array_keys($keys);
    $ph = implode(',', array_fill(0, count($keyList), '?'));
    $sql = "SELECT vocab_id, wort, wort_key, wortart, artikel, bedeutung, level
            FROM vocab WHERE wort_key IN ($ph)";
    $st = db()->prepare($sql);
    $st->execute($keyList);

    $vocab = [];
    foreach ($st->fetchAll() as $row) {
        $vocab[] = [
            'w'         => $row['wort'],
            'wort_key'  => $row['wort_key'],
            'art'       => vocab_art($row['artikel'], $row['wortart']),
            'bedeutung' => $row['bedeutung'],
            'level'     => (int)$row['level'],
            'vocab_id'  => $row['vocab_id'],
        ];
    }
    api_json(200, ['vocab' => $vocab]);
}

// ── POST /api/vocab (session) — web-add 1 từ (tab "Neu wort") ──
function api_vocab_post()
{
    if (!auth_check()) { api_json(401, ['error' => 'not logged in']); }
    $body = api_body_json();
    $wort = isset($body['wort']) ? trim((string)$body['wort']) : '';
    if ($wort === '') { api_json(400, ['error' => 'wort rỗng']); }
    $wkey = vocab_key($wort);

    $bedeutung = isset($body['bedeutung']) ? trim((string)$body['bedeutung']) : '';
    $wortart   = isset($body['wortart']) && trim((string)$body['wortart']) !== '' ? trim((string)$body['wortart']) : null;
    $artikel   = isset($body['artikel']) && trim((string)$body['artikel']) !== '' ? trim((string)$body['artikel']) : null;
    $source    = isset($body['source_lesson']) ? trim((string)$body['source_lesson']) : null;
    if ($source === '') { $source = null; }

    // Upsert theo wort_key: nếu đã có (curated=1 từ CSV) → KHÔNG hạ curated, chỉ bổ nghĩa nếu trống.
    $pdo = db();
    $sql = "INSERT INTO vocab (wort, wort_key, wortart, artikel, bedeutung, niveau, level, source, curated)
            VALUES (?, ?, ?, ?, ?, 'B1', 1, ?, 0)
            ON DUPLICATE KEY UPDATE
              bedeutung = IF(bedeutung IS NULL OR bedeutung = '', VALUES(bedeutung), bedeutung),
              wortart   = IF(wortart IS NULL OR wortart = '', VALUES(wortart), wortart),
              artikel   = IF(artikel IS NULL OR artikel = '', VALUES(artikel), artikel),
              source    = IF(source IS NULL OR source = '', VALUES(source), source)";
    $st = $pdo->prepare($sql);
    $st->execute([$wort, $wkey, $wortart, $artikel, $bedeutung !== '' ? $bedeutung : null, $source]);

    // Lấy id (insert mới → lastInsertId; trùng → query lại theo wort_key).
    $id = (int)$pdo->lastInsertId();
    if ($id === 0) {
        $q = $pdo->prepare('SELECT id FROM vocab WHERE wort_key = ? LIMIT 1');
        $q->execute([$wkey]);
        $r = $q->fetch();
        $id = $r ? (int)$r['id'] : 0;
    }
    api_json(200, ['ok' => true, 'id' => $id, 'wort_key' => $wkey]);
}

// ── POST /api/vocab/bulk (Bearer) — push_vocab upsert (curated=1) ──
// Hỗ trợ lemma → biến thể (DD-20260527-005): row có 'parent_vocab_id' (VOC-id của lemma trong CSV)
// → server resolve sang vocab.id rồi set parent_id. 'form_type' = mã biến cách (NOM.PL, PART.II...).
// push_vocab gửi 2-pass (lemma trước → biến thể sau) để lookup parent_vocab_id luôn thấy lemma.
function api_vocab_bulk()
{
    api_require_key();
    $body = api_body_json();
    $rows = isset($body['rows']) && is_array($body['rows']) ? $body['rows'] : [];
    if (count($rows) === 0) { api_json(400, ['error' => 'rows rỗng']); }
    if (count($rows) > 100) { api_json(400, ['error' => 'batch > 100 rows (cap §6)']); }

    $pdo = db();
    $sql = "INSERT INTO vocab (vocab_id, wort, wort_key, wortart, artikel, bedeutung, niveau, level, thema, tags, parent_id, form_type, curated)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
              vocab_id=VALUES(vocab_id), wort=VALUES(wort), wortart=VALUES(wortart),
              artikel=VALUES(artikel), bedeutung=VALUES(bedeutung), niveau=VALUES(niveau),
              level=VALUES(level), thema=VALUES(thema), tags=VALUES(tags),
              parent_id = IF(VALUES(parent_id) IS NOT NULL, VALUES(parent_id), parent_id),
              form_type = IF(VALUES(form_type) IS NOT NULL AND VALUES(form_type) <> '', VALUES(form_type), form_type),
              curated=1";
    $st = $pdo->prepare($sql);

    // Resolver vocab_id (CSV, vd 'VOC-20260518-010') → vocab.id (INT). Cache trong request.
    $lemmaCache = [];
    $lemmaLookup = $pdo->prepare('SELECT id FROM vocab WHERE vocab_id = ? LIMIT 1');
    $resolveParent = function ($parentVocabId) use (&$lemmaCache, $lemmaLookup) {
        $pid = trim((string)$parentVocabId);
        if ($pid === '') { return null; }
        if (array_key_exists($pid, $lemmaCache)) { return $lemmaCache[$pid]; }
        $lemmaLookup->execute([$pid]);
        $row = $lemmaLookup->fetch();
        $id = $row ? (int)$row['id'] : null;
        $lemmaCache[$pid] = $id;
        return $id;
    };

    $upserted = 0;
    $skipped  = 0;
    $orphan   = 0;   // biến thể có parent_vocab_id nhưng lemma chưa có trong DB
    $pdo->beginTransaction();
    foreach ($rows as $r) {
        if (!is_array($r)) { $skipped++; continue; }
        $wort = isset($r['wort']) ? trim((string)$r['wort']) : '';
        if ($wort === '') { $skipped++; continue; }
        $wkey = vocab_key($wort);
        $level = isset($r['level']) && is_numeric($r['level']) ? max(1, min(4, (int)$r['level'])) : 1;

        $parentVocabId = isset($r['parent_vocab_id']) ? trim((string)$r['parent_vocab_id']) : '';
        $parentId = $parentVocabId !== '' ? $resolveParent($parentVocabId) : null;
        if ($parentVocabId !== '' && $parentId === null) { $orphan++; }  // lemma chưa push → parent_id NULL
        $formType = isset($r['form_type']) && $r['form_type'] !== '' ? (string)$r['form_type'] : null;

        $st->execute([
            isset($r['vocab_id']) && $r['vocab_id'] !== '' ? (string)$r['vocab_id'] : null,
            $wort,
            $wkey,
            isset($r['wortart']) && $r['wortart'] !== '' ? (string)$r['wortart'] : null,
            isset($r['artikel']) && $r['artikel'] !== '' ? (string)$r['artikel'] : null,
            isset($r['bedeutung']) && $r['bedeutung'] !== '' ? (string)$r['bedeutung'] : null,
            isset($r['niveau']) && $r['niveau'] !== '' ? (string)$r['niveau'] : 'B1',
            $level,
            isset($r['thema']) && $r['thema'] !== '' ? (string)$r['thema'] : null,
            isset($r['tags']) && $r['tags'] !== '' ? (string)$r['tags'] : null,
            $parentId,
            $formType,
        ]);
        // affected-rows: insert=1, update đổi giá trị=2 → tính upsert; 0 = không đổi → skipped.
        if ($st->rowCount() > 0) { $upserted++; } else { $skipped++; }
    }
    $pdo->commit();
    api_json(200, ['upserted' => $upserted, 'skipped' => $skipped, 'orphan' => $orphan]);
}

// ── GET /api/vocab/forms?words=Fähigkeiten,automatisiert (session/Bearer) ──
// Trả các từ đã là BIẾN THỂ đã biết (parent_id IS NOT NULL) → link về lemma.
// words không khớp biến thể nào → trả vào "unknown" (frontend tự xử lý / queue).
function api_vocab_forms()
{
    api_vocab_guard();
    $raw = isset($_GET['words']) ? trim($_GET['words']) : '';
    if ($raw === '') { api_json(200, ['forms' => [], 'unknown' => []]); }

    // Tách + chuẩn hoá wort_key, dedup, cap 100 (spec §5).
    $keys = [];
    foreach (explode(',', $raw) as $w) {
        $k = vocab_key($w);
        if ($k !== '' && !isset($keys[$k])) { $keys[$k] = true; }
        if (count($keys) >= 100) { break; }
    }
    if (count($keys) === 0) { api_json(200, ['forms' => [], 'unknown' => []]); }
    $keyList = array_keys($keys);

    // Query 1: từ là biến thể đã biết (parent_id IS NOT NULL).
    $ph = implode(',', array_fill(0, count($keyList), '?'));
    $sql = "SELECT id, wort, wort_key, form_type, parent_id, wortart, artikel, bedeutung
            FROM vocab WHERE wort_key IN ($ph) AND parent_id IS NOT NULL";
    $st = db()->prepare($sql);
    $st->execute($keyList);
    $variants = $st->fetchAll();

    // Gom parent_id để query lemma 1 lần.
    $parentIds = [];
    foreach ($variants as $v) {
        $pid = (int)$v['parent_id'];
        if ($pid > 0) { $parentIds[$pid] = true; }
    }
    $lemmaById = [];
    if (count($parentIds) > 0) {
        $pidList = array_keys($parentIds);
        $ph2 = implode(',', array_fill(0, count($pidList), '?'));
        $sql2 = "SELECT id, wort, wort_key, wortart, artikel, bedeutung
                 FROM vocab WHERE id IN ($ph2)";
        $st2 = db()->prepare($sql2);
        $st2->execute($pidList);
        foreach ($st2->fetchAll() as $l) { $lemmaById[(int)$l['id']] = $l; }
    }

    // Build forms[]. Biến thể mà lemma không còn (parent xoá) → coi như unknown.
    $forms = [];
    $matchedKeys = [];
    foreach ($variants as $v) {
        $lemma = $lemmaById[(int)$v['parent_id']] ?? null;
        if ($lemma === null) { continue; }
        $matchedKeys[$v['wort_key']] = true;
        $forms[] = [
            'form'      => $v['wort'],
            'form_key'  => $v['wort_key'],
            'form_type' => $v['form_type'],
            'lemma'     => $lemma['wort'],
            'lemma_key' => $lemma['wort_key'],
            'lemma_id'  => (int)$lemma['id'],
            'art'       => vocab_art($lemma['artikel'], $lemma['wortart']),
            'bedeutung' => $lemma['bedeutung'],
        ];
    }

    $unknown = [];
    foreach ($keyList as $k) {
        if (!isset($matchedKeys[$k])) { $unknown[] = $k; }
    }
    api_json(200, ['forms' => $forms, 'unknown' => $unknown]);
}

// ── GET /api/vocab/queued?lesson_id=4.31 (session) — load queued words khi mở bài ──
// Trả về tất cả từ có source = lesson_id VÀ curated=0 (web-add chưa curate).
// drill.js merge vào vocabData để persist panel sau reload.
function api_vocab_queued()
{
    api_vocab_guard();
    $lid = isset($_GET['lesson_id']) ? trim($_GET['lesson_id']) : '';
    if ($lid === '') { api_json(200, ['vocab' => []]); }

    // Lấy TẤT CẢ từ có source = lesson_id (kể cả curated=1 sau khi auto-translate push).
    // Các từ từ vocab_master có source = quelle (vd 'SRC-001'), không phải lesson_id → không lẫn.
    $st = db()->prepare(
        "SELECT wort, wort_key, wortart, artikel, bedeutung, level, id, curated
         FROM vocab WHERE source = ?
         ORDER BY created_at ASC LIMIT 200"
    );
    $st->execute([$lid]);

    $vocab = [];
    foreach ($st->fetchAll() as $row) {
        $vocab[] = [
            'w'        => $row['wort'],
            'wort_key' => $row['wort_key'],
            'art'      => vocab_art($row['artikel'], $row['wortart']),
            'bedeutung'=> $row['bedeutung'],
            'level'    => (int)$row['level'],
            'vocab_id' => $row['id'],
            'curated'  => (int)$row['curated'],  // 0=chưa dịch, 1=đã có nghĩa
        ];
    }
    api_json(200, ['vocab' => $vocab]);
}

// ── GET /api/vocab/new?since= (Bearer) — pull_vocab kéo web-add (curated=0) ──
function api_vocab_new()
{
    api_require_key();
    $since = isset($_GET['since']) ? vocab_norm_ts(trim($_GET['since'])) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
    if ($limit <= 0 || $limit > 1000) { $limit = 200; }

    $sql = "SELECT id, vocab_id, wort, wort_key, wortart, artikel, bedeutung, source, created_at
            FROM vocab WHERE curated = 0";
    $params = [];
    if ($since !== '') { $sql .= " AND created_at > ?"; $params[] = $since; }
    $sql .= " ORDER BY created_at ASC LIMIT ?";

    $st = db()->prepare($sql);
    $bind = 1;
    foreach ($params as $p) { $st->bindValue($bind++, $p, PDO::PARAM_STR); }
    $st->bindValue($bind, $limit, PDO::PARAM_INT);
    $st->execute();

    $vocab = [];
    foreach ($st->fetchAll() as $row) {
        $vocab[] = [
            'id'         => (int)$row['id'],
            'wort'       => $row['wort'],
            'wort_key'   => $row['wort_key'],
            'bedeutung'  => $row['bedeutung'],
            'wortart'    => $row['wortart'],
            'artikel'    => $row['artikel'],
            'source'     => $row['source'],
            'created_at' => vocab_iso_z($row['created_at']),
        ];
    }
    api_json(200, ['count' => count($vocab), 'vocab' => $vocab]);
}
