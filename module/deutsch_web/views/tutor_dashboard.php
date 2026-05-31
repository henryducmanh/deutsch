<?php
// tutor_dashboard.php — dashboard gia sư: danh sách học viên được gán + form mở note buổi học.
// Router set: $students (mảng {id,username}), $uname.
/** @var array $students */
/** @var string $uname */
$students = $students ?? [];
$uname = $uname ?? '';
$today = date('Y-m-d');
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gia sư — Học viên</title>
<link rel="stylesheet" href="/assets/drill.css">
<style>
  .tutor-card { background:#fff; border:1px solid #e2e4ea; border-radius:12px; padding:16px 18px; margin:12px 0; }
  .tutor-card h2 { margin:0 0 12px; font-size:18px; }
  .note-form { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
  .note-form .field { display:flex; flex-direction:column; gap:4px; }
  .note-form label { font-size:12px; color:#6b7280; }
  .note-form input { padding:8px 10px; border:1px solid #cfd3dc; border-radius:8px; font-size:14px; }
  .note-form input[type=text] { width:130px; }
  .recent-notes { margin-top:12px; font-size:13px; }
  .recent-notes a { display:inline-block; margin:3px 8px 3px 0; padding:4px 9px; background:#eef1f6;
    border-radius:6px; text-decoration:none; color:#374151; }
  .recent-notes a:hover { background:#e0e5ee; }
  .muted { color:#9ca3af; }
</style>
</head>
<body>
<div class="app">
  <div class="list-head">
    <h1>Học viên của tôi</h1>
    <a class="logout-link" href="/logout">Abmelden (<?= h($uname) ?>)</a>
  </div>

  <?php if (empty($students)): ?>
    <p class="muted">Chưa có học viên nào được gán. Liên hệ admin để gán học viên.</p>
  <?php else: ?>
    <?php foreach ($students as $s): ?>
      <?php
        $sid = (int)$s['id'];
        $recent = function_exists('tutor_recent_notes') ? tutor_recent_notes($sid, 8) : [];
      ?>
      <div class="tutor-card">
        <h2><?= h($s['username']) ?></h2>
        <form class="note-form" method="get" action="/tutor/note">
          <input type="hidden" name="student_id" value="<?= $sid ?>">
          <div class="field">
            <label for="lesson_<?= $sid ?>">Bài Hören (lesson_id)</label>
            <input type="text" id="lesson_<?= $sid ?>" name="lesson_id" placeholder="z.B. 4.29"
                   pattern="[A-Za-z0-9._-]{1,16}" required>
          </div>
          <div class="field">
            <label for="date_<?= $sid ?>">Ngày buổi học</label>
            <input type="date" id="date_<?= $sid ?>" name="date" value="<?= h($today) ?>" required>
          </div>
          <button type="submit" class="btn btn-primary">📝 Mở note</button>
        </form>

        <?php if (!empty($recent)): ?>
          <div class="recent-notes">
            <span class="muted">Note gần đây:</span><br>
            <?php foreach ($recent as $n): ?>
              <?php
                $lid = (string)($n['lesson_id'] ?? '');
                $dt  = (string)($n['session_date'] ?? '');
              ?>
              <a href="/tutor/note?student_id=<?= $sid ?>&lesson_id=<?= h(rawurlencode($lid)) ?>&date=<?= h(rawurlencode($dt)) ?>">
                <?= h($lid) ?> · <?= h($dt) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
