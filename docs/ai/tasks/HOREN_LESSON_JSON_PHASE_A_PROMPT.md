---
phase: A (Hören Lesson JSON Generator)
service: deutsch_web
created_by: Module Engineer (Claude Cowork) — handoff Claude Code
created: 2026-05-31
template: prompt 7-phần (CLAUDE.md preference)
parent_module: module/scan_extract/ + module/deutsch_web/lessons/
scope: NEW file horen_to_lesson_json.py + cron_generate_lessons.bat. KHÔNG edit lessons_push.php, lingq_client.php, deutsch_web/*.php
---

# Phase A — Hören Lesson JSON Generator (4.x series)

Đọc theo thứ tự: `CLAUDE.md` (root) → `docs/ai/PIPELINE.md` → `module/deutsch_web/README.md` → file này.

Context:
- 30 bài Hören series 4.x đã scrape tại `input/html/deutsch-vorbereitung/horen/4.*/`
- Chỉ 4.29, 4.30, 4.31 đã có lesson JSON tại `module/deutsch_web/lessons/`
- Cần pipeline tự động: detect bài 4.x mới → push LingQ (lấy audio URL) → generate JSON
- Schema mẫu: `module/deutsch_web/lessons/4.31.json` (canonical reference)

---

## 1. End-user

Henry — solo DTZ B1 CRUNCH. Muốn thêm bài Hören lên `deutsch.twv.app` mà không phải làm tay từng bài. Chạy CLI từ Windows, PHP 7.4 + Python 3.x.

---

## 2. Màn cuối cùng (Definition of Done)

### File 1: `module/scan_extract/horen_to_lesson_json.py`

```
C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\4.*" --apply
C:\php\php74\php.exe module\lingq_sync\lessons_sync.php
python module\scan_extract\horen_to_lesson_json.py --dry-run
python module\scan_extract\horen_to_lesson_json.py --apply
```

Flags:
- `--dry-run`  : in plan (bài nào sẽ generate / skip). KHÔNG ghi file. (Default)
- `--apply`    : ghi thật JSON vào `module/deutsch_web/lessons/{id}.json`
- `--id 4.2`   : chỉ xử lý 1 bài (debug)
- `--force`    : overwrite JSON đã tồn tại (mặc định skip)
- `--series 4` : chỉ xử lý series 4.x (default và hardcoded — scope Phase A)

Output (dry-run):
```
=== HÖREN LESSON JSON GENERATOR — DRY RUN ===
Input : input/html/deutsch-vorbereitung/horen/4.*/
Output: module/deutsch_web/lessons/
---
4.2   → GENERATE  (audio: lingq_s3 | aussagen: 3 | transcript: ok)
4.3   → GENERATE  (audio: lingq_s3 | aussagen: 3 | transcript: ok)
4.10  → GENERATE  (audio: lingq_s3 | aussagen: 3 | transcript: ok)
4.29  → SKIP      (already exists: module/deutsch_web/lessons/4.29.json)
4.30  → SKIP      (already exists)
4.31  → SKIP      (already exists)
...
---
TOTAL: 27 to generate, 3 skip, 0 errors
Run with --apply to write files.
```

Output (apply):
```
=== HÖREN LESSON JSON GENERATOR ===
4.2   → OK    module/deutsch_web/lessons/4.2.json
4.3   → OK    module/deutsch_web/lessons/4.3.json
...
4.28  → OK    module/deutsch_web/lessons/4.28.json
---
Generated: 27  Skipped: 3  Errors: 0
Next: git add module/deutsch_web/lessons/*.json && git commit && git pull trên server
```

### File 2: `module/scan_extract/cron_generate_lessons.bat`

```bat
@echo off
setlocal
set ROOT=C:\twv_share\app\deutsch
set PHP=C:\php\php74\php.exe
set PYTHON=python
set LOG=%ROOT%\module\scan_extract\logs\lesson_gen_%date:~-4,4%%date:~-10,2%%date:~-7,2%.log

echo [%date% %time%] === HOREN LESSON GEN START === >> "%LOG%" 2>&1

:: Step 1: Push 4.x chưa có lên LingQ (idempotent — bài đã push sẽ skip tự động)
%PHP% %ROOT%\module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\4.*" --apply --sleep 2.0 >> "%LOG%" 2>&1

:: Step 2: Sync lingq_lessons.csv (cập nhật audio_url mới)
%PHP% %ROOT%\module\lingq_sync\lessons_sync.php >> "%LOG%" 2>&1

:: Step 3: Generate lesson JSON
%PYTHON% %ROOT%\module\scan_extract\horen_to_lesson_json.py --apply >> "%LOG%" 2>&1

echo [%date% %time%] === HOREN LESSON GEN END === >> "%LOG%" 2>&1
endlocal
```

Tạo thư mục log nếu chưa có: `module/scan_extract/logs/` (thêm `.gitkeep`).

---

## 3. Dữ liệu thật

### Input folder điển hình: `input/html/deutsch-vorbereitung/horen/4.2/`

```
4.2.mp3              ← audio local (luôn có)
4.2_questions.md     ← câu hỏi + đáp án (parse chính)
4.2_transcript.md    ← transcript nghe
url.md               ← LingQ data (có hoặc rỗng)
```

### `4.2_questions.md` format:

```markdown
# Aufgabe 4.2 — Kinderbetreuung

Source: https://deutsch-vorbereitung.com/en/uebung-12874.html

## Aussage 1

- a) Kinder brauchen den Austausch mit anderen Kindern.
- b) Kinder gewöhnen sich schnell an den Kita-Alltag.
- c) Großeltern unterstützen, möchten aber Zeit für sich behalten.  **(richtig)**
- d) Es gibt finanzielle Hilfe für die Betreuung, auch wenn sie teuer ist.
- e) Mindestens ein Elternteil sollte im ersten Jahr beim Kind bleiben.
- f) Jede Familie entscheidet selbst, was gut zum Alltag passt.

**Lösung:** c) Großeltern...
**Erklärung:** ...
```

### `4.2_transcript.md` format:

```markdown
# Transcript — 4.2 Kinderbetreuung

Source: https://...

[intro paragraph]

Aussage 1 [text Aussage 1]

Aussage 2 [text Aussage 2]

Aussage 3 [text Aussage 3]
```

### `url.md` format (bài đã push LingQ):

```markdown
# URLs — 4.29 Digitalisierung in der Bildung

## Nguồn gốc
- Source: https://deutsch-vorbereitung.com/en/uebung-1233.html

## LingQ
- Lesson: https://www.lingq.com/en/learn/de/web/reader/44825394/
- lesson_id: 44825394
- course_id: 2747707
- Audio: https://s3.amazonaws.com/media.lingq.com/resources/contents/audio/4.29.a0dc1a5e1c85.mp3
- Pushed: 2026-05-29 (text + audio OK)
```

### `data/lingq_lessons.csv` (fallback audio lookup):

```
lesson_id,course_id,title,language,audio_url,...,source_local,...
44762451,2747707,"...",de,https://s3.amazonaws.com/.../1.10.e493db6fb10a.mp3,...,input/html/deutsch-vorbereitung/horen/1.10/,...
```
Match bằng: `source_local == f"input/html/deutsch-vorbereitung/horen/{id}/"` (trailing slash).

### Schema output `{id}.json` — xem `module/deutsch_web/lessons/4.31.json` làm canonical.

Field mapping rõ:

| JSON field | Nguồn |
|---|---|
| `schema_version` | hardcode `"deutsch_web_lesson_v1"` |
| `lesson_id` | basename folder (vd `"4.2"`) |
| `aufgabe` | basename folder |
| `modul` | hardcode `"Hören"` |
| `niveau` | hardcode `"B1"` |
| `thema` | phần sau `—` trong title `# Aufgabe {id} — {thema}` |
| `title` | `{thema}` (same as thema nếu không có subtitle) |
| `instructions` | hardcode (constant cho tất cả 4.x — xem bên dưới) |
| `source.origin_url` | dòng `Source:` trong `_questions.md` |
| `source.lingq_lesson_id` | từ `url.md` → `lesson_id:` (int) hoặc từ lingq_lessons.csv |
| `source.lingq_course_id` | từ `url.md` → `course_id:` (int) hoặc từ lingq_lessons.csv |
| `source.lingq_reader_url` | từ `url.md` → `Lesson:` hoặc construct `https://www.lingq.com/en/learn/de/web/reader/{lesson_id}/` |
| `audio.url` | Ưu tiên: `url.md` → `Audio:` → lingq_lessons.csv `audio_url`. Nếu không có: `null` |
| `audio.host` | `"lingq_s3"` nếu url chứa `s3.amazonaws.com`; `"none"` nếu null |
| `audio.local_path` | `f"input/html/deutsch-vorbereitung/horen/{id}/{id}.mp3"` (luôn set) |
| `aussagen` | parse từ `_questions.md` (xem logic bên dưới) |
| `transcript` | parse từ `_transcript.md` (xem logic bên dưới) |
| `vocab` | hardcode `[]` — Vocab Extractor điền sau |
| `_meta.generated_by` | `f"horen_to_lesson_json.py Phase A {date}"` |
| `_meta.note_vocab_id` | copy từ 4.31.json |

**`instructions` constant cho tất cả 4.x:**
```
"Sie hören Aussagen zu einem Thema. Welcher der Sätze a–f passt zu den Aussagen? Lesen Sie jetzt die Sätze a–f. Dazu haben Sie eine Minute Zeit. Danach hören Sie die Aussagen."
```

---

## 4. Logic parse chi tiết

### Parse `_questions.md` → `aussagen[]`

```
1. Đọc toàn bộ file.
2. Tìm "# Aufgabe {id} — {thema}" → extract thema (strip whitespace).
3. Tìm "Source: {url}" → origin_url.
4. Split theo `## Aussage N` headers → list block.
5. Mỗi block:
   a. Dòng `- {key}) {text}  **(richtig)**` → correct = key, text = clean text (bỏ **(richtig)**)
   b. Dòng `- {key}) {text}` (không có richtig) → option bình thường
   c. Bỏ các dòng `**Lösung:**`, `**Erklärung:**` và phần tiếp theo (không cần trong JSON)
   d. Tạo: {"id": f"{id}-{N}", "label": f"Aussage {N}", "correct": key, "options": [...]}
6. Thứ tự options: a → b → c → d → e → f (sort theo key)
```

### Parse `_transcript.md` → `transcript[]`

```
1. Đọc toàn bộ file.
2. Bỏ header (`# Transcript...`), dòng `Source:`, và đoạn intro (trước "Aussage 1").
3. Split theo pattern `^Aussage \d+` (regex, đầu dòng hoặc trong paragraph).
4. Mỗi segment:
   a. text = clean text (strip whitespace, join dòng)
   b. key_phrase = câu đầu tiên (split ".")[0] + "." — max 120 chars, cắt ở từ cuối nếu quá dài
   c. Tạo: {"label": f"Aussage {N}", "text": text, "key_phrase": key_phrase}
5. Nếu không thể split rõ ràng (format khác) → tạo 1 entry: {"label": "Transcript", "text": full_text, "key_phrase": ""}
   → log WARN "transcript split failed for {id}, using full text"
```

### Lookup audio URL (priority order)

```python
def get_audio_url(id, url_md_path, lingq_csv_path):
    # 1. url.md → Audio: line
    if exists(url_md_path) and size > 0:
        match = re.search(r'^- Audio: (https://\S+)', content, re.MULTILINE)
        if match: return match.group(1), "lingq_s3"

    # 2. lingq_lessons.csv → source_local match
    source_local = f"input/html/deutsch-vorbereitung/horen/{id}/"
    row = csv_lookup(lingq_csv_path, 'source_local', source_local)
    if row and row['audio_url']:
        return row['audio_url'], "lingq_s3"

    # 3. No audio URL found
    return None, "none"
```

### Lookup LingQ metadata (priority order)

```python
def get_lingq_meta(id, url_md_path, lingq_csv_path):
    # 1. url.md
    if exists(url_md_path) and size > 0:
        lesson_id  = re.search(r'^- lesson_id: (\d+)', ...) → int
        course_id  = re.search(r'^- course_id: (\d+)', ...) → int
        reader_url = re.search(r'^- Lesson: (https://\S+)', ...) → str
        if lesson_id: return lesson_id, course_id, reader_url

    # 2. lingq_lessons.csv
    row = csv_lookup(lingq_csv_path, 'source_local', ...)
    if row and row['lesson_id']:
        lid = int(row['lesson_id'])
        cid = int(row['course_id']) if row['course_id'] else None
        return lid, cid, f"https://www.lingq.com/en/learn/de/web/reader/{lid}/"

    # 3. Not pushed yet
    return None, None, None
```

---

## 5. Cấm đụng

- `module/lingq_sync/lessons_push.php` — KHÔNG sửa (Phase L đã land, chỉ gọi)
- `module/lingq_sync/lingq_client.php` — KHÔNG sửa
- `module/deutsch_web/*.php` — KHÔNG sửa (Phase 1 đã live)
- `data/03_unified/vocab_master.csv` — KHÔNG đụng (vocab = [] để sau)
- `data/lingq_lessons.csv` — chỉ READ, KHÔNG write
- `module/deutsch_web/lessons/4.29.json`, `4.30.json`, `4.31.json` — KHÔNG overwrite (trừ khi `--force`)
- Tự `git commit` / `git push`
- Tự chạy `--apply` trước khi `--dry-run` pass

---

## 6. Performance / Scale

- 30 bài 4.x (có thể mở rộng khi scraper thêm bài) — xử lý < 5s không cần concurrency
- `lingq_lessons.csv` đọc 1 lần vào dict, tra lookup O(1)
- Ghi JSON: `json.dumps(..., ensure_ascii=False, indent=2)` + atomic write (tmp → rename)
- Nếu parse lỗi 1 bài (exception) → log ERROR + continue, không exit. Counter `errors`
- Log file: `module/scan_extract/logs/horen_lesson_json_{date}.log` (append)
- `cron_generate_lessons.bat`: tổng thời gian ~10-20 phút (dominated bởi LingQ push 27 bài × ~5s)

---

## 7. Format report Claude Code in cuối

```
PHASE A — DONE / PARTIAL / BLOCKED

CODE:
- file mới:  module/scan_extract/horen_to_lesson_json.py  (N dòng)
- file mới:  module/scan_extract/cron_generate_lessons.bat
- dir mới:   module/scan_extract/logs/.gitkeep

TEST:
- dry-run:    PASS / FAIL (output plan đúng N bài)
- --id 4.2:   PASS / FAIL (JSON path, json valid, aussagen count, audio url)
- --apply:    PASS / FAIL (N files generated)
- idempotent: PASS / FAIL (re-run → all skip)

ARTIFACT:
- JSON generated: N files tại module/deutsch_web/lessons/
- Bài không có audio URL (audio.host=none): [liệt kê id]
- Bài parse warn (transcript split fail): [liệt kê]
- Errors: N

NEXT (nếu PARTIAL/BLOCKED): rõ ràng cần gì
```

Sau khi xong:
- Append `docs/ai/DECISIONS.md`: `DD-20260531-007 Phase A Hören JSON Generator — horen_to_lesson_json.py live`
- Không cần update README module nếu script standalone có docstring đầy đủ

KHÔNG cần update README module nếu script standalone — docstring + --help là đủ.

---

## Ghi chú kỹ thuật

**Dependencies Python (đã có từ horen_scraper.py):**
```bash
pip install requests --break-system-packages
# csv, json, re, os, pathlib, datetime — stdlib
```

**Path root project (Windows):** `C:\twv_share\app\deutsch\`
**Path bash (sandbox):** `/sessions/.../mnt/deutsch/`

**Tạo lock:**
```
.ai-locks/horen_lesson_json_phase_a.lock
```
Xoá lock sau khi xong.

**Test nhanh 1 bài trước --apply:**
```cmd
python module\scan_extract\horen_to_lesson_json.py --id 4.2 --apply
REM → check: module\deutsch_web\lessons\4.2.json tồn tại, valid JSON, aussagen.length == 3
```

**Validate JSON sau generate:**
```cmd
python -c "import json,glob; [json.load(open(f)) for f in glob.glob('module/deutsch_web/lessons/*.json')]; print('ALL VALID')"
```
