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
  // ── Phase 4: biến thể đã biết (inflected forms) ──
  // formMap: form_key → {form, lemma_key, lemma, lemma_id, form_type, art, bedeutung}
  // formsByLemma: lemma_key → [ {form, form_type}, ... ] (để hiện dòng phụ "↳ ..." dưới lemma)
  var formMap = {};
  var formsByLemma = {};
  function loadVocabFromDB() {
    var words = vocabData.map(function (v) { return v.w; });
    // Phase 2: fetch nghĩa từ DB cho các từ trong lesson JSON
    var p1 = words.length === 0 ? Promise.resolve([]) :
      fetch('/api/vocab?words=' + encodeURIComponent(words.join(',')), {
        credentials: 'same-origin', headers: { 'Accept': 'application/json' }
      }).then(function (r) { return r.ok ? r.json() : { vocab: [] }; })
        .then(function (d) { return (d && d.vocab) || []; })
        .catch(function () { return []; });

    // Phase 2 + 3: fetch queued words (curated=0) đã queue cho bài này → persist sau reload
    var p2 = LESSON_ID ? fetch('/api/vocab/queued?lesson_id=' + encodeURIComponent(LESSON_ID), {
      credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    }).then(function (r) { return r.ok ? r.json() : { vocab: [] }; })
      .then(function (d) { return (d && d.vocab) || []; })
      .catch(function () { return []; }) : Promise.resolve([]);

    Promise.all([p1, p2]).then(function (results) {
      var dbRows   = results[0];   // từ lesson JSON matched trong DB
      var queued   = results[1];   // từ đã queue (curated=0) cho bài này

      // Merge DB rows vào vocabData (đè nghĩa/art)
      var byKey = {};
      dbRows.forEach(function (row) {
        var k = (row.wort_key || row.w || '').toLowerCase();
        if (k) { byKey[k] = row; knownKeys[k] = true; }
      });
      vocabData.forEach(function (v) {
        var hit = byKey[v.w.toLowerCase()];
        if (!hit) { return; }
        if (hit.bedeutung != null && hit.bedeutung !== '') { v.m = hit.bedeutung; }
        if (hit.art != null && hit.art !== '') { v.art = hit.art; }
        if (hit.vocab_id != null) { v.vocab_id = hit.vocab_id; }
      });

      // Append queued words vào vocabData nếu chưa có (tránh dup)
      queued.forEach(function (q) {
        var k = (q.wort_key || q.w || '').toLowerCase();
        if (!k || knownKeys[k]) { return; }   // đã có trong lesson vocab → skip
        knownKeys[k] = true;
        wordStatus[k] = 'new';
        vocabData.push({
          w: q.w, art: q.art || '?',
          m: (q.bedeutung && q.bedeutung !== '') ? q.bedeutung : '— (chưa tra)',
          lv: 'new', vocab_id: q.vocab_id,
          queued: q.curated === 0  // chỉ badge "?" nếu chưa dịch
        });
      });

      // Bước 3 (global scan, LingQ-style): đánh dấu từ đã học ở BÀI KHÁC → lọc "Neu wort".
      //   chạy TRƯỚC loadFormsFromDB để base word đã biết global không lọt vào query forms.
      // Phase 4: fetch biến thể đã biết (forms) cho token còn lại → formMap.
      loadGlobalKnownFromDB().then(function () {
        return loadFormsFromDB();
      }).then(function () {
        if (vocabOpen) { renderVocab(); }
        if (hlOn) { stripMarks(); marksInjected = false; injectMarks(); }  // re-inject để hiện form-mark
        refreshNeuIfOpen();
      });
    });
  }

  // Gom mọi token trong bài (option + transcript) CHƯA có trong knownKeys → query forms.
  function collectUnknownTokens() {
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
        seen[key] = true;
        out.push(tok);
        if (out.length >= 100) { return; }   // cap §5 (≤ 100 words/request)
      });
    });
    return out.slice(0, 100);
  }

  // Phase 4: gọi GET /api/vocab/forms → build formMap + formsByLemma.
  function loadFormsFromDB() {
    var tokens = collectUnknownTokens();
    if (tokens.length === 0) { return Promise.resolve(); }
    return fetch('/api/vocab/forms?words=' + encodeURIComponent(tokens.join(',')), {
      credentials: 'same-origin', headers: { 'Accept': 'application/json' }
    }).then(function (r) { return r.ok ? r.json() : { forms: [] }; })
      .then(function (d) {
        var forms = (d && d.forms) || [];
        forms.forEach(function (f) {
          var fk = (f.form_key || f.form || '').toLowerCase();
          if (!fk) { return; }
          formMap[fk] = {
            form: f.form, lemma_key: (f.lemma_key || '').toLowerCase(), lemma: f.lemma,
            lemma_id: f.lemma_id, form_type: f.form_type, art: f.art, bedeutung: f.bedeutung
          };
          var lk = (f.lemma_key || '').toLowerCase();
          if (lk) {
            if (!formsByLemma[lk]) { formsByLemma[lk] = []; }
            formsByLemma[lk].push({ form: f.form, form_type: f.form_type });
          }
        });
      })
      .catch(function () { /* offline → bỏ qua Phase 4, không chặn panel */ });
  }

  // Bước 3: gom MỌI token trong bài (option + transcript) CHƯA có trong knownKeys.
  // Trả lowercase wort_key list — dùng để quét DB global tìm từ đã học ở bài khác.
  function collectAllLessonTokens() {
    var texts = [];
    (LESSON.aussagen || []).forEach(function (a) {
      (a.options || []).forEach(function (o) { if (o.text) { texts.push(o.text); } });
    });
    (LESSON.transcript || []).forEach(function (t) { if (t.text) { texts.push(t.text); } });
    var seen = {};
    var out = [];
    texts.forEach(function (txt) {
      tokenize(txt).forEach(function (tok) {
        var k = tok.toLowerCase();
        if (k.length >= 3 && !seen[k] && !knownKeys[k] && !STOPWORDS[k]) {
          seen[k] = true;
          out.push(k);
        }
      });
    });
    return out;
  }

  // Bước 3: quét DB global cho mọi token lạ trong bài → đánh dấu knownKeys + lưu globalKnownData.
  //   KHÔNG thêm vào vocabData (không hiện trong panel "Alle Wörter") — chỉ lọc "Neu wort" + Pass 3 highlight.
  var globalKnownData = {};   // {wort_key: {w, art, bedeutung}} — cho Pass 3 injectMarks

  function loadGlobalKnownFromDB() {
    var tokens = collectAllLessonTokens();
    if (tokens.length === 0) { return Promise.resolve(); }
    var batches = [];
    for (var i = 0; i < tokens.length; i += 300) {
      batches.push(tokens.slice(i, i + 300));
    }
    return Promise.all(batches.map(function (batch) {
      return fetch('/api/vocab?words=' + encodeURIComponent(batch.join(',')), {
        credentials: 'same-origin', headers: { 'Accept': 'application/json' }
      }).then(function (r) { return r.ok ? r.json() : { vocab: [] }; })
        .then(function (d) { return (d && d.vocab) || []; })
        .catch(function () { return []; });
    })).then(function (results) {
      results.forEach(function (rows) {
        rows.forEach(function (row) {
          var k = (row.wort_key || row.w || '').toLowerCase();
          if (!k) { return; }
          knownKeys[k] = true;
          // Lưu data cho Pass 3 highlight (chỉ khi chưa có trong vocabData/formMap)
          if (!globalKnownData[k]) {
            globalKnownData[k] = { w: row.w || k, art: row.art || '', bedeutung: row.bedeutung || '' };
          }
        });
      });
      // Re-inject marks nếu "Nền vàng" đang bật (để Pass 3 chạy ngay)
      if (hlOn) { stripMarks(); marksInjected = false; injectMarks(); }
      refreshNeuIfOpen();
    });
  }

  // Danh sách global words (đã học ở bài khác) đang xuất hiện trong bài hiện tại,
  // đã lọc bỏ từ có trong vocabData/formMap (giống Pass 3 injectMarks).
  function globalWordsForPanel() {
    var vocabKeys = {};
    (vocabData || []).forEach(function (v) { vocabKeys[v.w.toLowerCase()] = true; });
    Object.keys(formMap).forEach(function (k) { vocabKeys[k] = true; });
    return Object.keys(globalKnownData)
      .filter(function (gk) { return !vocabKeys[gk]; })
      .map(function (gk) { return { key: gk, info: globalKnownData[gk] }; })
      .sort(function (a, b) { return a.info.w.toLowerCase().localeCompare(b.info.w.toLowerCase()); });
  }

  // HTML cho section "Đã học (bài khác)" — chỉ render khi có ≥ 1 global word.
  function globalSectionHtml() {
    var gw = globalWordsForPanel();
    if (gw.length === 0) { return ''; }
    var rows = gw.map(function (item) {
      var info = item.info;
      return '<div class="vocab-global-item" data-word="' + escHtml(item.key) + '">' +
        '<span class="vgi-word">' + escHtml(info.w) + '</span>' +
        (info.art ? '<span class="vgi-art">' + escHtml(info.art) + '</span>' : '') +
        (info.bedeutung ? '<span class="vgi-mean">' + escHtml(info.bedeutung) + '</span>' : '') +
        '</div>';
    }).join('');
    return '<div class="vocab-global-section">Đã học (bài khác) — ' + gw.length + '</div>' + rows;
  }

  // Click từ trong section "Đã học" → scroll đến vị trí đầu tiên trong đề + toggle hl-selected.
  function selectGlobalWord(gKey) {
    if (!gKey) { return; }
    document.querySelectorAll('.vocab-global-mark.hl-selected')
      .forEach(function (m) { m.classList.remove('hl-selected'); });
    var marks = document.querySelectorAll('.vocab-global-mark[data-word="' + gKey + '"]');
    marks.forEach(function (m) { m.classList.add('hl-selected'); });
    if (marks.length > 0) { marks[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }

  // Wire click cho các item trong section "Đã học" (gọi sau khi set list.innerHTML).
  function wireGlobalSection(list) {
    list.querySelectorAll('.vocab-global-item').forEach(function (el) {
      el.addEventListener('click', function () { selectGlobalWord(el.dataset.word); });
    });
  }

  function renderVocab() {
    var list = document.getElementById('vocabList');
    var items = vocabData || [];
    var globalHtml = globalSectionHtml();
    if (items.length === 0) {
      // Không có vocab riêng cho bài — vẫn hiện section "Đã học" nếu có global word.
      list.innerHTML = globalHtml
        ? ('<div class="vocab-empty">Chưa có từ vựng riêng cho bài này.</div>' + globalHtml)
        : '<div class="vocab-empty">Chưa có từ vựng cho bài này.</div>';
      var t0 = LESSON.title ? ('Vokabeln — Bài ' + LESSON_ID + ' — ' + LESSON.title) : 'Vokabeln';
      document.getElementById('vocabPanelTitle').textContent = t0;
      wireGlobalSection(list);
      return;
    }
    list.innerHTML = items.map(function (v) {
      var wKey = v.w.toLowerCase();
      var lv = wordStatus[wKey] || v.lv || 'new';
      // Phase 4: dòng phụ "↳ biến thể (FORM_TYPE)" nếu lemma này có biến thể đã biết trong bài
      var variantLine = '';
      var vlist = formsByLemma[wKey];
      if (vlist && vlist.length) {
        variantLine = '<div class="vocab-variants">↳ ' + vlist.map(function (f) {
          return escHtml(f.form) + ' (' + escHtml(f.form_type || '?') + ')';
        }).join(', ') + '</div>';
      }
      return '<div class="vocab-item" data-word="' + wKey + '">' +
        '<div class="vocab-lv lv-' + lv + '" data-word="' + wKey + '" title="Click: đổi trạng thái new→hard→ok">' + (lvNum[lv] || '1') + '</div>' +
        '<div class="vocab-text" style="cursor:pointer" data-word="' + wKey + '">' +
          '<span class="vocab-word">' + v.w + '</span>' +
          '<span class="vocab-art">' + (v.art || '') + '</span>' +
          '<div class="vocab-meaning">' + (v.m || '') + '</div>' +
          variantLine +
        '</div></div>';
    }).join('') + globalHtml;   // append section "Đã học (bài khác)" nếu có
    var title = LESSON.title ? ('Vokabeln — Bài ' + LESSON_ID + ' — ' + LESSON.title) : 'Vokabeln';
    document.getElementById('vocabPanelTitle').textContent = title;

    // Wire click: badge cycle status (+POST), text → selectWord (highlight)
    list.querySelectorAll('.vocab-lv').forEach(function (el) {
      el.addEventListener('click', function (e) { e.stopPropagation(); cycleWordStatus(el.dataset.word); });
    });
    list.querySelectorAll('.vocab-text').forEach(function (el) {
      el.addEventListener('click', function () { selectWord(el.dataset.word); });
    });
    wireGlobalSection(list);   // wire click cho section "Đã học"
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
    if (hlOn) {
      // Re-inject mỗi lần bật: dùng vocabData hiện tại (bao gồm từ DB + từ queue mới)
      stripMarks();
      marksInjected = false;
      injectMarks();
    }
  }

  // ── Inject <span class="vocab-mark"> vào option text + transcript ──
  var marksInjected = false;
  function escapeReg(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  function escHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  // Chỉ replace trong text nodes, KHÔNG replace trong HTML tags/attributes
  // (tránh match nhầm vào data-word/title của span đã chèn ở Pass trước).
  function replaceTextOnly(html, re, replacement) {
    return html.replace(/(<[^>]*>)|([^<]+)/g, function (match, tag, text) {
      if (tag) return tag;   // skip HTML tag + attributes
      return text ? text.replace(re, replacement) : match;
    });
  }

  // Xóa tất cả span.vocab-mark + .vocab-form-mark + .vocab-global-mark (unwrap về text thuần) để re-inject sạch.
  function stripMarks() {
    document.querySelectorAll('.vocab-mark, .vocab-form-mark, .vocab-global-mark').forEach(function (m) {
      if (m.parentNode) {
        m.parentNode.replaceChild(document.createTextNode(m.textContent), m);
      }
    });
    // Gộp text node liền kề lại để regex tiếp theo match đúng
    document.querySelectorAll('.option span, .transcript-box p').forEach(function (el) {
      el.normalize();
    });
  }

  function injectMarks() {
    if (marksInjected) return;
    marksInjected = true;
    // Pass 1: từ đã có (vocabData) → .vocab-mark (nền cam đậm)
    var words = (vocabData || []).map(function (v) { return v.w; });
    words.sort(function (a, b) { return b.length - a.length; }); // dài trước, tránh partial match
    // Pass 2: biến thể đã biết (formMap) → .vocab-form-mark (nền nhạt + link lemma)
    var forms = Object.keys(formMap).map(function (k) { return formMap[k].form; });
    forms.sort(function (a, b) { return b.length - a.length; });

    // Pass 3: global known (DB từ bài khác) → .vocab-global-mark (xanh nhạt).
    // Xây danh sách TRƯỚC forEach: lọc bỏ từ đã cover ở Pass 1/2 (vocabKeys) để dedup.
    var vocabKeys = {};
    (vocabData || []).forEach(function (v) { vocabKeys[v.w.toLowerCase()] = true; });
    Object.keys(formMap).forEach(function (k) { vocabKeys[k] = true; });
    var globalWords = Object.keys(globalKnownData)
      .filter(function (gk) { return !vocabKeys[gk]; })
      .map(function (gk) {
        var info = globalKnownData[gk];
        var tip = escHtml(info.w)
          + (info.art ? ' · ' + escHtml(info.art) : '')
          + (info.bedeutung ? ' = ' + escHtml(info.bedeutung) : '')
          + ' (đã học)';
        return { key: gk, w: info.w, tip: tip };
      });
    globalWords.sort(function (a, b) { return b.w.length - a.w.length; });

    var targets = document.querySelectorAll('.option span, .transcript-box p');
    targets.forEach(function (el) {
      var html = el.innerHTML;  // đọc 1 lần từ DOM gốc

      // Pass 1
      words.forEach(function (w) {
        // FIX double-escape \\w + dải ký tự có dấu Đức/tiếng Việt
        var re = new RegExp('(?<![\\w\\u00c0-\\u024f])(' + escapeReg(w) + ')(?![\\w\\u00c0-\\u024f])', 'gi');
        html = replaceTextOnly(html, re, '<span class="vocab-mark" data-word="' + w.toLowerCase() + '" title="' + escHtml(w) + '">$1</span>');
      });

      // Pass 2
      forms.forEach(function (fw) {
        var fk = fw.toLowerCase();
        var info = formMap[fk];
        if (!info) { return; }
        var re = new RegExp('(?<![\\w\\u00c0-\\u024f])(' + escapeReg(fw) + ')(?![\\w\\u00c0-\\u024f])', 'gi');
        var tip = escHtml(fw) + ' [' + escHtml(info.form_type || '?') + '] → ' + escHtml(info.lemma || '');
        html = replaceTextOnly(html, re, '<span class="vocab-form-mark" data-form="' + fk +
          '" data-lemma="' + escHtml(info.lemma_key || '') + '" data-ftype="' + escHtml(info.form_type || '') +
          '" title="' + tip + '">$1</span>');
      });

      // Pass 3 — TRONG cùng forEach, cùng html string (tránh đọc/ghi innerHTML đã có span)
      globalWords.forEach(function (item) {
        var re = new RegExp('(?<![\\w\\u00c0-\\u024f])(' + escapeReg(item.w) + ')(?![\\w\\u00c0-\\u024f])', 'gi');
        html = replaceTextOnly(html, re,
          '<span class="vocab-global-mark" data-word="' + item.key + '" title="' + item.tip + '">$1</span>');
      });

      el.innerHTML = html;  // ghi 1 lần duy nhất vào DOM
    });

    // Click mark trong đề/transcript → selectWord (form-mark → chọn lemma)
    document.querySelectorAll('.vocab-mark').forEach(function (m) {
      m.addEventListener('click', function () { selectWord(m.dataset.word); });
    });
    document.querySelectorAll('.vocab-form-mark').forEach(function (m) {
      m.addEventListener('click', function () { selectWord(m.dataset.lemma); });
    });
    // Global mark: tooltip only (từ không có trong panel bài này)
    document.querySelectorAll('.vocab-global-mark').forEach(function (m) {
      m.addEventListener('click', function () {
        // Hiện tooltip ngắn thay vì scroll panel (không có trong Alle Wörter)
        m.classList.toggle('hl-selected');
      });
    });
  }

  function selectWord(wKey) {
    if (!wKey) { return; }
    document.querySelectorAll('.vocab-mark.hl-selected, .vocab-form-mark.hl-selected')
      .forEach(function (m) { m.classList.remove('hl-selected'); });
    // Highlight cả từ gốc (vocab-mark[data-word]) và mọi biến thể của lemma (form-mark[data-lemma]).
    var marks = document.querySelectorAll('.vocab-mark[data-word="' + wKey + '"]');
    var fmarks = document.querySelectorAll('.vocab-form-mark[data-lemma="' + wKey + '"]');
    marks.forEach(function (m) { m.classList.add('hl-selected'); });
    fmarks.forEach(function (m) { m.classList.add('hl-selected'); });
    var scrollTo = marks.length > 0 ? marks[0] : (fmarks.length > 0 ? fmarks[0] : null);
    if (scrollTo) { scrollTo.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    // Panel: kích hoạt + scroll đến lemma item
    var activeItem = null;
    document.querySelectorAll('.vocab-item').forEach(function (item) {
      var on = item.dataset.word === wKey;
      item.classList.toggle('hl-active', on);
      if (on) { activeItem = item; }
    });
    if (activeItem) { activeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
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
    // Phase 4: tách "Biến thể đã biết" (form_key ∈ formMap) khỏi "Từ gốc mới".
    var variants = [];
    var fresh = [];
    cands.forEach(function (w) {
      if (formMap[w.toLowerCase()]) { variants.push(w); } else { fresh.push(w); }
    });

    if (cands.length === 0) {
      list.innerHTML = '<div class="vocab-empty">Không có từ lạ trong bài (tất cả đã có trong DB).</div>';
      return;
    }

    var html = '';
    // Nhóm 1: biến thể đã biết → link về lemma, KHÔNG cần "+ Queue" (đã có trong DB).
    if (variants.length) {
      html += '<div class="vnw-group-head">Biến thể đã biết (' + variants.length + ')</div>';
      html += variants.map(function (w) {
        var info = formMap[w.toLowerCase()];
        return '<div class="vocab-form-item" data-lemma="' + escHtml(info.lemma_key) + '">' +
          '<div class="vfi-form">' + escHtml(w) + '</div>' +
          '<div class="vfi-arrow">→</div>' +
          '<div class="vfi-lemma">' + escHtml(info.lemma) +
            ' <span class="vfi-ftype">' + escHtml(info.form_type || '?') + '</span>' +
            (info.bedeutung ? '<span class="vfi-mean">' + escHtml(info.bedeutung) + '</span>' : '') +
          '</div>' +
          '</div>';
      }).join('');
    }
    // Nhóm 2: từ gốc mới → form "+ Queue" như cũ.
    if (fresh.length) {
      html += '<div class="vnw-group-head">Từ gốc mới (' + fresh.length + ')</div>';
      html += fresh.map(function (w) {
        return '<div class="vocab-new-item" data-word="' + escHtml(w) + '">' +
          '<div class="vnw-row">' +
            '<div class="vnw-word">' + escHtml(w) + '</div>' +
            '<button class="vnw-add" type="button" title="Queue từ về local để tra sau">+ Queue</button>' +
          '</div>' +
          '<div class="vnw-expand" style="display:none">' +
            '<input class="vnw-mean" type="text" placeholder="Nghĩa (tuỳ chọn — tra sau cũng được)">' +
          '</div>' +
          '</div>';
      }).join('');
    }
    list.innerHTML = html;

    // Wire nhóm "từ gốc mới"
    list.querySelectorAll('.vocab-new-item').forEach(function (item) {
      item.querySelector('.vnw-add').addEventListener('click', function () { addNewWord(item); });
      var inp = item.querySelector('.vnw-mean');
      item.querySelector('.vnw-word').addEventListener('click', function () {
        var exp = item.querySelector('.vnw-expand');
        exp.style.display = exp.style.display === 'none' ? '' : 'none';
        if (exp.style.display !== 'none') { inp.focus(); }
      });
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { addNewWord(item); }
      });
    });
    // Wire nhóm "biến thể đã biết": click → sang tab Alle Wörter + chọn lemma
    list.querySelectorAll('.vocab-form-item').forEach(function (item) {
      item.addEventListener('click', function () {
        switchTab('all');
        selectWord(item.dataset.lemma);
      });
    });
  }

  function addNewWord(item) {
    var wort    = item.dataset.word;
    var meanEl  = item.querySelector('.vnw-mean');
    var mean    = meanEl ? meanEl.value.trim() : '';
    // artikel / wortart bỏ trống — điền sau khi pull về local (Cowork curate)
    var btn     = item.querySelector('.vnw-add');
    btn.disabled = true; btn.textContent = '…';

    fetch('/api/vocab', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        wort: wort, bedeutung: mean || null, wortart: null, artikel: null, source_lesson: LESSON_ID
      })
    }).then(function (r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    }).then(function (data) {
      var key = wort.toLowerCase();
      knownKeys[key] = true;
      // Hiện trong "Alle Wörter" với badge "?" — nghĩa chưa có, điền sau
      vocabData.push({ w: wort, art: '?', m: mean || '— (chưa tra)', lv: 'new',
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
