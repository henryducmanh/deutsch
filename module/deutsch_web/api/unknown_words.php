<?php
// unknown_words.php — GET /api/unknown_words/pending
// Trả từ đánh dấu (event word_mark) CHƯA sync (synced_at IS NULL). Bearer bắt buộc.

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api_auth.php';
require_once __DIR__ . '/events.php'; // dùng iso_z()

function api_unknown_words_pending()
{
    api_require_key();
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
    if ($limit <= 0 || $limit > 2000) { $limit = 500; }

    $st = db()->prepare(
        "SELECT event_id, lesson_id, payload, created_at
         FROM events
         WHERE type = 'word_mark' AND synced_at IS NULL
         ORDER BY created_at ASC LIMIT ?"
    );
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();

    $words = [];
    foreach ($st->fetchAll() as $row) {
        $p = json_decode($row['payload'], true);
        if (!is_array($p)) { $p = []; }
        $words[] = [
            'event_id'    => $row['event_id'],
            'word'        => $p['word'] ?? '',
            'word_status' => $p['word_status'] ?? '',
            'lesson_id'   => $row['lesson_id'],
            'context'     => $p['context'] ?? '',
            'vocab_id'    => $p['vocab_id'] ?? null,
            'clicked_at'  => iso_z($row['created_at']),
        ];
    }
    api_json(200, ['count' => count($words), 'words' => $words]);
}
