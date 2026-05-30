# FIX: vocab-global-mark infinite injection + tab "Đã học"
> Lock: `.ai-locks/deutsch_web_global_mark_fix.lock`
> Đọc file: `module/deutsch_web/public/assets/drill.js` trước khi sửa.

## Root cause (2 bug)

**Bug 1 — infinite nesting:**
Pass 3 dùng nested forEach riêng, chạy NGOÀI `targets.forEach` main:
```
Object.keys(globalKnownData).forEach(gk => {     // outer loop: words
    targets.forEach(el => {                        // inner loop: elements
        el.innerHTML = el.innerHTML.replace(...)   // BUG: đọc/ghi innerHTML đã có span từ pass 1/2
    })
})
```
Regex `/Homeoffice/gi` match cả text trong `title="Homeoffice"` attribute của span Pass 1 → inject span vào TRONG attribute → html bị vỡ → browser render attribute text ra màn hình.

**Bug 2 — stripMarks không xóa `.vocab-global-mark`:**
`stripMarks()` chỉ selector `.vocab-mark, .vocab-form-mark` → `vocab-global-mark` giữ lại → lần re-inject sau match lại → nesting tiếp tục.

---

## Fix Pass 3 — merge vào targets.forEach chính

Cấu trúc ĐÚNG: tất cả 3 pass xử lý trên **cùng 1 `html` string**, `el.innerHTML` chỉ ghi **1 lần duy nhất** ở cuối:

```javascript
function injectMarks() {
    if (marksInjected) return;
    marksInjected = true;

    // Pass 1 words (vocabData)
    var words = (vocabData || []).map(v => v.w);
    words.sort((a, b) => b.length - a.length);

    // Pass 2 form words
    var forms = Object.keys(formMap).map(k => formMap[k].form);
    forms.sort((a, b) => b.length - a.length);

    // Pass 3: build global word list TRƯỚC khi vào forEach
    // Xây vocabKeys (words đã cover ở Pass 1+2) để dedup
    var vocabKeys = {};
    (vocabData || []).forEach(v => { vocabKeys[v.w.toLowerCase()] = true; });
    Object.keys(formMap).forEach(k => { vocabKeys[k] = true; });

    var globalWords = Object.keys(globalKnownData)
        .filter(gk => !vocabKeys[gk])
        .map(gk => {
            var info = globalKnownData[gk];
            var tip = escHtml(info.w)
                + (info.art ? ' · ' + escHtml(info.art) : '')
                + (info.bedeutung ? ' = ' + escHtml(info.bedeutung) : '')
                + ' (đã học)';
            return { key: gk, w: info.w, tip: tip };
        });
    globalWords.sort((a, b) => b.w.length - a.w.length);

    var targets = document.querySelectorAll('.option span, .transcript-box p');
    targets.forEach(function (el) {
        var html = el.innerHTML;  // ← đọc 1 lần từ DOM gốc

        // Pass 1
        words.forEach(function (w) {
            var re = new RegExp('(?<![\\w\\u00c0-\\u024f])(' + escapeReg(w) + ')(?![\\w\\u00c0-\\u024f])', 'gi');
            html = html.replace(re, '<span class="vocab-mark" data-word="' + w.toLowerCase() + '" title="' + escHtml(w) + '">$1</span>');
        });

        // Pass 2
        forms.forEach(function (fw) {
            var fk = fw.toLowerCase();
            var info = formMap[fk];
            if (!info) return;
            var re = new RegExp('(?<![\\w\\u00c0-\\u024f])(' + escapeReg(fw) + ')(?![\\w\\u00c0-\\u024f])', 'gi');
            var tip = escHtml(fw) + ' [' + escHtml(info.form_type || '?') + '] → ' + escHtml(info.lemma || '');
            html = html.replace(re,
                '<span class="vocab-form-mark" data-form="' + fk
                + '" data-lemma="' + escHtml(info.lemma_key || '')
                + '" data-ftype="' + escHtml(info.form_type || '')
                + '" title="' + tip + '">$1</span>');
        });

        // Pass 3 — global known (TRONG cùng forEach, cùng html string)
        globalWords.forEach(function (item) {
            var re = new RegExp('(?<![\\w\\u00c0-\\u024f])(' + escapeReg(item.w) + ')(?![\\w\\u00c0-\\u024f])', 'gi');
            html = html.replace(re,
                '<span class="vocab-global-mark" data-word="' + item.key
                + '" title="' + item.tip + '">$1</span>');
        });

        el.innerHTML = html;  // ← ghi 1 lần duy nhất vào DOM
    });

    // Wire click handlers (vocab-mark, vocab-form-mark như cũ)
    // vocab-global-mark: click toggle hl-selected
    document.querySelectorAll('.vocab-mark').forEach(m => {
        m.addEventListener('click', () => selectWord(m.dataset.word));
    });
    document.querySelectorAll('.vocab-form-mark').forEach(m => {
        m.addEventListener('click', () => selectWord(m.dataset.lemma));
    });
    document.querySelectorAll('.vocab-global-mark').forEach(m => {
        m.addEventListener('click', () => m.classList.toggle('hl-selected'));
    });
}
```

