<?php
// lessons.php — GET /api/lessons/{id}/vocab  → mảng vocab từ lesson JSON.
// Auth: session (web user) HOẶC Bearer token đều OK (quyết định README mục 13).
// POST /api/lessons/{id}/vocab → stub Phase 2 (chưa làm).

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api_auth.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/lesson_loader.php';

// Cho phép qua nếu: có session login HOẶC Bearer token đúng.
function api_lessons_guard()
{
    if (auth_check()) { return; }            // web user
    api_require_key();                        // hoặc CLI Bearer (tự exit 401 nếu sai)
}

function api_lessons_vocab($id)
{
    api_lessons_guard();
    $lesson = lesson_load($id);
    if ($lesson === null) {
        api_json(404, ['error' => 'lesson not found']);
    }
    api_json(200, [
        'lesson_id' => $lesson['lesson_id'] ?? $id,
        'vocab'     => $lesson['vocab'] ?? [],
    ]);
}

// POST stub Phase 2 — chưa hỗ trợ ghi vocab động.
function api_lessons_vocab_post($id)
{
    api_json(501, ['error' => 'POST vocab chưa hỗ trợ (Phase 2).']);
}
