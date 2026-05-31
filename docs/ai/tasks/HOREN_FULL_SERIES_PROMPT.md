---
phase: B (Hören Full Series 1.x / 2.x / 3.x + Web UI Navigation)
service: deutsch_web
created_by: Module Engineer (Claude Cowork) — handoff Claude Code
created: 2026-05-31
template: prompt 7-phần (CLAUDE.md preference)
scope:
  - Extend module/scan_extract/horen_to_lesson_json.py (thêm parser 1.x/2.x/3.x + field teil/source_book)
  - Edit module/deutsch_web/views/lesson_list.php (thêm Teil tab navigation)
  - Edit module/deutsch_web/lib/lesson_loader.php (thêm field teil + source_book)
  - Edit module/deutsch_web/public/assets/drill.css (thêm list page styles)
  - Chạy generate --apply cho series 1/2/3 + regenerate series 4 với --force (thêm field mới)
  - KHÔNG sửa drill_horen.php, lesson_push.php, lingq_client.php, index.php (router)
---

# Phase B — Hören Full Series + Teil Navigation UI

Đọc theo thứ tự: `CLAUDE.md` (root) → `docs/ai/PIPELINE.md` → `module/deutsch_web/README.md`
→ `docs/ai/tasks/HOREN_LESSON_JSON_PHASE_A_PROMPT.md` (Phase A context) → file này.

---

## 1. End-user

Henry — học DTZ B1, muốn luyện cả 4 phần Hören trên `deutsch.twv.app`.
Hiện web chỉ có 30 bài série 4.x (Hören Teil 4). Cần thêm:
- ~314 bài mới: 1.x (146 bài Teil 1) + 2.x (137 bài Teil 2) + 3.x (31 bài Teil 3)
- Navigation tab "Teil 1 / 2 / 3 / 4" trên trang danh sách bài
- Chuẩn bị field `source_book` để tương lai thêm nguồn sách khác

---

## 2. Màn cuối cùng (Definition of Done)

### A. JSON generator (module/scan_extract/horen_to_lesson_json.py)

```cmd
REM Dry-run từng series:
python module\scan_extract\horen_to_lesson_json.py --dry-run --series 1
python module\scan_extract\horen_to_lesson_json.py --dry-run --series 2
python module\scan_extract\horen_to_lesson_json.py --dry-run --series 3

REM Apply:
python module\scan_extract\horen_to_lesson_json.py --apply --series 1
python module\scan_extract\horen_to_lesson_json.py --apply --series 2
python module\scan_extract\horen_to_lesson_json.py --apply --series 3

REM Re-generate series 4 để thêm field teil + source_book (merge strategy giữ LingQ/vocab):
python module\scan_extract\horen_to_lesson_json.py --apply --force --series 4

REM Validate toàn bộ:
python -c "import json,glob; [json.load(open(f,encoding='utf-8')) for f in glob.glob('module/deutsch_web/lessons/*.json')]; print('ALL VALID')"
```

Expected sau khi xong: ~344 file JSON trong `module/deutsch_web/lessons/`
(146 + 137 + 31 bài mới, 30 bài 4.x được update thêm field)

### B. Web UI (list page)

