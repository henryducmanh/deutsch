<?php
// tutor_dashboard.php — dashboard gia sư: danh sách học viên + nút "Học cùng X" (impersonation).
// Router set: $students (mảng {id,username}), $uname.
// Cơ chế: click "Học cùng X" → /tutor/select/{id} → xem web y như học viên đó login.
/** @var array $students */
/** @var string $uname */
$students = $students ?? [];
$uname = $uname ?? '';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gia sư — Học viên</title>
<link rel="stylesheet" href="/assets/drill.css">
<style>
  .tutor-card { display:flex; justify-content:space-between; align-items:center; gap:16px;
    background:#fff; border:1px solid #e2e4ea; border-radius:12px; padding:16px 18px; margin:12px 0; }
  .tutor-card .who { font-size:17px; font-weight:600; }
  .tutor-card .role { font-size:12px; color:#9ca3af; margin-left:6px; font-weight:400; }
  .btn-study { background:#1a1a2e; color:#fff; border:none; border-radius:9px; padding:10px 16px;
    font-size:14px; text-decoration:none; white-space:nowrap; }
  .btn-study:hover { background:#2a2a44; }
  .muted { color:#9ca3af; }
</style>
</head>
<body>
<div class="app">
  <div class="list-head">
    <h1>Xin chào, <?= h($uname) ?>!</h1>
    <a class="logout-link" href="/logout">Abmelden</a>
  </div>

  <p class="muted">Học viên của bạn — chọn để xem màn hình học viên (share qua Zoom):</p>

  <?php if (empty($students)): ?>
    <p class="muted">Chưa có học viên nào được gán. Liên hệ admin để gán học viên.</p>
  <?php else: ?>
    <?php foreach ($students as $s): ?>
      <?php $sid = (int)$s['id']; ?>
      <div class="tutor-card">
        <div class="who">👤 <?= h($s['username']) ?></div>
        <a class="btn-study" href="/tutor/select/<?= $sid ?>">▶ Học cùng <?= h($s['username']) ?></a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
