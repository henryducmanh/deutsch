---
phase: K (Lessons CRUD)
service: lingq
created_by: Module Engineer (Claude Cowork) — handoff Claude Code
created: 2026-05-24
template: prompt 7-phần (CLAUDE.md preference)
parent_module: module/lingq_sync/
extends: Phase C (sync) + Phase D (push) + Phase J (enriched notes)
---

# Phase K — LingQ Lessons CRUD

Đọc `CLAUDE.md` (root) → `docs/ai/PIPELINE.md` → `module/lingq_sync/README.md` → file này.

Mục tiêu Phase K: build **CRUD lessons trên LingQ** (Phase C/D/J chỉ làm CARDS — vocab). Cần riêng LESSONS vì user muốn học từ **ngữ cảnh** (highlight vàng trong đoạn văn), không phải từ rời.

Phase chia 3 stage commit độc lập:

- **K1: LIST + DELETE** — sync lessons về CSV, xóa bài user chọn
- **K2: PUSH TEXT** — push 1 bài Lesen (markdown) lên LingQ
- **K3: PUSH AUDIO** — push 1 bài Hören (markdown + MP3) lên LingQ

User muốn cả 3, nhưng commit từng stage để có thể test/dùng dần.

---

## 1. End-user

User henryducmanh — solo dev tự học DTZ B1, deadline thi 06/2026 (3-4 tuần).
Hiện đang ôn vocab trên LingQ qua dạng từ rời (vocab_master sync xong Phase J), nhưng thấy **học qua đoạn văn highlight vàng dễ nhớ hơn**. Repo có sẵn input `input/html/deutsch-vorbereitung/{lesen,horen}/X.X/X.X_text.md` (hoặc `_transcript.md` + `X.X.mp3` cho Hören), tổng ~140 bài Hören + ~100 bài Lesen scrape sẵn từ deutsch-vorbereitung.com.

User chạy CLI từ Windows (PHP 7.4 `C:\php\php74\php.exe`), không cần UI.

---

## 2. Màn cuối cùng (UX)

3 CLI command + 1 CSV shadow:

```cmd
REM Stage K1 — list + delete
C:\php\php74\php.exe module\lingq_sync\lessons_sync.php
REM → fetch all lessons về data\lingq_lessons.csv (atomic, idempotent)
REM → in ra: "Fetched N lessons (HTTP 200, Xms)"

C:\php\php74\php.exe module\lingq_sync\lessons_delete.php <id1> <id2> ...
REM → DRY-RUN: print danh sách sẽ xóa (title, audio?, words_known, last_studied)
REM → cần --apply để thực sự DELETE

REM Stage K2 — push 1 bài Lesen
C:\php\php74\php.exe module\lingq_sync\lessons_push.php input\html\deutsch-vorbereitung\lesen\1.1\
REM → DRY-RUN: print payload JSON sẽ POST
REM → cần --apply để tạo lesson trên LingQ

REM Stage K3 — push 1 bài Hören (text + audio)
C:\php\php74\php.exe module\lingq_sync\lessons_push.php input\html\deutsch-vorbereitung\horen\1.1\
REM → tương tự nhưng có audio MP3 attached
```

CSV shadow `data/lingq_lessons.csv` (UTF-8 BOM):

```
lesson_id,course_id,title,language,audio_url,words_count,unknown_count,source_local,first_seen,last_synced
14234,12345,ICE 577 nach Köln,de,https://...lingq.com/.../audio.mp3,87,12,input/html/deutsch-vorbereitung/horen/1.1/,2026-05-24,2026-05-24
13345,12345,Center Solaris – Übersicht der Etagen,de,,156,93,input/html/deutsch-vorbereitung/lesen/1.1/,2026-05-24,2026-05-24
```

Source local path = ref ngược về folder gốc, dùng để: (a) idempotency check (đã push chưa); (b) update lesson khi text gốc đổi.

---

## 3. Ví dụ dữ liệu thật

### Input Lesen 1.1

File: `input/html/deutsch-vorbereitung/lesen/1.1/1.1_text.md`

```markdown
---
bai: 1.1
teil: 1
teil_desc: "Teil 1 – Wegweiser/Übersichten"
chu_de: "Center Solaris – Übersicht der Etagen"
url: https://deutsch-vorbereitung.com/en/uebung-13345.html
extracted_at: 2026-05-22
---

# Center Solaris – Übersicht der Etagen

Center Solaris – Übersicht der Etagen
4. Etage – Technik & Freizeit
Restaurant & Café: Mittagessen, Kaffee & Kuchen mit Blick auf die Stadt
...
```

→ Push payload (giả định LingQ POST `/api/v2/de/lessons/`):
```json
{
  "title": "Center Solaris – Übersicht der Etagen",
  "text": "Center Solaris – Übersicht der Etagen\n4. Etage – Technik & Freizeit\nRestaurant & Café: ...",
  "language": "de",
  "collection": <course_id>,
  "level": 3,
  "save": true,
  "source_url": "https://deutsch-vorbereitung.com/en/uebung-13345.html",
  "external_audio": null,
  "tags": ["DTZ", "B1", "Lesen", "Teil1"]
}
```

