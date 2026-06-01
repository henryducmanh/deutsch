# DEUTSCH_WEB — Fix: Options không tương tác được khi vocab panel mở (v2)

## Trạng thái
Fix v2 (`injectMarks` / `wireMarkClicks`) đã done. Fix v3 (2026-06-01): khôi phục tab **Từ lạ** trên option — xem cuối file.

## Root Cause đầy đủ (3 nguồn)

### Nguồn 1 — ĐÃ SỬA (v1)
`injectNewInlineMarks()` inject `.vocab-new-inline-mark` vào `.option span`, mỗi span có `e.stopPropagation()` → click text option không bubble lên `<label>` → radio không check.

### Nguồn 2 — CHƯA SỬA
`injectMarks()` (dòng 915) được gọi **mỗi khi mở vocab panel** (`openVocab()` dòng 635), inject `.vocab-mark` / `.vocab-form-mark` / `.vocab-global-mark` vào `.option span`.

`wireMarkClicks()` dùng **capture phase** (addEventListener(..., true)):
```js
// dòng 851-860
document.addEventListener('click', function (e) {
  var mark = el.closest('.vocab-mark, .vocab-global-mark, .vocab-form-mark');
  if (!mark) { return; }
  if (!mark.closest('#aussagen, #transcript, .transcript-box')) { return; }
  e.preventDefault();
  e.stopPropagation();   // ← chặn từ capture, click không xuống được label
  openVocabPopupFromMark(wKey, mark);
}, true);
```

`#aussagen` bọc toàn bộ question block kể cả `.options`. Nên bất kỳ vocab-mark nào trong option span đều bị chặn.

### Nguồn 3 — CHƯA SỬA (gây Teil 4 mất option)
`injectMarks()` dòng 915 target cả `.option span`. Với Teil 4 có 6 options dài + 60+ global known words, `replaceTextOnly` tạo HTML lồng nhau quá phức tạp → browser parse sai cấu trúc → options biến mất khỏi DOM.

## Files

```
module/deutsch_web/public/assets/drill.js
```

## Main Work — 3 thay đổi

### Thay đổi 1: stripMarks() — dòng 880
```js
// Trước
document.querySelectorAll('.option span, .transcript-box p, .aussage-label').forEach(function (el) {

// Sau
document.querySelectorAll('.transcript-box p, .aussage-label').forEach(function (el) {
```

### Thay đổi 2: injectMarks() — dòng 915
```js
// Trước
var targets = document.querySelectorAll('.option span, .transcript-box p, .aussage-label');

// Sau
var targets = document.querySelectorAll('.transcript-box p, .aussage-label');
```

### Thay đổi 3: wireMarkClicks() — dòng khoảng 855-856
```js
// Trước
if (!mark.closest('#aussagen, #transcript, .transcript-box')) { return; }

// Sau — chỉ trigger popup trong aussage-label + transcript, KHÔNG trong .options
if (!mark.closest('.aussage-label, #transcript, .transcript-box')) { return; }
```

Thay đổi 3 là safety net: dù mark có lọt vào option span trong tương lai, popup cũng không fire ở đó.

## Kết quả sau fix

- Options a/b/c... và Richtig/Falsch: click được ở mọi tab (Đang ôn / Đã dịch / Từ lạ)
- Teil 4 (6 options dài): không bị mất, không bị inject marks phức tạp
- Marks (highlight vocab) vẫn hiện trong `.aussage-label` (câu hỏi) và transcript
- Popup nghĩa từ vẫn hiện khi click mark trong câu hỏi label hoặc transcript

## Test

1. Bài Teil 1/2/3 (vd 3.2): Mở vocab panel → tab "Đang ôn" → click option a/b → radio check ✓
2. Bài Teil 1/2/3: Tab "Từ lạ" → click option a/b → radio check ✓
3. Bài Teil 4 (vd 4.4): Mở vocab panel → tab "Từ lạ" → 6 options a-f vẫn hiện đủ ✓
4. Bài Teil 4: click option a/b/c... → radio check ✓
5. Bật Highlight → click từ được highlight trong `.aussage-label` → popup nghĩa vẫn hiện ✓
6. Click từ highlight trong transcript → popup hiện ✓

## Lưu ý
`injectMarks()` / `stripMarks()` / `wireMarkClicks()` — **giữ** không target `.option span` (v2).

---

## Fix v3 — Tab "Từ lạ" mất gạch trên option (regression v1)

### Nguyên nhân
Hai pipeline inject **khác nhau**:

| Hàm | Tab | Class | Option span? |
|-----|-----|-------|----------------|
| `injectMarks()` | Đang ôn + Highlight | `.vocab-mark` … | **Không** (v2 — đúng) |
| `injectNewInlineMarks()` | Từ lạ | `.vocab-new-inline-mark` | v1 đã bỏ nhầm → mất highlight |

v1 bỏ `.option span` để hết `stopPropagation` chặn radio — nhưng chỉ cần sửa **listener**, không cần bỏ inject.

### Sửa
1. `newInlineMarkTargets = '.option span, .transcript-box p, .aussage-label'` — dùng cho inject + strip Từ lạ.
2. Click handler: `if (!m.closest('.option')) { e.stopPropagation(); }` — trong option vẫn queue từ + radio vẫn check.

### Test thêm (tab Từ lạ)
7. Option có từ trong list (vd `Ausweispapiere`) → gạch tím dashed ✓
8. Click gạch trong option → queue + radio vẫn check ✓
