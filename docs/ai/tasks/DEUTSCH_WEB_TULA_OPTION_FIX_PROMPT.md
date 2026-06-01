# DEUTSCH_WEB — Fix: Tab "Từ lạ" chặn thao tác câu hỏi

## Goal
Khi tab "Từ lạ" mở, người dùng không thể click chọn đáp án câu hỏi Hören.
Sửa để 2 việc cùng hoạt động: chọn đáp án + mark từ lạ.

## Root Cause (đã xác nhận)

Hàm `injectNewInlineMarks()` (dòng 1187, `drill.js`) inject `<span class="vocab-new-inline-mark">` vào cả `.option span` (phần text đáp án).

Mỗi span này có listener với `e.stopPropagation()` (dòng 1202):
```js
m.addEventListener('click', function (e) {
  e.stopPropagation();   // ← chặn click không lên tới <label>
  addNewWordInline(m.dataset.word, m);
});
```

HTML option là `<label class="option"><input type="radio"><span>…</span></label>`.
Khi text bị bọc trong mark-span có stopPropagation → click text không bubble lên `<label>` → radio không được check → option "đơ".

## Files

```
module/deutsch_web/public/assets/drill.js
```

## Main Work

**Thay đổi duy nhất: bỏ `.option span` ra khỏi 2 dòng querySelector trong drill.js.**

**Dòng 1187** — trong `injectNewInlineMarks()`:
```js
// Trước
var targets = document.querySelectorAll('.option span, .transcript-box p, .aussage-label');

// Sau
var targets = document.querySelectorAll('.transcript-box p, .aussage-label');
```

**Dòng 1214** — trong `stripNewInlineMarks()` (normalize pass):
```js
// Trước
document.querySelectorAll('.option span, .transcript-box p, .aussage-label').forEach(function (el) {

// Sau
document.querySelectorAll('.transcript-box p, .aussage-label').forEach(function (el) {
```

Không sửa gì khác. Dòng 880 và 915 (stripMarks/injectMarks cho vocab-mark thường) giữ nguyên — scope khác.

## Test

1. Vào bài Hören bất kỳ (vd `/lesson/3.30`).
2. Mở vocab panel → click tab **"Từ lạ"**.
3. Click vào option "a) Richtig" hoặc "b) Falsch" → radio phải được check, class `selected` phải hiện.
4. Click vào từ được highlight dashed-purple trong **`.aussage-label`** (câu hỏi) → từ vẫn queue được vào "Từ lạ" list.
5. Chuyển sang tab "Đang ôn" → click option → vẫn hoạt động bình thường.
