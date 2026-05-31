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
    $st = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row) { return false; }
    if (!password_verify($password, $row['password_hash'])) { return false; }
    auth_session_start();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['id'];
    $_SESSION['uname'] = $row['username'];
    $_SESSION['role'] = $row['role'] ?? 'student';
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

// Role của user đang login: 'student' | 'tutor' | 'admin'. null nếu chưa login.
// Session cũ (login trước migration 004) chưa có role → fallback query DB 1 lần rồi cache vào session.
function auth_role()
{
    $uid = auth_user_id();
    if ($uid === null) { return null; }
    if (isset($_SESSION['role']) && $_SESSION['role'] !== '') {
        return $_SESSION['role'];
    }
    $st = db()->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $st->execute([$uid]);
    $row = $st->fetch();
    $role = ($row && isset($row['role']) && $row['role'] !== '') ? $row['role'] : 'student';
    $_SESSION['role'] = $role;
    return $role;
}

// Route đích sau login theo role: tutor → /tutor, còn lại → /.
function auth_home_path()
{
    return auth_role() === 'tutor' ? '/tutor' : '/';
}

// student_id của DỮ LIỆU đang xem (vocab/events/progress/note).
//   - tutor đang "học cùng" 1 học viên → $_SESSION['view_student_id']
//   - còn lại → chính mình (auth_user_id)
// LƯU Ý: đây KHÔNG phải identity. Mọi security check vẫn dùng auth_user_id()/auth_role().
function auth_active_student_id()
{
    auth_session_start();
    if (isset($_SESSION['view_student_id']) && auth_role() === 'tutor') {
        return (int)$_SESSION['view_student_id'];
    }
    return auth_user_id();
}

// True nếu tutor đang impersonate 1 học viên (để hiện banner "👁 Đang xem").
function auth_is_tutor_viewing()
{
    auth_session_start();
    return auth_role() === 'tutor' && isset($_SESSION['view_student_id']);
}

// Gọi đầu route cần login. Chưa login → redirect /login.
function auth_require()
{
    if (!auth_check()) {
        header('Location: /login');
        exit;
    }
}

// Yêu cầu role cụ thể. Chưa login → /login. Sai role → về home của role hiện tại.
function auth_require_role($role)
{
    auth_require();
    if (auth_role() !== $role) {
        header('Location: ' . auth_home_path());
        exit;
    }
}