### Input Hören 1.1

Folder: `input/html/deutsch-vorbereitung/horen/1.1/`
```
1.1.mp3                  ← audio file (binary, ~few MB)
1.1_transcript.md        ← transcript markdown với frontmatter giống Lesen
1.1_questions.md         ← câu hỏi multiple choice (KHÔNG push lên LingQ)
```

→ Push: tương tự Lesen nhưng kèm audio. Hai cách (Claude Code research API docs, chọn 1):
- A) Upload audio file qua multipart `audio` field
- B) Upload audio lên storage public (vd Cloudinary/S3) → đặt `external_audio` URL

Cách A ưu tiên nếu LingQ API hỗ trợ. Cách B fallback.

Tags Hören: `["DTZ", "B1", "Hören", "Teil<N>"]` — đọc `<teil>` từ frontmatter transcript.

### Cross-ref vocab_master

Trong khi push, KHÔNG cần tự highlight — LingQ tự match từ với cards của user (qua `lingq_target.csv` Phase D đã sync). Nhưng có thể tận dụng:
- Đọc `data/03_unified/vocab_master.csv` lấy danh sách từ user đã biết
- Log preview: "Bài 1.1 chứa N từ trong vocab_master (M chưa biết)"

Optional cho stage K2/K3.

---

## 4. Acceptance test

### K1: lessons_sync.php
- [ ] Chạy lần 1 (chưa có CSV): fetch toàn bộ lessons, ghi `data/lingq_lessons.csv` atomic (tmp + rename).
- [ ] Chạy lần 2 (CSV đã có): idempotent — `first_seen` giữ nguyên, `last_synced` cập nhật.
- [ ] Pagination giống Phase C: `?page=N&page_size=200`, retry 5xx 1s/3s/9s.
- [ ] Output console: "Fetching page 1... N lessons (HTTP 200, Xms)" per page.
- [ ] Log `module/lingq_sync/logs/lessons_YYYY-MM-DD.log` per run.
- [ ] CSV sort theo `lesson_id` asc cho stable diff.

### K1: lessons_delete.php
- [ ] Input: 1+ lesson_id từ argv. Đọc CSV để lấy title/audio_url để hiện preview.
- [ ] Default DRY-RUN: in danh sách bài sẽ xóa + tổng số + cảnh báo. KHÔNG gọi API.
- [ ] Với `--apply`: gọi `DELETE /lessons/<id>/` từng cái, retry 5xx, 404 = treat as already-gone (giống Phase D deleteCard).
- [ ] Sau --apply: re-sync CSV (tự gọi lessons_sync.php hoặc inline) → CSV không còn ID đã xóa.
- [ ] Snapshot backup `data/lingq_lessons_backup_<timestamp>.csv` trước khi --apply (giống Phase D).

### K2: lessons_push.php (text-only)
- [ ] Input: 1 folder path từ argv (vd `input/html/deutsch-vorbereitung/lesen/1.1/`).
- [ ] Parse frontmatter markdown lấy `bai`, `teil`, `chu_de`, `url`.
- [ ] Body = phần markdown sau frontmatter, strip heading `#` đầu nếu trùng title.
- [ ] Idempotency: nếu `source_local` (relative path) đã có trong CSV → skip với cảnh báo "Already pushed (lesson_id=X). Use --force-update để PATCH."
- [ ] Default DRY-RUN: print payload JSON full.
- [ ] Với `--apply`: POST `/de/lessons/`, response 201/200 → ghi lesson_id vào CSV, log thành công.
- [ ] Course handling: cần `course_id`. Đọc từ config (`config.php` thêm key `lessons_course_id`). Nếu chưa có → in cảnh báo + hướng dẫn create manually trên LingQ + paste ID vào config.

### K3: lessons_push.php (with audio)
- [ ] Detect: folder có `*.mp3` → mode audio.
- [ ] Research API: thử multipart upload trước (cách A). Nếu API không support → fallback cảnh báo user upload audio thủ công + paste URL.
- [ ] Transcript là `*_transcript.md` (không phải `*_text.md` như Lesen) — Claude Code phải handle cả 2 tên.
- [ ] Audio path ghi vào CSV `audio_url` = LingQ-hosted URL trả về từ response.

### Toàn module
- [ ] PHP 7.4 strict (existing code dùng `array_key_exists`, `isset`, no PHP 8 syntax).
- [ ] UTF-8 BOM cho mọi CSV mới (giống Phase C/D).
- [ ] Log format giống Phase C/D: `YYYY-MM-DD HH:MM:SS [LEVEL] message`.
- [ ] Không break Phase C/D/J — `sync.php`, `push.php`, `update_local.php` chạy y nguyên sau khi cài Phase K.

