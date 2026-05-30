<?php
// index.php — front controller / router.
// 2 chế độ chạy:
//   1) Apache (deploy twv.app): .htaccess rewrite mọi request → index.php.
//   2) PHP built-in server dùng index.php làm ROUTER script:
//        php -S localhost:8080 -t public public/index.php
//      → file tĩnh thật (assets/*) trả false để server tự serve; còn lại router xử lý.

// ── Built-in server: để server tự serve file tĩnh đã tồn tại (css/js/...) ──
if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $reqPath;
    if ($reqPath !== '/' && is_file($file)) {
        return false; // server tự trả file tĩnh
    }
}

$BASE = __DIR__ . '/..';
require_once $BASE . '/lib/db.php';
require_once $BASE . '/lib/auth.php';
require_once $BASE . '/lib/lesson_loader.php';

// ── Parse path + method ──
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = '/' . trim(rawurldecode($path), '/');
if ($path === '/') { $path = '/'; }
$method = $_SERVER['REQUEST_METHOD'];

// ── API routes (Bearer / session) ──
if (strpos($path, '/api/') === 0) {
    route_api($path, $method, $BASE);
    exit;
}

// ── Web routes (session) ──
switch (true) {
    case $path === '/login':
        if ($method === 'POST') {
            $u = $_POST['username'] ?? '';
            $p = $_POST['password'] ?? '';
            if (auth_attempt($u, $p)) {
                header('Location: /');
                exit;
            }
            $error = 'Sai tên đăng nhập hoặc mật khẩu.';
            require $BASE . '/views/login.php';
            exit;
        }
        if (auth_check()) { header('Location: /'); exit; }
        $error = null;
        require $BASE . '/views/login.php';
        exit;

    case $path === '/logout':
        auth_logout();
        header('Location: /login');
        exit;

    case $path === '/':
        auth_require();
        $lessons = lesson_list(auth_user_id());
        $uname = $_SESSION['uname'] ?? '';
        require $BASE . '/views/lesson_list.php';
        exit;

    case preg_match('#^/lesson/([A-Za-z0-9._-]+)$#', $path, $m) === 1:
        auth_require();
        $lesson = lesson_load($m[1]);
        if ($lesson === null) {
            http_response_code(404);
            echo 'Lesson không tồn tại.';
            exit;
        }
        require $BASE . '/views/drill_horen.php';
        exit;

    case $path === '/track':
        route_track($method);
        exit;

    default:
        http_response_code(404);
        echo 'Not Found';
        exit;
}

// ── /track : web POST event (session-authenticated) ──
function route_track($method)
{
    header('Content-Type: application/json; charset=utf-8');
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST only']);
        return;
    }
    if (!auth_check()) {
        http_response_code(401);
        echo json_encode(['error' => 'not logged in']);
        return;
    }
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) { $body = []; }

    $type = $body['type'] ?? '';
    $allowed = ['horen_complete', 'word_mark', 'lesson_open'];
    if (!in_array($type, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'type không hợp lệ']);
        return;
    }
    $lessonId = $body['lesson_id'] ?? null;
    if ($lessonId !== null && !lesson_id_valid($lessonId)) {
        http_response_code(400);
        echo json_encode(['error' => 'lesson_id không hợp lệ']);
        return;
    }
    $payload = $body['payload'] ?? [];
    if (!is_array($payload)) { $payload = []; }

    $eventId = dw_uuid4();
    $st = db()->prepare(
        'INSERT INTO events (event_id, user_id, type, lesson_id, payload) VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([
        $eventId,
        auth_user_id(),
        $type,
        $lessonId,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    echo json_encode(['ok' => true, 'event_id' => $eventId]);
}

// ── API dispatch ──
function route_api($path, $method, $BASE)
{
    // GET /api/events , POST /api/events/ack
    if ($path === '/api/events' && $method === 'GET') {
        require_once $BASE . '/api/events.php';
        api_events_list();
        return;
    }
    if ($path === '/api/events/ack' && $method === 'POST') {
        require_once $BASE . '/api/events.php';
        api_events_ack();
        return;
    }
    // GET /api/unknown_words/pending
    if ($path === '/api/unknown_words/pending' && $method === 'GET') {
        require_once $BASE . '/api/unknown_words.php';
        api_unknown_words_pending();
        return;
    }
    // GET/POST /api/lessons/{id}/vocab
    if (preg_match('#^/api/lessons/([A-Za-z0-9._-]+)/vocab$#', $path, $m) === 1) {
        require_once $BASE . '/api/lessons.php';
        if ($method === 'GET') { api_lessons_vocab($m[1]); return; }
        if ($method === 'POST') { api_lessons_vocab_post($m[1]); return; }
    }
    // /api/vocab (GET session/Bearer, POST session) — Phase 2/3 vocab panel + web-add
    if ($path === '/api/vocab') {
        require_once $BASE . '/api/vocab.php';
        if ($method === 'GET')  { api_vocab_get();  return; }
        if ($method === 'POST') { api_vocab_post(); return; }
    }
    // POST /api/vocab/bulk (Bearer) — push_vocab upsert
    if ($path === '/api/vocab/bulk' && $method === 'POST') {
        require_once $BASE . '/api/vocab.php';
        api_vocab_bulk();
        return;
    }
    // GET /api/vocab/new (Bearer) — pull_vocab kéo web-add
    if ($path === '/api/vocab/new' && $method === 'GET') {
        require_once $BASE . '/api/vocab.php';
        api_vocab_new();
        return;
    }

    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'API route không tồn tại']);
}
