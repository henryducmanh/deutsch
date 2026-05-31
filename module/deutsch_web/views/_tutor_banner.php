<?php
// _tutor_banner.php — banner "👁 Đang xem: <học viên>" khi tutor đang impersonate.
// Include ở đầu <body> của lesson_list.php và drill_horen.php.
// Yêu cầu: auth.php + db.php đã load (router đã require). Không xuất gì nếu không impersonate.
if (function_exists('auth_is_tutor_viewing') && auth_is_tutor_viewing()):
    $vuid = (int)$_SESSION['view_student_id'];
    $stB = db()->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $stB->execute([$vuid]);
    $vname = $stB->fetchColumn();
    if ($vname === false) { $vname = '#' . $vuid; }
?>
<div style="background:#1a1a2e;color:#fff;padding:8px 16px;font-size:13px;display:flex;align-items:center;gap:16px;">
  <span>👁 Đang xem: <strong><?= htmlspecialchars((string)$vname, ENT_QUOTES, 'UTF-8') ?></strong></span>
  <a href="/tutor/exit" style="color:#ff9f43;font-size:12px;text-decoration:none;">← Thoát dashboard</a>
</div>
<?php endif; ?>
