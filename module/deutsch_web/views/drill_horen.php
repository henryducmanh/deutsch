<?php
// drill_horen.php — render 1 bài Hören từ lesson JSON (server-side aussagen/transcript)
// + inject window.LESSON cho drill.js (vocab panel, track). Khung HTML từ prototype.
/** @var array $lesson */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$lid    = $lesson['lesson_id'] ?? '';
$title  = $lesson['title'] ?? $lid;
$modul  = $lesson['modul'] ?? 'Hören';
$niveau = $lesson['niveau'] ?? '';
$instr  = $lesson['instructions'] ?? '';
$audio  = $lesson['audio']['url'] ?? '';
$aussagen   = $lesson['aussagen'] ?? [];
$transcript = $lesson['transcript'] ?? [];
$total = count($aussagen);

// Link mở note buổi học (collaborative tutor note) — student_id = user hiện tại, date = hôm nay.
$noteUid  = (int)auth_user_id();
$noteHref = '/tutor/note?lesson_id=' . rawurlencode($lid)
          . '&student_id=' . $noteUid
          . '&date=' . date('Y-m-d');
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> — Aufgabe <?= h($lid) ?></title>
<?php $assetV = '20260530l'; ?>
<link rel="stylesheet" href="/assets/drill.css?v=<?= $assetV ?>">
</head>
<body>
<div class="app">

  <div class="tabs">
    <a class="tab" href="/">← Alle Übungen</a>
    <a class="tab" href="<?= h($noteHref) ?>">📝 Notizen</a>
  </div>

  <div class="lesson active" id="lesson">
    <div class="lesson-header">
      <div>
        <div class="lesson-title"><?= h($title) ?></div>
        <div class="lesson-source">Aufgabe <?= h($lid) ?> · DTZ <?= h($niveau) ?> · <?= h($modul) ?></div>
      </div>
      <div class="score-badge" id="score">0/<?= (int)$total ?></div>
    </div>

    <?php if ($instr !== ''): ?>
      <div class="instructions"><?= h($instr) ?></div>
    <?php endif; ?>

    <div class="audio-wrap" id="player">
      <audio id="audio" src="<?= h($audio) ?>" preload="none"></audio>
      <button class="play-btn" id="playbtn" onclick="togglePlay()" title="Play / Pause">
        <svg width="14" height="16" viewBox="0 0 14 16" fill="currentColor"><path d="M0 0 L14 8 L0 16 Z"/></svg>
      </button>
      <span class="time-cur" id="cur">0:00</span>
      <div class="progress-bar" id="bar" onmousedown="startSeek(event)" ontouchstart="startSeek(event)">
        <div class="progress-fill" id="fill"></div>
        <div class="progress-dot" id="dothandle"></div>
      </div>
      <span class="time-total" id="total">0:00</span>
      <button class="ctrl-btn repeat-btn" id="repeat" onclick="toggleRepeat()" title="Lặp lại">&#x21BA;</button>
      <button class="ctrl-btn speed-btn" id="speed" onclick="cycleSpeed()">1x</button>
      <button class="ctrl-btn vol-btn" id="vol" onclick="toggleMute()" title="Tắt tiếng">&#x1F50A;</button>
    </div>

    <div class="aussagen" id="aussagen">
      <?php foreach ($aussagen as $i => $a): ?>
        <?php $num = $i + 1; $name = 'q-' . $num; ?>
        <div class="aussage-block">
          <div class="aussage-label"><?= $num ?>. <?= h($a['label'] ?? ('Aussage ' . $num)) ?></div>
          <div class="options" data-aussage-id="<?= h($a['id'] ?? '') ?>" data-correct="<?= h($a['correct'] ?? '') ?>">
            <?php foreach (($a['options'] ?? []) as $opt): ?>
              <label class="option">
                <input type="radio" name="<?= h($name) ?>" value="<?= h($opt['key'] ?? '') ?>">
                <span><?= h($opt['key'] ?? '') ?>) <?= h($opt['text'] ?? '') ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($transcript)): ?>
      <div class="transcript-toggle">
        <button class="btn-transcript" onclick="toggleTranscript()">🎧 Transkription anzeigen</button>
        <div class="transcript-box" id="transcript">
          <?php foreach ($transcript as $t): ?>
            <?php
              $label = $t['label'] ?? '';
              $text  = $t['text'] ?? '';
              $key   = $t['key_phrase'] ?? '';
              // Bôi đậm key_phrase (như prototype) — escape rồi mới chèn <strong>.
              $safe = h($text);
              if ($key !== '' && mb_strpos($text, $key) !== false) {
                  $safeKey = h($key);
                  $safe = str_replace($safeKey, '<strong>' . $safeKey . '</strong>', $safe);
              }
            ?>
            <p><strong><?= h($label) ?>:</strong> <?= $safe ?></p>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="result-bar" id="result"></div>
    <div class="btn-row">
      <button class="btn btn-secondary" onclick="resetLesson()">↺ Neu starten</button>
      <button class="btn btn-primary" id="check" onclick="checkLesson()">Prüfen</button>
    </div>
  </div>

  <div class="progress-dots">
    <div class="dot active" id="dot"></div>
  </div>
</div>

<button class="vocab-btn" id="vocabToggleBtn" onclick="toggleVocab()">
  <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
    <rect x="3" y="2" width="14" height="16" rx="2"/>
    <line x1="7" y1="7" x2="13" y2="7"/>
    <line x1="7" y1="10" x2="13" y2="10"/>
    <line x1="7" y1="13" x2="11" y2="13"/>
  </svg>
  Vokabeln
</button>

<div class="vocab-panel" id="vocabPanel">
  <div class="vocab-head">
    <div class="vocab-head-row1">
      <button class="vocab-collapse-btn" onclick="closeVocab()" title="Thu bảng từ vựng">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <polyline points="9,2 13,7 9,12"/>
          <line x1="1" y1="7" x2="13" y2="7"/>
        </svg>
      </button>
      <div class="vocab-head-title" id="vocabPanelTitle">Vokabeln</div>
      <div class="vocab-head-actions">
        <button class="vocab-hl-btn" id="hlToggleBtn" onclick="toggleHighlight()" title="Bật/tắt nền vàng">☀ Nền vàng</button>
      </div>
    </div>
    <div class="vocab-head-tabs">
      <span class="vocab-tab active" id="tabAll" data-tab="all">Alle Wörter</span>
      <span class="vocab-tab" id="tabNew" data-tab="new">Neu wort</span>
    </div>
  </div>
  <div class="vocab-list" id="vocabList"></div>
  <div class="vocab-list vocab-new-list" id="vocabNewList" style="display:none"></div>
</div>

<script>
  window.LESSON = <?= json_encode($lesson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
</script>
<script src="/assets/drill.js?v=<?= $assetV ?>"></script>
</body>
</html>
