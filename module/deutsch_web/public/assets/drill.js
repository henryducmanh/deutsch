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
  // vocabData = danh sách từ đang hiển thị panel. Khởi tạo từ JSON (fallback),
  // sau đó loadVocabFromDB() merge nghĩa/art/level từ DB đè lên (Phase 2).
  var vocabData = (LESSON.vocab || []).map(function (v) {
    return { w: v.w, art: v.art, m: v.m, lv: v.lv, vocab_id: (v.vocab_id != null ? v.vocab_id : null) };
  });
  // Trạng thái lv hiện tại của từng từ (khởi tạo từ JSON, user click sẽ đổi).
  var wordStatus = {};
  vocabData.forEach(function (v) { wordStatus[v.w.toLowerCase()] = v.lv || 'new'; });

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

  // ── Phase 2: load nghĩa vocab từ DB, merge đè JSON ──
  // Set wort_key đã biết (có trong DB) — Phase 3 dùng để lọc "Neu wort".
  var knownKeys = {};
  function loadVocabFromDB() {
    var words = vocabData.map(function (v) { return v.w; });
    if (words.length === 0) { return Promise.resolve(); }
    return fetch('/api/vocab?words=' + encodeURIComponent(words.join(',')), {
      credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    }).then(function (r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    }).then(function (data) {
      var rows = (data && data.vocab) || [];
      var byKey = {};
      rows.forEach(function (row) {
        var k = (row.wort_key || row.w || '').toLowerCase();
        if (k) { byKey[k] = row; knownKeys[k] = true; }
      });
      // Merge: DB đè nghĩa/art/vocab_id/level lên JSON. Từ không có trong DB giữ JSON.
      vocabData.forEach(function (v) {
        var hit = byKey[v.w.toLowerCase()];
        if (!hit) { return; }
        if (hit.bedeutung != null && hit.bedeutung !== '') { v.m = hit.bedeutung; }
        if (hit.art != null && hit.art !== '') { v.art = hit.art; }
        if (hit.vocab_id != null) { v.vocab_id = hit.vocab_id; }
      });
      // Nếu panel đang mở → re-render để hiện nghĩa DB ngay.
      if (vocabOpen) { renderVocab(); }
      refreshNeuIfOpen();   // known set đầy đủ → lọc lại "Neu wort" nếu đang mở
    }).catch(function () {
      // offline / 401 → giữ nguyên vocabData từ JSON (fallback §9 bước 5). Không chặn UX.
      refreshNeuIfOpen();
    });
  }

  function renderVocab() {
    var list = document.getElementById('vocabList');
    var items = vocabData || [];
    if (items.length === 0) {
      list.innerHTML = '<div class="vocab-empty">Chưa có từ vựng cho bài này.</div>';
      var t0 = LESSON.title ? ('Vokabeln — Bài ' + LESSON_ID + ' — ' + LESSON.title) : 'Vokabeln';
      document.getElementById('vocabPanelTitle').textContent = t0;
      return;
    }
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
    var words = (vocabData || []).map(function (v) { return v.w; });
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
    var hit = (vocabData || []).filter(function (v) { return v.w.toLowerCase() === wKey; })[0];
    return hit ? hit.w : wKey;
  }
  function vocabIdFor(wKey) {
    var hit = (vocabData || []).filter(function (v) { return v.w.toLowerCase() === wKey; })[0];
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

  // ── Phase 3: tab "Neu wort" — gợi ý từ lạ trong bài + form thêm ──
  var STOPWORDS = (function () {
    var arr = ['die','der','das','ein','eine','ist','sind','hat','haben','wird','werden',
      'und','oder','aber','mit','für','auf','in','an','bei','von','zu','aus','über','unter',
      'nach','vor','durch','um','ich','du','er','sie','es','wir','ihr','mich','mir','sich',
      'uns','euch','nicht','auch','noch','schon','sehr','so','wie','dass','wenn','weil','ob',
      'als','mehr','viel','gut','groß','klein','alt','neu','man','kann','muss','darf','soll'];
    var s = {};
    arr.forEach(function (w) { s[w] = true; });
    return s;
  })();
  var wortartAbbr = { 'Substantiv': 'Subst.', 'Verb': 'Verb', 'Adjektiv': 'Adj.', 'Adverb': 'Adv.' };

  function jsArt(artikel, wortart) {
    var parts = [];
    if (artikel) { parts.push(artikel); }
    if (wortart) { parts.push(wortartAbbr[wortart] || wortart); }
    return parts.join(' · ');
  }

  function tokenize(text) {
    return (text || '').match(/[A-Za-zÀ-ɏ]+/g) || [];
  }

  // Set wort_key đã biết = vocabData (panel) + knownKeys (DB response).
  function buildKnownSet() {
    var s = {};
    vocabData.forEach(function (v) { s[v.w.toLowerCase()] = true; });
    Object.keys(knownKeys).forEach(function (k) { s[k] = true; });
    return s;
  }

  function collectCandidates() {
    var texts = [];
    (LESSON.aussagen || []).forEach(function (a) {
      (a.options || []).forEach(function (o) { if (o.text) { texts.push(o.text); } });
    });
    (LESSON.transcript || []).forEach(function (t) { if (t.text) { texts.push(t.text); } });

    var known = buildKnownSet();
    var seen = {};
    var out = [];
    texts.forEach(function (txt) {
      tokenize(txt).forEach(function (tok) {
        if (tok.length < 2) { return; }
        var key = tok.toLowerCase();
        if (seen[key] || known[key] || STOPWORDS[key]) { return; }
        var startsUpper = /^[A-ZÄÖÜ]/.test(tok);     // Substantiv viết hoa
        if (!startsUpper && tok.length <= 6) { return; }
        seen[key] = true;
        out.push(tok);
      });
    });
    out.sort(function (a, b) { return a.toLowerCase().localeCompare(b.toLowerCase()); });
    return out;
  }

  function renderNewWords() {
    var list = document.getElementById('vocabNewList');
    if (!list) { return; }
    var cands = collectCandidates();
    if (cands.length === 0) {
      list.innerHTML = '<div class="vocab-empty">Không có từ lạ trong bài (tất cả đã có trong DB).</div>';
      return;
    }
    list.innerHTML = cands.map(function (w) {
      return '<div class="vocab-new-item" data-word="' + w + '">' +
        '<div class="vnw-word">' + w + '</div>' +
        '<div class="vnw-form">' +
          '<select class="vnw-art" title="Artikel"><option value="">—</option>' +
            '<option value="der">der</option><option value="die">die</option><option value="das">das</option></select>' +
          '<input class="vnw-mean" type="text" placeholder="Nghĩa (tiếng Việt)">' +
          '<select class="vnw-wortart" title="Wortart">' +
            '<option value="Substantiv">Subst.</option><option value="Verb">Verb</option>' +
            '<option value="Adjektiv">Adj.</option><option value="Adverb">Adv.</option></select>' +
          '<button class="vnw-add" type="button">Thêm</button>' +
        '</div></div>';
    }).join('');
    list.querySelectorAll('.vocab-new-item').forEach(function (item) {
      item.querySelector('.vnw-add').addEventListener('click', function () { addNewWord(item); });
      item.querySelector('.vnw-mean').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { addNewWord(item); }
      });
    });
  }

  function addNewWord(item) {
    var wort    = item.dataset.word;
    var mean    = item.querySelector('.vnw-mean').value.trim();
    var artikel = item.querySelector('.vnw-art').value;
    var wortart = item.querySelector('.vnw-wortart').value;
    var btn     = item.querySelector('.vnw-add');
    btn.disabled = true; btn.textContent = '…';

    fetch('/api/vocab', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        wort: wort, bedeutung: mean, wortart: wortart, artikel: artikel, source_lesson: LESSON_ID
      })
    }).then(function (r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    }).then(function (data) {
      var key = wort.toLowerCase();
      knownKeys[key] = true;
      vocabData.push({ w: wort, art: jsArt(artikel, wortart), m: mean, lv: 'new',
                       vocab_id: (data && data.id ? data.id : null) });
      wordStatus[key] = 'new';
      if (item.parentNode) { item.parentNode.removeChild(item); }
      renderVocab();
      switchTab('all');   // hiện ngay trong "Alle Wörter"
    }).catch(function () {
      btn.disabled = false; btn.textContent = 'Thêm';
      alert('Không thêm được từ. Kiểm tra kết nối / đăng nhập rồi thử lại.');
    });
  }

  function switchTab(which) {
    var tabAll = document.getElementById('tabAll');
    var tabNew = document.getElementById('tabNew');
    var listAll = document.getElementById('vocabList');
    var listNew = document.getElementById('vocabNewList');
    if (!tabAll || !tabNew) { return; }
    if (which === 'new') {
      tabNew.classList.add('active'); tabAll.classList.remove('active');
      listAll.style.display = 'none'; listNew.style.display = '';
      renderNewWords();
    } else {
      tabAll.classList.add('active'); tabNew.classList.remove('active');
      listNew.style.display = 'none'; listAll.style.display = '';
      renderVocab();
    }
  }

  function wireTabs() {
    var tabAll = document.getElementById('tabAll');
    var tabNew = document.getElementById('tabNew');
    if (tabAll) { tabAll.addEventListener('click', function () { switchTab('all'); }); }
    if (tabNew) { tabNew.addEventListener('click', function () { switchTab('new'); }); }
  }

  // Nếu đang ở tab Neu wort → refresh sau khi DB load xong (known set đầy đủ).
  function refreshNeuIfOpen() {
    var listNew = document.getElementById('vocabNewList');
    if (listNew && listNew.style.display !== 'none') { renderNewWords(); }
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

  document.addEventListener('DOMContentLoaded', function () {
    wireOptionHighlight();
    wireTabs();          // Phase 3: tab Alle Wörter / Neu wort
    loadVocabFromDB();   // Phase 2: enrich nghĩa từ DB (fallback JSON nếu fail)
  });
})();
