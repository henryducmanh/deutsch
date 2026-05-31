# Deutsch Web — Vocab Panel UX Improvements

> **Task ID:** `deutsch_web_vocab_ux`
> **Lock:** Tạo `.ai-locks/vocab_ux.lock` đầu task, xóa cuối.
> **Paste 1 dòng:** `Đọc docs/ai/tasks/DEUTSCH_WEB_VOCAB_UX_PROMPT.md và làm theo. Tạo lock .ai-locks/vocab_ux.lock. Báo "edit xong, chờ review Cursor".`

**QUAN TRỌNG — File > 280 dòng trên Windows mount:**
KHÔNG dùng Write tool cho drill.js (551 dòng) và drill.css.
Chỉ dùng **Edit** (patch nhỏ, từng đoạn). Sau mỗi Edit > 300 dòng: xác nhận `wc -l` + `tail -5`.

---

## 6 thay đổi cần làm

### 1. "Nền vàng" → "Highlight" (lỗi chính tả)

**File:** `module/deutsch_web/views/drill_horen.php`

Tìm và sửa:
```
☀ Nền vàng
```
thành:
```
☀ Highlight
```

**File:** `module/deutsch_web/public/assets/drill.css`
Tìm comment `/* Nền vàng tất cả từ` → đổi thành `/* Highlight tất cả từ`.

---

### 2. Nút Highlight bật/tắt CẢ HAI: nền vàng + gạch chân

**Vấn đề:** `.vocab-form-mark` và `.vocab-global-mark` luôn có `border-bottom` dù Highlight tắt → gạch chân hiện cả khi OFF.

**Fix trong `module/deutsch_web/public/assets/drill.css`:**

Xóa `border-bottom` khỏi BASE class (luôn hiện), chuyển vào selector `body.hl-on`:

```css
/* TRƯỚC (base — luôn hiện): */
.vocab-form-mark { border-radius: 3px; cursor: pointer;
                   border-bottom: 2px dotted #e06030; transition: background .15s; }
.vocab-global-mark { border-radius: 3px; cursor: pointer;
                     border-bottom: 2px solid #4a9eff; transition: background .15s; }

/* SAU: */
.vocab-form-mark  { border-radius: 3px; cursor: pointer; transition: background .15s; }
.vocab-global-mark { border-radius: 3px; cursor: pointer; transition: background .15s; }

/* Chỉ hiện khi hl-on: */
body.hl-on .vocab-mark        { background: #ffe066; }
body.hl-on .vocab-mark:hover  { background: #ffc926; }
body.hl-on .vocab-form-mark   { background: #fff0c2; border-bottom: 2px dotted #e06030; }
body.hl-on .vocab-form-mark:hover { background: #ffe08a; }
body.hl-on .vocab-global-mark { background: #deeeff; border-bottom: 2px solid #4a9eff; }
body.hl-on .vocab-global-mark:hover { background: #b8d9ff; }
```

Cũng thêm `border-bottom` cho `.vocab-mark` khi hl-on (hiện chưa có underline):
```css
body.hl-on .vocab-mark { background: #ffe066; border-bottom: 2px solid #e8a020; }
```

---

### 3. "Đã học (bài khác)" → "Đã dịch"

**File:** `module/deutsch_web/public/assets/drill.js`

Tìm:
```javascript
return '<div class="vocab-global-section">Đã học (bài khác) — ' + gw.length + '</div>' + rows;
```
Sửa thành:
```javascript
return '<div class="vocab-global-section">Đã dịch — ' + gw.length + '</div>' + rows;
```

---

### 4. "Alle Wörter" → "Từ trong bài"

**File:** `module/deutsch_web/views/drill_horen.php`

Tìm:
```html
<span class="vocab-tab active" id="tabAll" data-tab="all">Alle Wörter</span>
```
Sửa thành:
```html
<span class="vocab-tab active" id="tabAll" data-tab="all">Từ trong bài</span>
```

---

### 5. Thêm nút + (add) và × (remove) cho vocab items

#### 5a. Nút "+" trong section "Đã dịch" → thêm vào "Từ trong bài"

Trong hàm `buildGlobalSection()` (drill.js, tạo HTML cho global items), thêm nút `+` mỗi row:

```javascript
// Hiện tại mỗi row là:
'<div class="vocab-global-item" data-word="' + escHtml(gk) + '">' +
  '<div class="vgi-word">' + escHtml(gd.w) + '</div>' + ...

// Thêm nút + vào cuối row:
'<div class="vocab-global-item" data-word="' + escHtml(gk) + '">' +
  '<div class="vgi-word">' + escHtml(gd.w) + '</div>' + ...
  '<button class="vgi-add-btn" data-word="' + escHtml(gk) + '" title="Thêm vào Từ trong bài">+</button>' +
'</div>'
```

Trong `wireGlobalSection()`, wire click cho `.vgi-add-btn`:
```javascript
list.querySelectorAll('.vgi-add-btn').forEach(function (btn) {
  btn.addEventListener('click', function (e) {
    e.stopPropagation();  // không trigger scroll/select của item
    var gk = btn.dataset.word;
    var gd = globalKnownData[gk];
    if (!gd) { return; }
    // Thêm vào vocabData nếu chưa có
    var alreadyIn = vocabData.some(function (v) { return v.w.toLowerCase() === gk; });
    if (alreadyIn) { btn.textContent = '✓'; btn.disabled = true; return; }
    vocabData.push({ w: gd.w, art: gd.art || '', m: gd.bedeutung || '—', lv: 'new', addedFromGlobal: true });
    wordStatus[gk] = 'new';
    if (vocabOpen) { renderVocab(); }
    if (hlOn) { stripMarks(); marksInjected = false; injectMarks(); }
    btn.textContent = '✓'; btn.disabled = true;
  });
});
```

