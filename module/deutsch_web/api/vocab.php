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

    // Tách + chuẩn hoá wort_key, dedup, cap 50 (spec §6: ≤ 50 words/request).
    $keys = [];
    foreach (explode(',', $raw) as $w) {
        $k = vocab_key($w);
        if ($k !== '' && !isset($keys[$k])) { $keys[$k] = true; }
        if (count($keys) >= 50) { break; }
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
function api_vocab_bulk()
{
    api_require_key();
    $body = api_body_json();
    $rows = isset($body['rows']) && is_array($body['rows']) ? $body['rows'] : [];
    if (count($rows) === 0) { api_json(400, ['error' => 'rows rỗng']); }
    if (count($rows) > 100) { api_json(400, ['error' => 'batch > 100 rows (cap §6)']); }

    $pdo = db();
    $sql = "INSERT INTO vocab (vocab_id, wort, wort_key, wortart, artikel, bedeutung, niveau, level, thema, tags, curated)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
              vocab_id=VALUES(vocab_id), wort=VALUES(wort), wortart=VALUES(wortart),
              artikel=VALUES(artikel), bedeutung=VALUES(bedeutung), niveau=VALUES(niveau),
              level=VALUES(level), thema=VALUES(thema), tags=VALUES(tags), curated=1";
    $st = $pdo->prepare($sql);

    $upserted = 0;
    $skipped  = 0;
    $pdo->beginTransaction();
    foreach ($rows as $r) {
        if (!is_array($r)) { $skipped++; continue; }
        $wort = isset($r['wort']) ? trim((string)$r['wort']) : '';
        if ($wort === '') { $skipped++; continue; }
        $wkey = vocab_key($wort);
        $level = isset($r['level']) && is_numeric($r['level']) ? max(1, min(4, (int)$r['level'])) : 1;
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
        ]);
        // affected-rows: insert=1, update đổi giá trị=2 → tính upsert; 0 = không đổi → skipped.
        if ($st->rowCount() > 0) { $upserted++; } else { $skipped++; }
    }
    $pdo->commit();
    api_json(200, ['upserted' => $upserted, 'skipped' => $skipped]);
}

// ── GET /api/vocab/queued?lesson_id=4.31 (session) — load queued words khi mở bài ──
// Trả về tất cả từ có source = lesson_id VÀ curated=0 (web-add chưa curate).
// drill.js merge vào vocabData để persist panel sau reload.
function api_vocab_queued()
{
    api_vocab_guard();
    $lid = isset($_GET['lesson_id']) ? trim($_GET['lesson_id']) : '';
    if ($lid === '') { api_json(200, ['vocab' => []]); }

    $st = db()->prepare(
        "SELECT wort, wort_key, wortart, artikel, bedeutung, level, id
         FROM vocab WHERE source = ? AND curated = 0
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
            'queued'   => true,  // flag cho drill.js biết đây là từ chưa curate
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
