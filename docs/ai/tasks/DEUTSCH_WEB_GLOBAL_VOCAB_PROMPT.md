# DEUTSCH_WEB_GLOBAL_VOCAB — LingQ-style cross-lesson vocab awareness
> Handoff Claude Code. Lock: `.ai-locks/deutsch_web_global_vocab.lock`

## Vấn đề
"Arbeit" đã có trong DB (học từ bài 4.31) nhưng khi mở bài 4.30, tab "Neu wort"
vẫn hiện "Arbeit" là từ mới vì hệ thống chỉ check vocab của bài hiện tại.

LingQ: vocab là **global per user** — học ở bài nào → biết ở mọi bài.

## Thay đổi cần làm

### 1. api/vocab.php — nâng cap GET /api/vocab?words=
Sửa: `if (count($keys) >= 50) { break; }` → `if (count($keys) >= 300) { break; }`

### 2. drill.js — loadVocabFromDB(): thêm global token scan

Sau khi fetch lesson vocab + queued words, thêm **bước 3**:

```
// Bước 3: Global scan — tất cả tokens trong lesson text
// → query DB để tìm từ đã biết từ bài học khác

var allTokens = collectAllLessonTokens();   // tất cả tokens từ options + transcript
var unknownTokens = allTokens.filter(t => !knownKeys[t]);  // loại đã biết

if (unknownTokens.length > 0) {
  fetch('/api/vocab?words=' + encodeURIComponent(unknownTokens.join(',')), ...)
  .then(data => {
    data.vocab.forEach(row => {
      knownKeys[row.wort_key] = true;   // đánh dấu global known
      // KHÔNG thêm vào vocabData (không hiện trong panel trừ khi user muốn)
      // → chỉ dùng để lọc "Neu wort"
    });
    refreshNeuIfOpen();
  })
}
```

**`collectAllLessonTokens()`** — tách từ từ lesson text:
```javascript
function collectAllLessonTokens() {
  var texts = [];
  (LESSON.aussagen || []).forEach(function(a) {
    (a.options || []).forEach(function(o) { if (o.text) texts.push(o.text); });
  });
  (LESSON.transcript || []).forEach(function(t) { if (t.text) texts.push(t.text); });

  var seen = {};
  var out = [];
  texts.forEach(function(txt) {
    (txt.match(/[A-Za-zÀ-ɏ]+/g) || []).forEach(function(tok) {
      var k = tok.toLowerCase();
      if (k.length >= 3 && !seen[k] && !STOPWORDS[k]) {
        seen[k] = true;
        out.push(k);
      }
    });
  });
  return out;  // lowercase wort_key list
}
```

Chia batch nếu > 300 tokens (dùng Promise.all với nhiều fetch).

### 3. drill.js — collectCandidates(): chỉ hiện từ THẬT SỰ mới

Không đổi logic — knownKeys đã đầy đủ global sau bước 3 → collectCandidates()
tự lọc đúng.

### 4. drill.js — "Neu wort" tab: thêm nhóm "Đã biết từ bài khác"

Tách 3 nhóm trong renderNewWords():
- **"TỪ GỐC MỚI"** — chưa có trong DB global
- **"BIẾN THỂ ĐÃ BIẾT"** — lemma đã có (từ formMap, feature trước)
- **"ĐÃ HỌC (bài khác)"** — token có trong DB global nhưng source ≠ lesson hiện tại
  → hiện với style mờ + link "Xem nghĩa" (không có nút Queue)

> Note: nhóm 3 là optional nếu quá phức tạp — ưu tiên nhóm 1+2 đúng trước.

## Acceptance Tests
- [ ] Mở bài 4.30 → "Arbeit" (đã học ở 4.31) KHÔNG xuất hiện trong "Neu wort"
- [ ] Từ thật sự mới (chưa có trong DB) vẫn hiện đúng với "+ Queue"
- [ ] `GET /api/vocab?words=` với 200 words → không bị cap 50
- [ ] KHÔNG thêm "Arbeit" vào "Alle Wörter" panel (chỉ lọc, không hiện)
- [ ] Perf: global scan fetch chạy sau lesson vocab fetch (không block render)

## Cấm
- KHÔNG đụng vocab_master.csv, lingq_sync, bảng events/users
- KHÔNG commit/push
- asset version bump drill_horen.php: `20260530g`

## Files sửa
```
module/deutsch_web/api/vocab.php          ← nâng cap 300
module/deutsch_web/public/assets/drill.js ← collectAllLessonTokens + global scan
module/deutsch_web/views/drill_horen.php  ← version bump
```
