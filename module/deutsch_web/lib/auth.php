<?php
// auth.php — session login cho route web. Pattern tham khảo dự án mieu nhưng
// KHÔNG dùng DB mieu: user lưu trong bảng users của deutsch_web.sqlite.

require_once __DIR__ . '/db.php';

function auth_session_start()
{
    if (session_status() === PHP_SESSION_NONE) {
        $cfg = dw_config();
        session_name($cfg['session_name'] ?? 'deutsch_web_sess');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            // 'secure' => true,  // bật khi deploy HTTPS twv.app (Henry chỉnh nếu cần)
        ]);
        session_start();
    }
}

function auth_attempt($username, $password)
{
    $st = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row) { return false; }
    if (!password_verify($password, $row['password_hash'])) { return false; }
    auth_session_start();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['id'];
    $_SESSION['uname'] = $row['username'];
    return true;
}

function auth_logout()
{
    auth_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_user_id()
{
    auth_session_start();
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}

function auth_check()
{
    return auth_user_id() !== null;
}

// Gọi đầu route cần login. Chưa login → redirect /login.
function auth_require()
{
    if (!auth_check()) {
        header('Location: /login');
        exit;
    }
}