## Fix stripMarks — thêm .vocab-global-mark

```javascript
function stripMarks() {
    document.querySelectorAll('.vocab-mark, .vocab-form-mark, .vocab-global-mark').forEach(m => {
        if (m.parentNode) {
            m.parentNode.replaceChild(document.createTextNode(m.textContent), m);
        }
    });
    document.querySelectorAll('.option span, .transcript-box p').forEach(el => el.normalize());
}
```

---

## Thêm tab "Đã học" trong panel Alle Wörter

Khi user click vào từ `vocab-global-mark` → không có trong panel bài này → cần 1 chỗ để xem nghĩa.

**Option A (đơn giản): thêm section "Đã học từ bài khác" trong tab "Alle Wörter"**

Sau danh sách lesson vocab, render thêm một section nhỏ cho các global words đang visible trong bài:

```
── Alle Wörter ──────────────────
  [lesson vocab items như cũ]
  
── Đã học (bài khác) ───────────   ← section header, collapse được
  Homeoffice · das Subst · làm việc tại nhà
  Vorteil · der Subst · lợi thế / ưu điểm
  ...
```

Section "Đã học" chỉ hiện khi có ≥ 1 global word được highlight trong bài hiện tại.
Click vào từ trong section này → scroll đến vị trí đầu tiên trong đề (highlight hl-selected).

**Implement trong `renderVocab()`:** sau khi render vocabList items, append thêm section nếu `Object.keys(globalKnownData).length > 0`.

---

## CSS bổ sung

```css
/* Section header "Đã học" */
.vocab-global-section { padding: 6px 12px 4px; font-size: 11px; font-weight: 700;
                         color: #4a9eff; border-top: 1px solid #e8f4ff; margin-top: 6px; }
.vocab-global-item { display: flex; align-items: flex-start; gap: 8px; padding: 4px 12px;
                     cursor: pointer; }
.vocab-global-item:hover { background: #f0f8ff; }
.vocab-global-item .vgi-word { font-weight: 600; color: #222; font-size: 13px; }
.vocab-global-item .vgi-art { font-size: 11px; color: #4a9eff; }
.vocab-global-item .vgi-mean { font-size: 12px; color: #555; }
```

---

## Acceptance tests

- [ ] Bật "Nền vàng" → "Homeoffice" highlight xanh lam, KHÔNG có text thừa
- [ ] Tắt + bật lại "Nền vàng" nhiều lần → không tạo thêm span nào
- [ ] Section "Đã học (bài khác)" hiện trong panel với nghĩa từ
- [ ] Click từ xanh trong đề → toggle hl-selected (scroll to nếu cần)
- [ ] KHÔNG break Pass 1/2 (cam đậm, cam nhạt vẫn đúng)

## Files sửa
```
module/deutsch_web/public/assets/drill.js       ← Fix injectMarks + stripMarks + renderVocab
module/deutsch_web/public/assets/drill.css       ← CSS vocab-global-section/item
module/deutsch_web/views/drill_horen.php          ← version bump 20260530j
```

KHÔNG commit/push.
