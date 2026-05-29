/* drill.js — tách + refactor từ <script> của output/drills/horen_test_4.29-4.31.html (Phase 0).
 *
 * Khác prototype:
 *  - BỎ biến vocabData hardcode. Đọc từ window.LESSON (PHP inject trong drill_horen.php).
 *  - Single-lesson: mỗi trang 1 bài (/lesson/{id}). Bỏ switchLesson đa-tab → id không có hậu tố số.
 *  - Giữ y nguyên logic player / check / transcript / vocab panel / inject marks.
 *  - SỬA BUG regex prototype dòng 820: '(?<![\w...])' bị nuốt \w trong chuỗi JS →
 *    phải double-escape '(?<![\\w\\u00c0-\\u017e])...'.
 *  - Thêm postTrack(): chấm điểm xong tự POST /track event horen_complete; click từ POST word_mark.
 */
(function () {
  'use strict';

  var LESSON = window.LESSON || {};
  var LESSON_ID = LESSON.lesson_id || '';
  // Trạng thái lv hiện tại của từng từ (khởi tạo từ JSON, user click sẽ đổi).
  var wordStatus = {};
  (LESSON.vocab || []).forEach(function (v) { wordStatus[v.w.toLowerCase()] = v.lv || 'new'; });

  // ── POST event lên server (session-authenticated) ──
  function postTrack(type, payload) {
    try {
      fetch('/track', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ type: type, lesson_id: LESSON_ID, payload: payload || {} })
      }).catch(function () { /* offline → bỏ qua, không chặn UX */ });
    } catch (e) { /* noop */ }
  }

  // ── Chấm điểm ──
  function checkLesson() {
    var correct = 0;
    var wrong = [];
    var optionGroups = document.querySelectorAll('.options');
    var allAnswered = true;

    optionGroups.forEach(function (group) {
      if (!group.querySelector('input:checked')) { allAnswered = false; }
    });
    if (!allAnswered) { alert('Bitte alle Aussagen beantworten!'); return; }

    optionGroups.forEach(function (group) {
      var correctVal = group.dataset.correct;
      var aussageId = group.dataset.aussageId || '';
      var selected = group.querySelector('input:checked');
      var options = group.querySelectorAll('.option');

      group.querySelectorAll('input').forEach(function (inp) { inp.disabled = true; });

      var thisCorrect = false;
      options.forEach(function (opt) {
        var inp = opt.querySelector('input');
        if (inp.value === correctVal && inp.checked) {
          opt.classList.add('correct');
          opt.innerHTML += '<span class="mark">✓</span>';
          correct++;
          thisCorrect = true;
        } else if (inp.checked && inp.value !== correctVal) {
          opt.classList.add('wrong');
          opt.innerHTML += '<span class="mark">✗</span>';
        } else if (inp.value === correctVal) {
          opt.classList.add('show-correct');
        }
      });
      if (!thisCorrect) { wrong.push(aussageId); }
    });

    var total = optionGroups.length;
    var badge = document.getElementById('score');
    badge.textContent = correct + '/' + total;
    if (correct === total) badge.classList.add('perfect');

    var bar = document.getElementById('result');
    bar.classList.add('show');
    if (correct === total) {
      bar.className = 'result-bar show perfect';
      bar.textContent = '✓ Perfekt! Alle ' + total + ' Aussagen richtig.';
    } else if (correct >= 2) {
      bar.className = 'result-bar show ok';
      bar.textContent = correct + '/' + total + ' richtig. Fast! Hören Sie die Aussage(n) noch einmal.';
    } else {
      bar.className = 'result-bar show poor';
      bar.textContent = correct + '/' + total + ' richtig. Lesen Sie die Transkription und versuchen Sie es erneut.';
    }

    var dot = document.getElementById('dot');
    if (dot) dot.classList.add('done');
    document.getElementById('check').disabled = true;

    // Tự ghi DB (không reload)
    postTrack('horen_complete', {
      score: correct + '/' + total,
      correct: correct,
      total: total,
      wrong: wrong,
      notes: ''
    });
  }

  function resetLesson() {
    document.querySelectorAll('input[type=radio]').forEach(function (inp) {
      inp.checked = false; inp.disabled = false;
    });
    document.querySelectorAll('.option').forEach(function (opt) {
      // Giữ class .vocab-mark bên trong span; chỉ reset class của chính .option
      opt.className = 'option';
      var mark = opt.querySelector('.mark');
      if (mark) mark.remove();
    });
    var badge = document.getElementById('score');
    badge.textContent = '0/' + document.querySelectorAll('.options').length;
    badge.classList.remove('perfect');
    var bar = document.getElementById('result');
    bar.className = 'result-bar';
    bar.textContent = '';
    document.getElementById('check').disabled = false;
    var box = document.getElementById('transcript');
    if (box) box.classList.remove('show');
    var dot = document.getElementById('dot');
    if (dot) dot.classList.remove('done');
  }

  function toggleTranscript() {
    document.getElementById('transcript').classList.toggle('show');
  }

  // ── Custom Audio Player (single) ──
  var speeds = [0.75, 1, 1.25, 1.5, 2];
  var speedIdx = 1;
  var repeatOn = false;

  function fmt(s) {
    var m = Math.floor(s / 60);
    var sec = Math.floor(s % 60);
    return m + ':' + (sec < 10 ? '0' : '') + sec;
  }

  function initPlayer() {
    var audio = document.getElementById('audio');
    if (!audio || audio._inited) return;
    audio._inited = true;
    audio.addEventListener('timeupdate', function () {
      if (!audio.duration) return;
      var pct = (audio.currentTime / audio.duration) * 100;
      document.getElementById('fill').style.width = pct + '%';
      document.getElementById('dothandle').style.left = pct + '%';
      document.getElementById('cur').textContent = fmt(audio.currentTime);
    });
    audio.addEventListener('loadedmetadata', function () {
      document.getElementById('total').textContent = fmt(audio.duration);
    });
    audio.addEventListener('ended', function () {
      if (repeatOn) { audio.currentTime = 0; audio.play(); }
      else { setPauseIcon(); }
    });
  }

  function togglePlay() {
    var audio = document.getElementById('audio');
    initPlayer();
    if (audio.paused) { audio.play(); setPlayIcon(); }
    else { audio.pause(); setPauseIcon(); }
  }
  function setPlayIcon() {
    document.getElementById('playbtn').innerHTML =
      '<svg width="12" height="14" viewBox="0 0 12 14" fill="currentColor"><rect x="0" y="0" width="4" height="14"/><rect x="8" y="0" width="4" height="14"/></svg>';
  }
  function setPauseIcon() {
    document.getElementById('playbtn').innerHTML =
      '<svg width="14" height="16" viewBox="0 0 14 16" fill="currentColor"><path d="M0 0 L14 8 L0 16 Z"/></svg>';
  }

  function startSeek(e) {
    e.preventDefault();
    var audio = document.getElementById('audio');
    initPlayer();
    function doSeek(ev) {
      var bar = document.getElementById('bar');
      var rect = bar.getBoundingClientRect();
      var clientX = ev.touches ? ev.touches[0].clientX : ev.clientX;
      var pct = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
      if (audio.duration) audio.currentTime = pct * audio.duration;
    }
    doSeek(e);
    function onMove(ev) { doSeek(ev); }
    function onUp() {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('touchmove', onMove);
      document.removeEventListener('mouseup', onUp);
      document.removeEventListener('touchend', onUp);
    }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('touchmove', onMove, { passive: false });
    document.addEventListener('mouseup', onUp);
    document.addEventListener('touchend', onUp);
  }

  function toggleRepeat() {
    repeatOn = !repeatOn;
    document.getElementById('repeat').classList.toggle('active', repeatOn);
  }
  function cycleSpeed() {
    speedIdx = (speedIdx + 1) % speeds.length;
    var s = speeds[speedIdx];
    document.getElementById('audio').playbackRate = s;
    document.getElementById('speed').textContent = s + 'x';
  }
  function toggleMute() {
    var audio = document.getElementById('audio');
    audio.muted = !audio.muted;
    document.getElementById('vol').innerHTML = audio.muted ? '🔇' : '🔊';
  }

  // Sticky shadow on scroll
  window.addEventListener('scroll', function () {
    document.querySelectorAll('.audio-wrap').forEach(function (el) {
      el.classList.toggle('pinned', el.getBoundingClientRect().top <= 10);
    });
  });

  // Highlight selected option on click
  function wireOptionHighlight() {
    document.querySelectorAll('.options').forEach(function (group) {
      group.querySelectorAll('input').forEach(function (inp) {
        inp.addEventListener('change', function () {
          group.querySelectorAll('.option').forEach(function (opt) { opt.classList.remove('selected'); });
          inp.parentElement.classList.add('selected');
        });
      });
    });
  }

  // ── Vocab Panel ──
  var vocabOpen = false;
  var hlOn = false;
  var lvNum = { new: '1', ok: '2', hard: '3' };

  function renderVocab() {
    var list = document.getElementById('vocabList');
    var items = LESSON.vocab || [];
    list.innerHTML = items.map(function (v) {
      var wKey = v.w.toLowerCase();
      var lv = wordStatus[wKey] || v.lv || 'new';
      return '<div class="vocab-item" data-word="' + wKey + '">' +
        '<div class="vocab-lv lv-' + lv + '" data-word="' + wKey + '" title="Click: đổi trạng thái new→hard→ok">' + (lvNum[lv] || '1') + '</div>' +
        '<div class="vocab-text" style="cursor:pointer" data-word="' + wKey + '">' +
          '<span class="vocab-word">' + v.w + '</span>' +
          '<span class="vocab-art">' + (v.art || '') + '</span>' +
          '<div class="vocab-meaning">' + (v.m || '') + '</div>' +
        '</div></div>';
    }).join('');
    var title = LESSON.title ? ('Vokabeln — Bài ' + LESSON_ID + ' — ' + LESSON.title) : 'Vokabeln';
    document.getElementById('vocabPanelTitle').textContent = title;

    // Wire click: badge cycle status (+POST), text → selectWord (highlight)
    list.querySelectorAll('.vocab-lv').forEach(function (el) {
      el.addEventListener('click', function (e) { e.stopPropagation(); cycleWordStatus(el.dataset.word); });
    });
    list.querySelectorAll('.vocab-text').forEach(function (el) {
      el.addEventListener('click', function () { selectWord(el.dataset.word); });
    });
  }

  function toggleVocab() { if (vocabOpen) closeVocab(); else openVocab(); }

  function openVocab() {
    renderVocab();
    injectMarks();
    document.getElementById('vocabPanel').classList.add('open');
    document.getElementById('vocabToggleBtn').classList.add('active', 'panel-open');
    vocabOpen = true;
  }
  function closeVocab() {
    document.getElementById('vocabPanel').classList.remove('open');
    document.getElementById('vocabToggleBtn').classList.remove('active', 'panel-open');
    vocabOpen = false;
  }

  function toggleHighlight() {
    hlOn = !hlOn;
    document.body.classList.toggle('hl-on', hlOn);
    document.getElementById('hlToggleBtn').classList.toggle('on', hlOn);
  }

  // ── Inject <span class="vocab-mark"> vào option text + transcript ──
  var marksInjected = false;
  function escapeReg(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

  function injectMarks() {
    if (marksInjected) return;
    marksInjected = true;
    var words = (LESSON.vocab || []).map(function (v) { return v.w; });
    words.sort(function (a, b) { return b.length - a.length; }); // dài trước, tránh partial match

    var targets = document.querySelectorAll('.option span, .transcript-box p');
    targets.forEach(function (el) {
      var html = el.innerHTML;
      words.forEach(function (w) {
        // FIX double-escape \\w + dải ký tự có dấu Đức/tiếng Việt
        var re = new RegExp('(?<![\\w\\u00c0-\\u024f])(' + escapeReg(w) + ')(?![\\w\\u00c0-\\u024f])', 'gi');
        html = html.replace(re, '<span class="vocab-mark" data-word="' + w.toLowerCase() + '" title="' + w + '">$1</span>');
      });
      el.innerHTML = html;
    });
    // Click mark trong đề/transcript → selectWord
    document.querySelectorAll('.vocab-mark').forEach(function (m) {
      m.addEventListener('click', function () { selectWord(m.dataset.word); });
    });
  }

  function selectWord(wKey) {
    document.querySelectorAll('.vocab-mark.hl-selected').forEach(function (m) { m.classList.remove('hl-selected'); });
    var targets = document.querySelectorAll('.vocab-mark[data-word="' + wKey + '"]');
    targets.forEach(function (m) { m.classList.add('hl-selected'); });
    if (targets.length > 0) targets[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.querySelectorAll('.vocab-item').forEach(function (item) {
      item.classList.toggle('hl-active', item.dataset.word === wKey);
    });
  }

  // ── Cycle trạng thái từ + POST word_mark ──
  var cycleOrder = ['new', 'hard', 'ok']; // click 'new' → 'hard' (đánh dấu chưa biết) → 'ok' → 'new'
  function cycleWordStatus(wKey) {
    var cur = wordStatus[wKey] || 'new';
    var next = cycleOrder[(cycleOrder.indexOf(cur) + 1) % cycleOrder.length];
    wordStatus[wKey] = next;

    // Cập nhật badge trong panel
    var badge = document.querySelector('.vocab-lv[data-word="' + wKey + '"]');
    if (badge) {
      badge.className = 'vocab-lv lv-' + next;
      badge.textContent = lvNum[next] || '1';
    }

    var word = wordFromKey(wKey);
    postTrack('word_mark', {
      word: word,
      word_status: next,
      context: contextFor(wKey),
      vocab_id: vocabIdFor(wKey)
    });
  }

  function wordFromKey(wKey) {
    var hit = (LESSON.vocab || []).filter(function (v) { return v.w.toLowerCase() === wKey; })[0];
    return hit ? hit.w : wKey;
  }
  function vocabIdFor(wKey) {
    var hit = (LESSON.vocab || []).filter(function (v) { return v.w.toLowerCase() === wKey; })[0];
    return hit ? (hit.vocab_id != null ? hit.vocab_id : null) : null;
  }
  function contextFor(wKey) {
    // Câu transcript / option đầu tiên chứa từ → làm context
    var word = wordFromKey(wKey);
    var hay = (LESSON.transcript || []).map(function (t) { return t.text; });
    (LESSON.aussagen || []).forEach(function (a) {
      (a.options || []).forEach(function (o) { hay.push(o.text); });
    });
    for (var i = 0; i < hay.length; i++) {
      if (hay[i] && hay[i].toLowerCase().indexOf(wKey) !== -1) return hay[i];
    }
    return word;
  }

  // ── Expose handlers cho inline onclick trong view ──
  window.togglePlay = togglePlay;
  window.startSeek = startSeek;
  window.toggleRepeat = toggleRepeat;
  window.cycleSpeed = cycleSpeed;
  window.toggleMute = toggleMute;
  window.checkLesson = checkLesson;
  window.resetLesson = resetLesson;
  window.toggleTranscript = toggleTranscript;
  window.toggleVocab = toggleVocab;
  window.closeVocab = closeVocab;
  window.toggleHighlight = toggleHighlight;

  document.addEventListener('DOMContentLoaded', wireOptionHighlight);
})();