#### 5b. Nút "×" trong "Từ trong bài" → remove khỏi vocabData

Trong hàm render vocab item (`renderVocab`), thêm nút × vào mỗi item:

Tìm trong hàm render vocab item HTML — nơi tạo `.vocab-item` HTML — thêm nút remove:
```javascript
// Trong phần tạo HTML vocab item, thêm:
'<button class="vocab-remove-btn" data-word="' + escHtml(v.w.toLowerCase()) + '" title="Bỏ khỏi Từ trong bài">×</button>'
```

Sau khi `list.innerHTML = ...`, wire click cho `.vocab-remove-btn`:
```javascript
list.querySelectorAll('.vocab-remove-btn').forEach(function (btn) {
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    var wKey = btn.dataset.word;
    vocabData = vocabData.filter(function (v) { return v.w.toLowerCase() !== wKey; });
    delete wordStatus[wKey];
    renderVocab();
    if (hlOn) { stripMarks(); marksInjected = false; injectMarks(); }
  });
});
```

**CSS cho 2 nút mới** (append vào drill.css):
```css
/* Nút + trong section Đã dịch */
.vgi-add-btn {
  margin-left: auto; flex-shrink: 0;
  border: 1.5px solid #4a9eff; background: none; color: #4a9eff;
  border-radius: 6px; padding: 2px 8px; font-size: 14px; font-weight: 700;
  cursor: pointer; line-height: 1.4;
}
.vgi-add-btn:hover { background: #4a9eff; color: #fff; }
.vgi-add-btn:disabled { opacity: .4; cursor: default; }

/* Nút × trong Từ trong bài */
.vocab-remove-btn {
  flex-shrink: 0; border: none; background: none;
  color: #ccc; font-size: 16px; cursor: pointer; padding: 0 4px; line-height: 1;
}
.vocab-remove-btn:hover { color: #e06030; }
```

---

### 6. Teil 1/2/3: cho phép tương tác từ vựng ở text câu hỏi

**Vấn đề:** `injectMarks()` chỉ target `.option span, .transcript-box p`. Với Teil 1/2/3, câu hỏi nằm trong `.aussage-label` (ví dụ "Die Frau möchte ein Handy kaufen.") chưa được highlight/interactive.

**A. `injectMarks()` — thêm `.aussage-label` vào targets:**

Tìm:
```javascript
var targets = document.querySelectorAll('.option span, .transcript-box p');
```
Sửa thành:
```javascript
var targets = document.querySelectorAll('.option span, .transcript-box p, .aussage-label');
```

**B. `stripMarks()` — thêm `.aussage-label` vào normalize:**

Tìm:
```javascript
document.querySelectorAll('.option span, .transcript-box p').forEach(function (el) {
  el.normalize();
});
```
Sửa thành:
```javascript
document.querySelectorAll('.option span, .transcript-box p, .aussage-label').forEach(function (el) {
  el.normalize();
});
```

**C. `collectUnknownTokens()` — thêm text từ `.aussage-label` (question text):**

Tìm:
```javascript
(LESSON.aussagen || []).forEach(function (a) {
  (a.options || []).forEach(function (o) { if (o.text) { texts.push(o.text); } });
});
```
Sửa thành:
```javascript
(LESSON.aussagen || []).forEach(function (a) {
  if (a.label && a.label.length > 10) { texts.push(a.label); }  // question text (không phải "Aussage 1")
  (a.options || []).forEach(function (o) { if (o.text) { texts.push(o.text); } });
});
```

**Lưu ý:** `.aussage-label` chứa số đầu (ví dụ "1. Thomas..."). injectMarks đã dùng word-boundary regex nên số không match từ Đức → OK.

---

## Acceptance tests

1. Nút panel hiển thị "☀ Highlight" (không phải "Nền vàng").
2. Tab "Từ trong bài" (không phải "Alle Wörter").
3. Khi Highlight OFF: không có nền vàng, không có gạch chân (vocab-mark, form-mark, global-mark đều trong suốt).
4. Khi Highlight ON: nền vàng trên vocab-mark + gạch chân trên form-mark + gạch xanh trên global-mark.
5. Section "Đã dịch — N" (không phải "Đã học (bài khác)"). Mỗi item có nút "+".
6. Click "+" → item xuất hiện trong "Từ trong bài", nút thành "✓" disabled.
7. Mỗi item trong "Từ trong bài" có nút "×". Click "×" → item biến mất. Global "Đã dịch" không đổi.
8. Mở bài Teil 1/2/3 (vd /lesson/3.1), bật Highlight → text câu hỏi cũng bị highlight nếu có từ trong vocab.

---

## Cấm đụng

- KHÔNG sửa lesson JSON, lingq_sync, deutsch_web_sync, scan_extract
- KHÔNG dùng Write tool cho drill.js / drill.css (dùng Edit từng đoạn)
- KHÔNG thêm API call mới cho tính năng add/remove (session-only, không persist)
- KHÔNG sửa backend PHP cho 6 tính năng này

---

## Format report

```
=== VOCAB UX DONE ===
Files sửa:
- module/deutsch_web/views/drill_horen.php (1,4)
- module/deutsch_web/public/assets/drill.css (2, CSS nút mới)
- module/deutsch_web/public/assets/drill.js  (3, 5a, 5b, 6)

wc -l drill.js: [số dòng]
tail -5 drill.js: [5 dòng cuối]

Tests: 1✓ 2✓ 3✓ 4✓ 5✓ 6✓ 7✓ 8✓
Lock xóa: .ai-locks/vocab_ux.lock
```
