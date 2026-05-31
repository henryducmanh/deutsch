<?php
// lesson_list.php — danh sách bài Hören. Router set $lessons (mảng), $uname.
/** @var array $lessons */
/** @var string $uname */
$lessons = $lessons ?? [];
$uname = $uname ?? '';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hören — Deutsch Web</title>
<link rel="stylesheet" href="/assets/drill.css">
</head>
<body>
<div class="app">
  <div class="list-head">
    <h1>Hören Übungen — DTZ B1</h1>
    <a class="logout-link" href="/logout">Abmelden (<?= h($uname) ?>)</a>
  </div>

  <div class="teil-tabs" id="teilTabs">
    <button class="teil-tab active" data-teil="0">Alle</button>
    <button class="teil-tab" data-teil="1">Teil 1</button>
    <button class="teil-tab" data-teil="2">Teil 2</button>
    <button class="teil-tab" data-teil="3">Teil 3</button>
    <button class="teil-tab" data-teil="4">Teil 4</button>
  </div>

  <?php if (empty($lessons)): ?>
    <p>Chưa có bài nào trong <code>lessons/</code>.</p>
  <?php else: ?>
    <?php foreach ($lessons as $l): ?>
      <a class="lesson-card" data-teil="<?= (int)($l['teil'] ?? 0) ?>" href="/lesson/<?= h($l['lesson_id']) ?>">
        <div>
          <div class="lc-id">Aufgabe <?= h($l['lesson_id']) ?> · <?= h($l['modul']) ?> <?= h($l['niveau']) ?></div>
          <div class="lc-title"><?= h($l['title']) ?></div>
          <?php if (!empty($l['thema'])): ?>
            <div class="lc-thema"><?= h($l['thema']) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($l['best'] !== null): ?>
          <?php $perfect = ($l['best']['correct'] === $l['best']['total'] && $l['best']['total'] > 0); ?>
          <div class="score-badge<?= $perfect ? ' perfect' : '' ?>">
            <?= (int)$l['best']['correct'] ?>/<?= (int)$l['best']['total'] ?>
          </div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<script>
(function(){
  var tabs = document.querySelectorAll('.teil-tab');
  tabs.forEach(function(btn){
    btn.addEventListener('click', function(){
      var t = parseInt(this.dataset.teil, 10);
      tabs.forEach(function(b){ b.classList.remove('active'); });
      this.classList.add('active');
      document.querySelectorAll('.lesson-card').forEach(function(card){
        var ct = parseInt(card.dataset.teil, 10);
        card.style.display = (t === 0 || ct === t) ? '' : 'none';
      });
    });
  });
})();
</script>
</body>
</html>