---

## 5. Cấm đụng

- **KHÔNG sửa logic CARD trong `lingq_client.php`** (fetchAllCards, createCard, updateCard, deleteCard). Chỉ ADD methods mới cho lessons: `fetchAllLessons()`, `getLesson($id)`, `createLesson($payload)`, `updateLesson($id, $patch)`, `deleteLesson($id)`, `uploadLessonAudio($lesson_id, $file_path)` (nếu cần).
- **KHÔNG sửa `sync.php`, `push.php`, `update_local.php`, `notes_builder.php`** — đây là Phase C/D/J đã ổn, độc lập với Phase K.
- **KHÔNG đụng `data/03_unified/vocab_master.csv`, `data/lingq_cards.csv`, `data/lingq_target.csv`** — read-only ref. Phase K có CSV riêng `data/lingq_lessons.csv`.
- **KHÔNG bịa endpoint LingQ API**. Trước khi code POST/PATCH/DELETE lessons, MUST verify endpoint thật bằng cách:
  1. Đọc LingQ API docs tại https://www.lingq.com/apidocs/ (hoặc tương đương)
  2. HOẶC sniff request thực tế từ browser khi user upload lesson manual trên web (mở DevTools Network tab, paste cURL example vào prompt)
  3. HOẶC test fetch 1 lesson đã có (GET) để biết shape response trước, rồi suy POST payload từ đó
- **KHÔNG tự `git commit` / `git push`**. Edit xong báo "edit xong, chờ review trong Cursor" — user commit từ Cursor CHANGES panel sau khi review diff.
- **KHÔNG chạy `php -l`** local (treo trên Windows mount). Verify syntax bằng cách chạy `--dry-run` thực tế.

---

## 6. Performance scale

- User có ~140 cards (vocab) đã sync — lessons có thể 0-100 bài, không lớn.
- Pagination 200/page giống Phase C — toàn bộ lessons load trong 1-3 page.
- Audio upload ≤ 10MB/bài (MP3 deutsch-vorbereitung trung bình 2-5MB).
- Retry policy kế thừa Phase C: 5xx backoff 1s/3s/9s, 4xx fast-fail.
- Rate limit: `sleep_ms = 500` giữa requests (config hiện có).
- 1 run `lessons_sync.php` mục tiêu < 30s.
- 1 run `lessons_push.php` mục tiêu < 60s/bài (kể cả upload audio).

---

## 7. Format report

Sau khi xong mỗi stage, Claude Code commit riêng + report về user trong CHANGES panel Cursor.

Mỗi commit:

```
[Phase K1] LingQ Lessons LIST + DELETE

- Add LingqClient::fetchAllLessons, deleteLesson, getLesson
- Add module/lingq_sync/lessons_sync.php (paginated fetch → data/lingq_lessons.csv)
- Add module/lingq_sync/lessons_delete.php (DRY-RUN default, --apply, snapshot backup)
- Update README.md: Phase K section
- Acceptance: chạy lessons_sync.php → fetched N bài; lessons_delete.php <id> --apply → DELETE 204, CSV re-sync

Files:
- module/lingq_sync/lingq_client.php  (extended +M lines)
- module/lingq_sync/lessons_sync.php  (new, N lines)
- module/lingq_sync/lessons_delete.php (new, N lines)
- module/lingq_sync/README.md  (extended)
- data/lingq_lessons.csv  (generated, gitignored if needed)
```

Cuối Phase K (cả 3 stage xong): update `docs/LINGQ_INTEGRATION.md` với section Phase K full + flow diagram.

---

## Đầu vào sẵn có

- `module/lingq_sync/lingq_client.php` — pattern cURL retry/backoff đã chuẩn (đọc trước khi extend)
- `module/lingq_sync/sync.php` — pattern paginated fetch + atomic CSV write
- `module/lingq_sync/push.php` — pattern dry-run + --apply + safety threshold
- `module/lingq_sync/config.php` — đã có `api_key`, `language=de`, `base_url`, retry config
- `data/lingq_cards.csv` schema v2 — pattern UTF-8 BOM 12 cột (Phase J)
- `input/html/deutsch-vorbereitung/{lesen,horen}/X.X/` — input thật 140+100 bài

## Thiếu — user cần bổ sung

- `course_id` LingQ DE (1 collection user tạo manually trên LingQ web để chứa lessons push). User sẽ tạo + paste vào `config.php` field mới `lessons_course_id`.
- LingQ API endpoint chính xác cho lessons (verify bước 1).

---

**Acceptance final**: user chạy `lessons_sync.php` thấy ~140 từ trên LingQ trong CSV → chạy `lessons_delete.php 12345 --apply` xóa được 1 bài → chạy `lessons_push.php input/html/deutsch-vorbereitung/lesen/1.1/ --apply` thấy bài Center Solaris hiện trên LingQ web với highlight vàng.
