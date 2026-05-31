// tutor_note.js — Quill editor + auto-save (debounce 1s) + polling sync (3s).
// Collaboration ≤2 người/note: last-write-wins. Không overwrite khi user đang gõ (<2s).
// Ref: docs/ai/tasks/DEUTSCH_WEB_TUTOR_PHASE1_PROMPT.md §6.
(function () {
  'use strict';

  var P = window.NOTE || {};
  var canEdit = !!P.can_edit;
  var params = 'lesson_id=' + encodeURIComponent(P.lesson_id) +
               '&student_id=' + encodeURIComponent(P.student_id) +
               '&date=' + encodeURIComponent(P.date);

  var statusEl = document.getElementById('saveStatus');
  function setStatus(t) { if (statusEl) { statusEl.textContent = t; } }

  var quill = new Quill('#editor', {
    theme: 'snow',
    readOnly: !canEdit,
    modules: {
      toolbar: canEdit ? [
        [{ 'header': [2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'color': [] }, { 'background': [] }],   // highlight = background color
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        [{ 'indent': '-1' }, { 'indent': '+1' }],
        ['blockquote', 'link'],
        ['clean']
      ] : false
    }
  });

  var lastUpdatedAt = null;   // updated_at server đã đồng bộ lần cuối
  var lastKeyTime = 0;        // thời điểm gõ phím cuối (ms)
  var saveTimer = null;

  // Đổ HTML vào Quill, giữ vị trí con trỏ nếu có.
  function applyContent(html) {
    var range = quill.getSelection();
    quill.setContents(quill.clipboard.convert(html || ''));
    if (range) {
      try { quill.setSelection(range.index, range.length); } catch (e) {}
    }
  }

  // 'YYYY-MM-DDTHH:MM:SSZ' → 'HH:MM' (local) cho status.
  function fmtTime(iso) {
    if (!iso) { return ''; }
    var d = new Date(iso);
    if (isNaN(d.getTime())) { return ''; }
    var hh = ('0' + d.getHours()).slice(-2);
    var mm = ('0' + d.getMinutes()).slice(-2);
    return hh + ':' + mm;
  }

  function loadNote(initial) {
    fetch('/api/notes?' + params, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || data.error) { return; }
        if (initial) {
          applyContent(data.content);
          lastUpdatedAt = data.updated_at;
          setStatus(data.updated_at ? ('Đã tải · ' + fmtTime(data.updated_at)) : 'Ghi chú mới');
          return;
        }
        // Poll: chỉ cập nhật khi server có bản mới VÀ user không đang gõ.
        if (data.updated_at === lastUpdatedAt) { return; }
        if (Date.now() - lastKeyTime < 2000) { return; }
        applyContent(data.content);
        lastUpdatedAt = data.updated_at;
        setStatus('Đã đồng bộ · ' + fmtTime(data.updated_at));
      })
      .catch(function () { /* network blip → để poll sau thử lại */ });
  }

  function saveNote() {
    if (!canEdit) { return; }
    setStatus('Đang lưu…');
    fetch('/api/notes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        lesson_id: P.lesson_id,
        student_id: P.student_id,
        date: P.date,
        content: quill.root.innerHTML
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && data.updated_at) {
          lastUpdatedAt = data.updated_at;
          setStatus('Đã lưu · ' + fmtTime(data.updated_at));
        } else {
          setStatus('Lỗi lưu' + (data && data.error ? ': ' + data.error : ''));
        }
      })
      .catch(function () { setStatus('Lỗi mạng — thử lại'); });
  }

  if (canEdit) {
    quill.on('text-change', function (delta, oldDelta, source) {
      if (source !== 'user') { return; }   // bỏ qua thay đổi do applyContent (source='api')
      lastKeyTime = Date.now();
      setStatus('Đang soạn…');
      clearTimeout(saveTimer);
      saveTimer = setTimeout(saveNote, 1000);   // debounce 1s
    });
  }

  loadNote(true);                       // tải nội dung ban đầu
  setInterval(function () { loadNote(false); }, 3000);   // poll 3s
})();
