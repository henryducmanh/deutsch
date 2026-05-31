<?php
// notes.php — API ghi chú buổi học (collaborative note editor cho Tutor + Student).
//   GET  /api/notes?lesson_id=4.29&student_id=1&date=2026-05-31  (session) → note hiện có (rỗng nếu chưa có)
//   POST /api/notes  body {lesson_id,student_id,date,content}     (session) → upsert, trả note_id + updated_at
//
// Auth: session login (KHÔNG Bearer — đây là route web cho người dùng).
// Quyền truy cập note (notes_can_access):
//   - tutor : chỉ student được gán cho mình (tutor_students status='active')
//   - student: chỉ note của chính mình (student_id == uid)
//   - admin : mọi note
// Note key UNIQUE (student_id, lesson_id, session_date) → upsert INSERT ... ON DUPLICATE KEY UPDATE.
// updated_at lưu UTC (db() SET +00:00), trả về dạng ISO-8601 Z cho client poll.

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/api_auth.php';
require_once __DIR__ . '/../lib/auth.php';

// ── Validate helpers ──
function notes_valid_lesson($id)
{
    return is_string($id) && preg_match('/^[A-Za-z0-9._-]{1,16}$/', $id) === 1;
}

function notes_valid_date($d)
{
    return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
}

// 'YYYY-MM-DD HH:MM:SS' → 'YYYY-MM-DDTHH:MM:SSZ' (null giữ null).
function notes_iso_z($ts)
{
    if ($ts === null || $ts === '') { return null; }
    return str_replace(' ', 'T', trim($ts)) . 'Z';
}

// ── Access control ──
function tutor_has_student($tutorId, $studentId)
{
    $st = db()->prepare(
        "SELECT 1 FROM tutor_students WHERE tutor_id = ? AND student_id = ? AND status = 'active' LIMIT 1"
    );
    $st->execute([(int)$tutorId, (int)$studentId]);
    return (bool)$st->fetch();
}

// Danh sách student được gán cho tutor (cho dashboard).
function tutor_student_list($tutorId)
{
    $st = db()->prepare(
        "SELECT u.id, u.username FROM tutor_students ts
         JOIN users u ON u.id = ts.student_id
         WHERE ts.tutor_id = ? AND ts.status = 'active'
         ORDER BY u.username"
    );
    $st->execute([(int)$tutorId]);
    return $st->fetchAll();
}

// Note gần đây của 1 student (cho dashboard mở lại nhanh).
function tutor_recent_notes($studentId, $limit = 10)
{
    $st = db()->prepare(
        "SELECT lesson_id, session_date, updated_at FROM tutor_notes
         WHERE student_id = ? ORDER BY session_date DESC, updated_at DESC LIMIT ?"
    );
    $st->bindValue(1, (int)$studentId, PDO::PARAM_INT);
    $st->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// Người đang login có được xem/sửa note của $studentId không?
function notes_can_access($studentId)
{
    $studentId = (int)$studentId;
    if ($studentId <= 0) { return false; }
    if (!auth_check()) { return false; }
    $role = auth_role();
    $uid  = (int)auth_user_id();
    if ($role === 'tutor') { return tutor_has_student($uid, $studentId); }
    if ($role === 'admin') { return true; }
    return $studentId === $uid;   // student: chỉ note của chính mình
}

// ── GET /api/notes ──
function api_notes_get()
{
    if (!auth_check()) { api_json(401, ['error' => 'not logged in']); }
    $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    if ($studentId <= 0) { $studentId = (int)auth_active_student_id(); }   // default: học viên đang xem
    $lessonId  = isset($_GET['lesson_id']) ? trim((string)$_GET['lesson_id']) : '';
    $date      = isset($_GET['date']) ? trim((string)$_GET['date']) : '';

    if (!notes_valid_lesson($lessonId)) { api_json(400, ['error' => 'lesson_id không hợp lệ']); }
    if (!notes_valid_date($date))       { api_json(400, ['error' => 'date không hợp lệ (YYYY-MM-DD)']); }
    if (!notes_can_access($studentId))  { api_json(403, ['error' => 'Không có quyền với note này']); }

    $st = db()->prepare(
        'SELECT id, student_id, lesson_id, session_date, content, updated_at
         FROM tutor_notes WHERE student_id = ? AND lesson_id = ? AND session_date = ? LIMIT 1'
    );
    $st->execute([$studentId, $lessonId, $date]);
    $row = $st->fetch();

    if (!$row) {
        api_json(200, [
            'note_id'      => null,
            'lesson_id'    => $lessonId,
            'student_id'   => $studentId,
            'session_date' => $date,
            'content'      => '',
            'updated_at'   => null,
        ]);
    }
    api_json(200, [
        'note_id'      => (int)$row['id'],
        'lesson_id'    => $row['lesson_id'],
        'student_id'   => (int)$row['student_id'],
        'session_date' => $row['session_date'],
        'content'      => $row['content'] !== null ? $row['content'] : '',
        'updated_at'   => notes_iso_z($row['updated_at']),
    ]);
}

// ── POST /api/notes — upsert (last-write-wins) ──
function api_notes_post()
{
    if (!auth_check()) { api_json(401, ['error' => 'not logged in']); }
    $body = api_body_json();
    $studentId = isset($body['student_id']) ? (int)$body['student_id'] : 0;
    if ($studentId <= 0) { $studentId = (int)auth_active_student_id(); }   // default: học viên đang xem
    $lessonId  = isset($body['lesson_id']) ? trim((string)$body['lesson_id']) : '';
    $date      = isset($body['date']) ? trim((string)$body['date']) : '';
    $content   = isset($body['content']) ? (string)$body['content'] : '';

    if (!notes_valid_lesson($lessonId)) { api_json(400, ['error' => 'lesson_id không hợp lệ']); }
    if (!notes_valid_date($date))       { api_json(400, ['error' => 'date không hợp lệ (YYYY-MM-DD)']); }
    if (!notes_can_access($studentId))  { api_json(403, ['error' => 'Không có quyền với note này']); }
    // Guard kích thước (MEDIUMTEXT = 16MB; chặn sớm ở 5MB để an toàn).
    if (strlen($content) > 5000000)     { api_json(413, ['error' => 'content quá lớn (> 5MB)']); }

    $pdo = db();
    $sql = 'INSERT INTO tutor_notes (student_id, lesson_id, session_date, content)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE content = VALUES(content)';
    $st = $pdo->prepare($sql);
    $st->execute([$studentId, $lessonId, $date, $content]);

    // Lấy id + updated_at thực tế sau upsert.
    $q = $pdo->prepare(
        'SELECT id, updated_at FROM tutor_notes
         WHERE student_id = ? AND lesson_id = ? AND session_date = ? LIMIT 1'
    );
    $q->execute([$studentId, $lessonId, $date]);
    $row = $q->fetch();

    api_json(200, [
        'ok'         => true,
        'note_id'    => $row ? (int)$row['id'] : null,
        'updated_at' => $row ? notes_iso_z($row['updated_at']) : null,
    ]);
}
