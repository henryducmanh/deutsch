<?php
// events.php — GET /api/events?since=  +  POST /api/events/ack
// Bearer token bắt buộc (api_auth). Append-only: ack chỉ set synced_at, KHÔNG xóa row.

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api_auth.php';

// GET /api/events?since=2026-05-29T00:00:00Z[&limit=500]
function api_events_list()
{
    api_require_key();
    $since = isset($_GET['since']) ? trim($_GET['since']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
    if ($limit <= 0 || $limit > 2000) { $limit = 500; }

    // created_at lưu dạng SQLite datetime('now') = 'YYYY-MM-DD HH:MM:SS' (UTC).
    // since đầu vào dạng ISO 'Z' → chuẩn hoá về so sánh chuỗi lexicographic được nhờ
    // cùng định dạng sắp xếp. Normalise 'T'/'Z' để so khớp cột.
    $sinceCmp = normalize_ts($since);

    $sql = "SELECT event_id, user_id, type, lesson_id, payload, created_at, synced_at
            FROM events";
    $params = [];
    if ($sinceCmp !== '') {
        $sql .= " WHERE created_at > ?";
        $params[] = $sinceCmp;
    }
    $sql .= " ORDER BY created_at ASC LIMIT ?";

    $pdo = db();
    $st = $pdo->prepare($sql);
    $bindIdx = 1;
    foreach ($params as $p) { $st->bindValue($bindIdx++, $p, PDO::PARAM_STR); }
    $st->bindValue($bindIdx, $limit, PDO::PARAM_INT);
    $st->execute();

    $events = [];
    foreach ($st->fetchAll() as $row) {
        $events[] = [
            'event_id'   => $row['event_id'],
            'type'       => $row['type'],
            'lesson_id'  => $row['lesson_id'],
            'payload'    => json_decode($row['payload'], true),
            'created_at' => iso_z($row['created_at']),
            'synced_at'  => $row['synced_at'] !== null ? iso_z($row['synced_at']) : null,
        ];
    }
    api_json(200, ['count' => count($events), 'events' => $events]);
}

// POST /api/events/ack   body {"event_ids":["..."]}
function api_events_ack()
{
    api_require_key();
    $body = api_body_json();
    $ids = $body['event_ids'] ?? [];
    if (!is_array($ids) || count($ids) === 0) {
        api_json(400, ['error' => 'event_ids rỗng']);
    }
    // Lưu cùng định dạng SQLite datetime('now') (UTC 'Y-m-d H:i:s') như created_at,
    // để iso_z() ở output chuẩn hoá đồng nhất (tránh double 'Z').
    $now = gmdate('Y-m-d H:i:s');
    $pdo = db();
    $st = $pdo->prepare('UPDATE events SET synced_at = ? WHERE event_id = ? AND synced_at IS NULL');
    $acked = 0;
    $pdo->beginTransaction();
    foreach ($ids as $id) {
        if (!is_string($id)) { continue; }
        $st->execute([$now, $id]);
        $acked += $st->rowCount();
    }
    $pdo->commit();
    api_json(200, ['acked' => $acked]);
}

// 'YYYY-MM-DDTHH:MM:SSZ' → 'YYYY-MM-DD HH:MM:SS' (khớp cột created_at SQLite).
function normalize_ts($ts)
{
    if ($ts === '') { return ''; }
    $ts = str_replace(['T', 'Z'], [' ', ''], $ts);
    $ts = trim($ts);
    // Bỏ phần phân số giây / offset nếu có.
    if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $ts, $m)) { return $m[1]; }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $ts, $m)) { return $m[1] . ' 00:00:00'; }
    return $ts;
}

// 'YYYY-MM-DD HH:MM:SS' → 'YYYY-MM-DDTHH:MM:SSZ' cho output API.
function iso_z($ts)
{
    if ($ts === null || $ts === '') { return $ts; }
    return str_replace(' ', 'T', trim($ts)) . 'Z';
}
