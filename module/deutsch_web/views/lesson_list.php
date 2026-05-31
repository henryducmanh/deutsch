<?php
// lesson_list.php — danh sách bài Hören. Router set $lessons (mảng), $uname.
/** @var array $lessons */
/** @var string $uname */
$lessons = $lessons ?? [];
$uname   = $uname ?? '';
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hören — Deutsch Web</title>
<link rel="stylesheet" href="/assets/drill.css">
<style>
/* ── Breadcrumb ── */
.breadcrumb { font-size:13px; color:#6b7280; padding:10px 0 4px; }
.breadcrumb a { color:#e05a2b; text-decoration:none; }
.breadcrumb a:hover { text-decoration:underline; }
.breadcrumb .bc-sep { margin:0 5px; color:#d1d5db; }
.breadcrumb .bc-cur { color:#111827; font-weight:600; }

/* ── 2-column layout ── */
.list-body { display:flex; gap:16px; align-items:flex-start; margin-top:8px; }
.lessons-col { flex:1; min-width:0; }

/* ── Thema sidebar ── */
.thema-sidebar {
  width:190px; flex-shrink:0;
  background:#fff; border:1px solid #e2e4ea; border-radius:12px;
  padding:12px; position:sticky; top:16px;
}
.thema-sidebar-title {
  font-size:11px; font-weight:700; color:#9ca3af;
  text-transform:uppercase; letter-spacing:.6px; margin-bottom:8px;
}
.thema-btn {
  display:block; width:100%; text-align:left;
  background:none; border:none; border-radius:8px;
  padding:7px 10px; font-size:13px; color:#374151;
  cursor:pointer; margin-bottom:2px;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.thema-btn:hover { background:#f3f4f6; }
.thema-btn.active { background:#fff3ed; color:#e05a2b; font-weight:600; }
</style>
</head>
<body>
<?php require __DIR__ . '/_tutor_banner.php'; ?>
<div class="app">

  <div class="list-head">
    <h1>Hören Übungen — DTZ B1</h1>
    <a class="logout-link" href="/logout">Abmelden (<?= h($uname) ?>)</a>
  </div>

  <!-- Breadcrumb -->
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="/">Deutsch B1 DTZ</a>
    <span class="bc-sep">→</span>
    <span>Hören</span>
    <span class="bc-sep">→</span>
    <span class="bc-cur" id="bcTeil">Alle Übungen</span>
  </nav>

  <!-- Teil tabs -->
  <div class="teil-tabs" id="teilTabs">
    <button class="teil-tab active" data-teil="0">Alle</button>
    <button class="teil-tab" data-teil="1">Teil 1</button>
    <button class="teil-tab" data-teil="2">Teil 2</button>
    <button class="teil-tab" data-teil="3">Teil 3</button>
    <button class="teil-tab" data-teil="4">Teil 4</button>
  </div>

  <div class="list-body">

    <!-- Thema sidebar (ẩn khi "Alle" hoặc không có thema lặp) -->
    <aside class="thema-sidebar" id="themaSidebar" style="display:none" aria-label="Filter theo Thema">
      <div class="thema-sidebar-title">Thema</div>
      <div id="themaList"></div>
    </aside>

    <!-- Danh sách bài -->
    <div class="lessons-col" id="lessonsCol">
      <?php if (empty($lessons)): ?>
        <p>Chưa có bài nào trong <code>lessons/</code>.</p>
      <?php else: ?>
        <?php foreach ($lessons as $l): ?>
          <a class="lesson-card"
             data-teil="<?= (int)($l['teil'] ?? 0) ?>"
             data-lid="<?= h($l['lesson_id']) ?>"
             data-thema="<?= h($l['thema'] ?? '') ?>"
             href="/lesson/<?= h($l['lesson_id']) ?>">
            <div>
              <div class="lc-id">Aufgabe <?= h($l['lesson_id']) ?> · <?= h($l['modul']) ?> <?= h($l['niveau']) ?></div>
              <div class="lc-title"><?= h($l['title']) ?></div>
              <?php
                $thema = $l['thema'] ?? '';
                $title = $l['title'] ?? '';
                // Chỉ hiện thema nếu khác title (tránh lặp)
                if ($thema !== '' && $thema !== $title):
              ?>
                <div class="lc-thema"><?= h($thema) ?></div>
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

  </div><!-- /.list-body -->
</div><!-- /.app -->

<script>
(function(){
  var col       = document.getElementById('lessonsCol');
  var sidebar   = document.getElementById('themaSidebar');
  var themaList = document.getElementById('themaList');
  var bcTeil    = document.getElementById('bcTeil');
  var tabs      = document.querySelectorAll('.teil-tab');

  var LABEL = { 0:'Alle Übungen', 1:'Teil 1', 2:'Teil 2', 3:'Teil 3', 4:'Teil 4' };

  // ── Numeric sort: "1.10" > "1.9", "1.101" > "1.10" ──────────────────────
  function parseLid(lid) {
    var p = String(lid).split('.');
    return [ parseInt(p[0]) || 0, parseInt(p[1]) || 0 ];
  }
  function sortCards(cards) {
    Array.from(cards)
      .sort(function(a, b) {
        var pa = parseLid(a.dataset.lid);
        var pb = parseLid(b.dataset.lid);
        return pa[0] !== pb[0] ? pa[0] - pb[0] : pa[1] - pb[1];
      })
      .forEach(function(c) { col.appendChild(c); });
  }

  // ── Thema sidebar ─────────────────────────────────────────────────────────
  function buildSidebar(cards) {
    themaList.innerHTML = '';
    sidebar.style.display = 'none';
    if (!cards.length) return;

    // Đếm tần suất mỗi thema
    var counts = {}, order = [];
    cards.forEach(function(c) {
      var t = c.dataset.thema;
      if (!t) return;
      if (counts[t] === undefined) { counts[t] = 0; order.push(t); }
      counts[t]++;
    });

    // Chỉ hiện sidebar khi có ít nhất 1 thema xuất hiện ≥ 2 lần
    var hasDup = order.some(function(t) { return counts[t] > 1; });
    if (!hasDup || order.length < 2) return;

    // "Alle" button
    var allBtn = document.createElement('button');
    allBtn.className = 'thema-btn active';
    allBtn.textContent = 'Alle';
    allBtn.dataset.thema = '';
    themaList.appendChild(allBtn);

    order.forEach(function(t) {
      var btn = document.createElement('button');
      btn.className = 'thema-btn';
      btn.textContent = t.length > 24 ? t.slice(0, 22) + '…' : t;
      btn.title = t;
      btn.dataset.thema = t;
      themaList.appendChild(btn);
    });

    sidebar.style.display = '';

    // Filter khi click thema
    themaList.addEventListener('click', function(e) {
      var btn = e.target.closest('.thema-btn');
      if (!btn) return;
      themaList.querySelectorAll('.thema-btn').forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var filter = btn.dataset.thema;
      cards.forEach(function(c) {
        c.style.display = (!filter || c.dataset.thema === filter) ? '' : 'none';
      });
    });
  }

  // ── Tab click ─────────────────────────────────────────────────────────────
  tabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
      var t = parseInt(this.dataset.teil, 10);

      // Active state
      tabs.forEach(function(b) { b.classList.remove('active'); });
      this.classList.add('active');

      // Breadcrumb
      if (bcTeil) bcTeil.textContent = LABEL[t] || 'Teil ' + t;

      // Show/hide cards, collect visible
      var all     = col.querySelectorAll('.lesson-card');
      var visible = [];
      all.forEach(function(c) {
        var ct   = parseInt(c.dataset.teil, 10);
        var show = (t === 0 || ct === t);
        c.style.display = show ? '' : 'none';
        if (show) visible.push(c);
      });

      // Sort numerically
      sortCards(visible);

      // Sidebar
      if (t === 0) { sidebar.style.display = 'none'; themaList.innerHTML = ''; }
      else         { buildSidebar(visible); }
    });
  });

  // ── Khởi tạo: sort all cards ngay khi tải trang ──────────────────────────
  sortCards(col.querySelectorAll('.lesson-card'));
})();
</script>
</body>
</html>
