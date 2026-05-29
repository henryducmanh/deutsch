<?php
// login.php — form đăng nhập. Router set $error (string|null) khi sai pass.
/** @var string|null $error */
$error = $error ?? null;
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập — Deutsch Web</title>
<link rel="stylesheet" href="/assets/drill.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h1>Deutsch Web — Hören</h1>
    <?php if ($error): ?>
      <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" action="/login">
      <label for="username">Tên đăng nhập</label>
      <input type="text" id="username" name="username" autocomplete="username" autofocus required>
      <label for="password">Mật khẩu</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
      <button type="submit" class="btn btn-primary">Đăng nhập</button>
    </form>
  </div>
</div>
</body>
</html>