Trang `/` sau khi đăng nhập hiển thị:
- 5 tab: **Alle | Teil 1 | Teil 2 | Teil 3 | Teil 4**
- Click tab → filter instant (client-side, không reload)
- Tab active highlight màu cam (#e06030)
- Mỗi card có `data-teil="N"` để JS filter

### C. JSON schema (tất cả bài mới + 4.x re-generated)

Mỗi JSON có thêm 2 field ngay sau `niveau`:
```json
"niveau": "B1",
"teil": 1,
"source_book": "deutsch-vorbereitung",
```

---

## 3. Dữ liệu thật

### Format câu hỏi theo series

**Series 1.x (`1.1_questions.md`)** — Hören Teil 1:
```markdown
# Aufgabe 1.1 — ICE 577 nach Köln

Source: https://deutsch-vorbereitung.com/en/uebung-14234.html

## Sie haben eine Reservierung in Wagen 25. Wohin müssen Sie?

- a) Ganz nach vorne.
- b) Nach A bis C.  **(richtig)**
- c) Nach D bis F.

**Lösung:** b) Nach A bis C.
**Erklärung:** ...
```
→ **1 câu hỏi** (H2 text = question text), **3 options a/b/c**
→ `aussagen[0].label = "Sie haben eine Reservierung in Wagen 25. Wohin müssen Sie?"`
→ `aussagen[0].correct = "b"`

**Series 2.x (`2.1_questions.md`)** — Hören Teil 2:
```markdown
# Aufgabe 2.1 — Die Werte

Source: ...

## Am Mittag sollen Kinder

- a) draußen spielen
- b) in die Stadt gehen
- c) zu Hause bleiben  **(richtig)**

**Lösung:** c) zu Hause bleiben
```
→ Giống hệt 1.x: **1 câu hỏi**, **3 options a/b/c**

**Series 3.x (`3.1_questions.md`)** — Hören Teil 3:
```markdown
# Aufgabe 3.1 — Gepäckservice

Source: ...

## Thomas und Lena fahren zusammen in den Urlaub.

- a) richtig
- b) falsch  **(richtig)**

**Lösung:** b) falsch
**Erklärung:** ...

## Was soll Thomas machen?

- a) den Gepäckservice anrufen
- b) den Koffer bezahlen
- c) den Koffer in Lenas Wohnung stellen  **(richtig)**

**Lösung:** c) den Koffer in Lenas Wohnung stellen
```
→ **2 câu hỏi**: Q1 có 2 options (a=richtig / b=falsch), Q2 có 3 options (a/b/c)
→ `aussagen[0].label = "Thomas und Lena fahren zusammen in den Urlaub."`
→ `aussagen[1].label = "Was soll Thomas machen?"`

### Output JSON format (canonical)

Giống `module/deutsch_web/lessons/4.31.json` nhưng thêm `teil` + `source_book`,
và `aussagen.label` là question text (không phải "Aussage N") cho 1.x/2.x/3.x:

```json
{
  "schema_version": "deutsch_web_lesson_v1",
  "lesson_id": "1.1",
  "aufgabe": "1.1",
  "modul": "Hören",
  "niveau": "B1",
  "teil": 1,
  "source_book": "deutsch-vorbereitung",
  "thema": "ICE 577 nach Köln",
  "title": "ICE 577 nach Köln",
  "instructions": "Sie hören kurze Texte. Zu jedem Text gibt es eine Aufgabe. Kreuzen Sie die richtige Antwort an.",
  "source": {
    "origin_url": "https://deutsch-vorbereitung.com/en/uebung-14234.html",
    "lingq_lesson_id": 44743345,
    "lingq_course_id": 2747707,
    "lingq_reader_url": "https://www.lingq.com/en/learn/de/web/reader/44743345/"
  },
  "audio": {
    "url": "https://s3.amazonaws.com/media.lingq.com/resources/contents/audionorm/1.1.597e42cbf077.mp3",
    "host": "lingq_s3",
    "local_path": "input/html/deutsch-vorbereitung/horen/1.1/1.1.mp3"
  },
  "aussagen": [
    {
      "id": "1.1-1",
      "label": "Sie haben eine Reservierung in Wagen 25. Wohin müssen Sie?",
      "correct": "b",
      "options": [
        { "key": "a", "text": "Ganz nach vorne." },
        { "key": "b", "text": "Nach A bis C." },
        { "key": "c", "text": "Nach D bis F." }
      ]
    }
  ],
  "transcript": [
    { "label": "Transkription", "text": "...", "key_phrase": "..." }
  ],
  "vocab": [],
  "_meta": {
    "note_vocab_id": "null = chưa link vocab_master...",
    "note_lv": "lv = trạng thái panel...",
    "generated_by": "horen_to_lesson_json.py Phase B 2026-05-31"
  }
}
```

---

## 4. Logic implement chi tiết

### A. `horen_to_lesson_json.py` — các thay đổi

#### A1. Constants thêm

```python
INSTRUCTIONS_BY_TEIL = {
    1: "Sie hören kurze Texte. Zu jedem Text gibt es eine Aufgabe. Kreuzen Sie die richtige Antwort an.",
    2: "Sie hören eine Sendung. Zu jedem Abschnitt gibt es eine Aufgabe. Kreuzen Sie die richtige Antwort an.",
    3: "Sie hören Gespräche. Zu jedem Gespräch gibt es zwei Aufgaben. Kreuzen Sie jeweils die richtige Antwort an.",
    4: "Sie hören Aussagen zu einem Thema. Welcher der Sätze a–f passt zu den Aussagen? Lesen Sie jetzt die Sätze a–f. Dazu haben Sie eine Minute Zeit. Danach hören Sie die Aussagen.",
}
```
(INSTRUCTIONS constant cũ giữ lại = value của key 4, cho backward compat)

#### A2. Hàm `parse_questions_simple(text, lesson_id)` cho series 1.x và 2.x

```python
def parse_questions_simple(text: str, lesson_id: str):
    """1 câu hỏi, 3 options a/b/c. H2 = question text."""
    thema = ""
    hm = re.search(r"^#\s*Aufgabe\b[^\n]*?[—\-]\s*(.+?)\s*$", text, re.MULTILINE)
    if hm:
        thema = hm.group(1).strip()

    sm = re.search(r"^Source:\s*(\S+)", text, re.MULTILINE)
    origin_url = sm.group(1).strip() if sm else ""

    # H2 = question text (bài 1.x/2.x chỉ có 1 H2 duy nhất)
    headers = list(re.finditer(r"^##\s*(.+?)\s*$", text, re.MULTILINE))
    if not headers:
        logger.warning("[%s] parse_simple: không tìm thấy H2", lesson_id)
        return thema, origin_url, []

    aussagen = []
    for i, h in enumerate(headers):
        question_text = h.group(1).strip()
        block_start = h.end()
        block_end = headers[i + 1].start() if i + 1 < len(headers) else len(text)
        block = text[block_start:block_end]

        correct = ""
        options = []
        for om in OPTION_LINE.finditer(block):
            key = om.group(1)
            raw = om.group(2)
            if RICHTIG.search(raw):
                correct = key
            opt_text = clean(RICHTIG.sub("", raw))
            if opt_text:
                options.append({"key": key, "text": opt_text})

        if not options:
            continue  # bỏ H2 không có options (vd Lösung/Erklärung sections)

        aussagen.append({
            "id": f"{lesson_id}-{i+1}",
            "label": question_text,
            "correct": correct,
            "options": sorted(options, key=lambda o: o["key"]),
        })

    if not aussagen:
        logger.warning("[%s] parse_simple: 0 aussagen sau filter", lesson_id)

    return thema, origin_url, aussagen
```

#### A3. Hàm `parse_questions_teil3(text, lesson_id)` cho series 3.x

Dùng lại `parse_questions_simple` (đã xử lý đúng multi-H2).
3.x có 2 H2: Q1 (2 options richtig/falsch) và Q2 (3 options). Hàm generic đã handle.
→ **KHÔNG cần hàm riêng** — gọi `parse_questions_simple(text, lesson_id)` trực tiếp.

#### A4. Transcript cho 1.x/2.x/3.x

`parse_transcript()` hiện tại đã có fallback:
```python
if not markers:
    return [{"label": "Transkription", "text": clean(body), "key_phrase": ""}], True
```
Vì 1.x/2.x/3.x không có marker "Aussage N / Nr. N / Nummer N" trong transcript, nó sẽ tự fall vào fallback → toàn bộ text thành 1 khối. Đây là behavior đúng — không cần thay đổi.

#### A5. Audio lookup cho 1.x

Series 1.x đã có `url.md` trong nhiều folder (Phase L đã push), và entries trong `lingq_lessons.csv` với `source_local = "input/html/deutsch-vorbereitung/horen/1.X/"`.
Hàm `get_audio_url` và `get_lingq_meta` hiện tại đã xử lý đúng cả 2 nguồn.
Với 2.x và 3.x (chưa push LingQ): `audio.url = null, host = "none"` — expected.

#### A6. Sửa `build_lesson()`

Thêm tham số `series: int`:
```python
def build_lesson(lesson_id: str, csv_table: dict, date: str,
                 old_json: "dict | None" = None, series: int = 4):
```

Rẽ nhánh parser:
```python
if series in (1, 2):
    thema, origin_url, aussagen = parse_questions_simple(q_text, lesson_id)
elif series == 3:
    thema, origin_url, aussagen = parse_questions_simple(q_text, lesson_id)  # generic
else:  # series == 4
    thema, origin_url, aussagen = parse_questions(q_text, lesson_id)  # parser cũ

instructions = INSTRUCTIONS_BY_TEIL.get(series, INSTRUCTIONS)
```

Thêm `teil` + `source_book` vào lesson dict:
```python
lesson = {
    "schema_version": "deutsch_web_lesson_v1",
    "lesson_id": lesson_id,
    "aufgabe": lesson_id,
    "modul": "Hören",
    "niveau": "B1",
    "teil": series,
    "source_book": "deutsch-vorbereitung",
    ...
}
```

#### A7. Sửa `discover_ids()`

Hiện tại đã generic: `d.name.startswith(f"{series}.")` — không cần thay đổi.

#### A8. Sửa `main()`

Truyền `series=int(args.series)` vào `build_lesson()`.
Không đổi default (`--series 4` vẫn default, backward compat).

#### A9. Special case: bài 1.21

Bài 1.21 bị scrape thiếu options. Sau khi parse → `aussagen = []`.
Trong `main()`: nếu `len(lesson['aussagen']) == 0` → skip + log WARNING "0 aussagen, skip".
Counter `n_skip` tăng. KHÔNG ghi file. KHÔNG crash.

---

### B. `lesson_loader.php` — hàm `lesson_list()`

Thêm 2 field vào mỗi phần tử `$out` trong vòng `foreach`:
```php
// Tính teil: từ JSON nếu có, fallback parse từ lesson_id (ví dụ "1.5" → 1)
$t = $data['teil'] ?? null;
if ($t === null) {
    $parts = explode('.', $id, 2);
    $t = is_numeric($parts[0]) ? (int)$parts[0] : 0;
}
$out[] = [
    'lesson_id'   => $id,
    'title'       => $title,
    'thema'       => $data['thema'] ?? ($idx[$id]['chu_de'] ?? ''),
    'modul'       => $data['modul'] ?? 'Hören',
    'niveau'      => $data['niveau'] ?? '',
    'teil'        => $t,
    'source_book' => $data['source_book'] ?? 'deutsch-vorbereitung',
    'best'        => $scores[$id] ?? null,
];
```

---

### C. `views/lesson_list.php` — thêm tab bar + JS filter

**Đọc file lesson_list.php trước khi edit** (xem cấu trúc hiện tại, tìm chỗ chèn tab bar và `data-teil`).

Thêm tab bar ngay dưới `.list-head` (hoặc đầu content area):
```html
<div class="teil-tabs" id="teilTabs">
  <button class="teil-tab active" data-teil="0">Alle</button>
  <button class="teil-tab" data-teil="1">Teil 1</button>
  <button class="teil-tab" data-teil="2">Teil 2</button>
  <button class="teil-tab" data-teil="3">Teil 3</button>
  <button class="teil-tab" data-teil="4">Teil 4</button>
</div>
```

Mỗi `.lesson-card` thêm `data-teil="<?= (int)$l['teil'] ?>"`.

Thêm `<script>` inline cuối file (trước `</body>` hoặc cuối file):
```html
<script>
(function(){
  var tabs = document.querySelectorAll('.teil-tab');
  tabs.forEach(function(btn){
    btn.addEventListener('click', function(){
      var t = parseInt(this.dataset.teil, 10);
      tabs.forEach(function(b){ b.classList.remove('active'); });
      this.classList.add('active');
      document.querySelectorAll('.lesson-card').forEach(function(card){
        var ct = parseInt(card.dataset.teil, 10);
        card.style.display = (t === 0 || ct === t) ? '' : 'none';
      });
    });
  });
})();
</script>
```

---

### D. `public/assets/drill.css` — thêm list page styles

Append vào cuối file (sau tất cả styles hiện có):

```css

/* ── List page ─────────────────────────────────────────────────────────── */
.list-wrap { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
.list-head  { display: flex; justify-content: space-between; align-items: center;
              margin-bottom: 24px; }
.list-head h1 { font-size: 22px; font-weight: 700; margin: 0; }
.logout-link { font-size: 13px; color: #888; text-decoration: none; }
.logout-link:hover { color: #555; }

.teil-tabs  { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.teil-tab   { padding: 8px 20px; border-radius: 20px; border: 2px solid #ddd;
              background: #fff; cursor: pointer; font-size: 14px; font-weight: 500;
              transition: all .15s; }
.teil-tab:hover  { border-color: #e06030; color: #e06030; }
.teil-tab.active { background: #e06030; color: #fff; border-color: #e06030; }

.lesson-card { display: flex; justify-content: space-between; align-items: center;
               background: #fff; border-radius: 12px; padding: 18px 22px;
               margin-bottom: 10px; text-decoration: none; color: #222;
               box-shadow: 0 1px 4px rgba(0,0,0,.08);
               transition: box-shadow .15s, transform .1s; }
.lesson-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.13); transform: translateY(-1px); }
.lc-meta   { font-size: 12px; color: #e06030; font-weight: 600; margin-bottom: 4px;
             letter-spacing: .3px; }
.lc-title  { font-size: 16px; font-weight: 600; line-height: 1.3; }
.lc-thema  { font-size: 13px; color: #888; margin-top: 3px; }
.score-badge-sm { min-width: 44px; text-align: center; padding: 6px 12px;
                  border-radius: 20px; font-size: 14px; font-weight: 700;
                  background: #4caf50; color: #fff; flex-shrink: 0; }
.score-badge-sm.empty { background: #eee; color: #999; }
```

---

## 5. Cấm đụng

- `module/deutsch_web/views/drill_horen.php` — KHÔNG sửa (drill render đã generic)
- `module/deutsch_web/public/index.php` — KHÔNG sửa router
- `module/lingq_sync/` — KHÔNG đụng (Phase L đã land)
- `data/03_unified/vocab_master.csv` — KHÔNG đụng
- `data/lingq_lessons.csv` — chỉ READ
- `module/deutsch_web/lessons/4.29.json`, `4.30.json`, `4.31.json` — được phép --force (merge strategy trong script đã giữ vocab + LingQ data)
- Tự `git commit` / `git push`
- Chạy `--apply` trước khi `--dry-run` pass cho từng series

---

## 6. Performance / Scale

- Series 1.x: 146 bài × 1 aussage = 146 JSON, mỗi bài ~2KB → tổng ~300KB. Tốc độ generate < 10s.
- Series 2.x: 137 bài, tương tự.
- Series 3.x: 31 bài × 2 aussagen = nhanh hơn 4.x.
- Tổng cộng: ~344 file JSON trong `module/deutsch_web/lessons/`, dung lượng dự kiến < 2MB.
- `lesson_list()` trong PHP load tất cả 344 file: mỗi file ~2-5KB → peak disk I/O ~1MB.
  Với shared hosting cPanel + SSD, page load danh sách dự kiến < 1s. Chấp nhận được cho 1 user.
  (Nếu sau này lag → thêm cache file `lessons_index.json` — Phase C, không cần làm ngay)
- CSS append: file `drill.css` tăng ~60 dòng — không ảnh hưởng render hiện tại.
- JS filter client-side: `querySelectorAll` trên ~344 `.lesson-card` là O(n) tức thì.

---

## 7. Format report Claude Code in cuối

```
PHASE B — DONE / PARTIAL / BLOCKED

CODE:
- horen_to_lesson_json.py: +N / -M dòng (wc -l trước/sau)
  - parse_questions_simple(): added
  - build_lesson(): series param + INSTRUCTIONS_BY_TEIL
  - main(): series routing
- lesson_loader.php: +N dòng (thêm teil + source_book)
- views/lesson_list.php: +N dòng (tab bar + data-teil + JS)
- public/assets/drill.css: +N dòng (list page styles)

TEST — Generator:
- dry-run series 1:  PASS / FAIL (N generate, N skip)
- dry-run series 2:  PASS / FAIL
- dry-run series 3:  PASS / FAIL
- apply series 1:    PASS / FAIL (N files, bài 1.21 skip OK)
- apply series 2:    PASS / FAIL
- apply series 3:    PASS / FAIL
- apply --force 4:   PASS / FAIL (30 files, LingQ data preserved, vocab preserved)
- validate all JSON: PASS / FAIL (N files ALL VALID)
- spot-check 1.1:    aussagen=1, correct=b, audio=lingq_s3
- spot-check 2.1:    aussagen=1, correct=c
- spot-check 3.1:    aussagen=2, Q1 options=[a,b], Q2 options=[a,b,c]

TEST — UI (PHP syntax only — không có browser test):
- php -l module/deutsch_web/views/lesson_list.php: OK
- php -l module/deutsch_web/lib/lesson_loader.php: OK

ARTIFACT:
- JSON total: N files (target ~344)
- By series: 1.x=N, 2.x=N, 3.x=N, 4.x=30
- audio.host=none (cần LingQ push sau): [series 2.x, 3.x mostly]
- Errors/skipped: N (bài 1.21 expected)

NEXT (nếu PARTIAL/BLOCKED): rõ ràng cần gì
```

Sau khi xong:
- Append `docs/ai/DECISIONS.md`: `DD-20260531-008 Phase B Hören 1.x/2.x/3.x + Teil UI — ~344 lessons live`
- Xóa lock: `.ai-locks/horen_full_series.lock`
- Báo "edit xong, chờ review Cursor"

---

## Ghi chú kỹ thuật

**Lesson sort order trong web**: `lesson_list()` dùng `sort($files)` → lexicographic sort.
Kết quả: `1.1, 1.10, 1.100, 1.101, ...` (không phải numeric).
Chấp nhận — UI filter theo Teil sẽ compensate. Nếu muốn numeric sort sau này:
`usort($files, function($a, $b){ return strnatcmp($a, $b); })` — Phase C.

**drill_horen.php render label cho 1.x/2.x/3.x**:
PHP hiện tại: `<?= $num ?>. <?= h($a['label'] ?? ...) ?>`
→ Với bài 1.1: "1. Sie haben eine Reservierung in Wagen 25. Wohin müssen Sie?"
→ Với bài 3.1 Q2: "2. Was soll Thomas machen?"
Render này hợp lý, không cần sửa drill_horen.php.

**`php -l` test**: KHÔNG chạy `php -l` local (treo theo CLAUDE.md preference).
Kiểm tra PHP syntax bằng bash nếu cần: `cat file.php | grep -n "?>"` để check tag closing.

**Lock**: `.ai-locks/horen_full_series.lock` (tạo trước, xóa sau khi xong).

**Path Windows:** `C:\twv_share\app\deutsch\`
**Path bash (sandbox):** `/sessions/.../mnt/deutsch/`
