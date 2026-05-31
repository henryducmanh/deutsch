<?php
// tutor_note.php — trang note buổi học (Quill editor + polling realtime).
// Truy cập được cả tutor lẫn student (collaboration page). Router đã check notes_can_access().
// Router set: $noteStudentId, $noteLessonId, $noteDate, $noteStudentName, $noteLessonTitle, $uname.
/** @var int $noteStudentId */
/** @var string $noteLessonId */
/** @var string $noteDate */
/** @var string $noteStudentName */
/** @var string $noteLessonTitle */
/** @var string $uname */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Back link: tutor → dashboard, student → lesson.
$isTutor  = (auth_role() === 'tutor');
$backHref = '/lesson/' . rawurlencode($noteLessonId);
$backText = '← Bài học';
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notiz — <?= h($noteLessonTitle) ?> · <?= h($noteDate) ?></title>
<link rel="stylesheet" href="/assets/drill.css">
<!-- Quill 1.3.7 via CDN (không Composer, không npm) -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
  .note-app { max-width:820px; margin:0 auto; padding:16px; }
  .note-head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
  .note-head .meta { font-size:13px; color:#6b7280; }
  .note-head .meta strong { color:#111827; }
  .note-title { font-size:18px; font-weight:600; margin:0; }
  .save-status { font-size:12px; color:#9ca3af; min-width:120px; text-align:right; }
  #editor { background:#fff; min-height:420px; border-radius:0 0 10px 10px; }
  .ql-toolbar.ql-snow { border-radius:10px 10px 0 0; }
  .top-links a { text-decoration:none; color:#374151; font-size:14px; }
</style>
</head>
<body>
<div class="note-app">
  <div class="top-links"><a href="<?= h($backHref) ?>"><?= h($backText) ?></a></div>
  <div class="note-head">
    <div>
      <p class="note-title">📝 <?= h($noteLessonTitle) ?></p>
      <div class="meta">
        Học viên <strong><?= h($noteStudentName) ?></strong>
        · Aufgabe <strong><?= h($noteLessonId) ?></strong>
        · Ngày <strong><?= h($noteDate) ?></strong>
      </div>
    </div>
    <div class="save-status" id="saveStatus">…</div>
  </div>

  <div id="editor"></div>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
  window.NOTE = {
    student_id: <?= (int)$noteStudentId ?>,
    lesson_id:  <?= json_encode($noteLessonId, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    date:       <?= json_encode($noteDate, JSON_HEX_TAG) ?>,
    can_edit:   true
  };
</script>
<script src="/assets/tutor_note.js?v=20260531"></script>
</body>
</html>
