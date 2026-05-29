---
phase: L (Lessons Batch Push — Hören full)
service: lingq
created_by: Module Engineer (Claude Cowork) — handoff Claude Code
created: 2026-05-25
template: prompt 7-phần (CLAUDE.md preference)
parent_module: module/lingq_sync/
extends: Phase K (lessons_push.php K2 text + K3 audio đã verify live)
scope: extend lessons_push.php thêm cờ --batch + --limit + --sleep; KHÔNG tạo file mới
---

# Phase L — Lessons Batch Push (Hören full 344 bài)

Đọc theo thứ tự: `CLAUDE.md` (root) → `docs/ai/PIPELINE.md` → `module/lingq_sync/README.md` → file này.

Phase K (CRUD đơn) đã land 2026-05-24:
- `lessons_sync.php` fetch toàn bộ 458 lessons về `data/lingq_lessons.csv`
- `lessons_delete.php` xóa theo id (có `--apply`)
- `lessons_push.php` push 1 folder/lần (K2 text + K3 audio), verify live OK:
  - Lesen 1.1 → `lesson_id=44743333` (HTTP 201, 89% blue trên UI)
  - Hören 1.1 → `lesson_id=44743345` + audio 35s (HTTP 201 + PATCH OK)

User đã verify UI LingQ "DTZ Vorbereitung" course 2 lessons OK. Cho phép scale Hören full.

---

## 1. End-user

User henryducmanh — solo dev DTZ B1 CRUNCH, deadline 06/2026 (3–4 tuần).
Yếu nhất kỹ năng **Hören** → ưu tiên push toàn bộ Hören (có audio) để LingQ thành workspace nghe-đọc-highlight chính. Lesen để Phase M sau.

Chạy CLI từ Windows, PHP 7.4 (`C:\php\php74\php.exe`), không UI.

---

## 2. Màn cuối cùng (UX)

**Extend `lessons_push.php`** (KHÔNG tạo file mới — theo memory `feedback_module_engineer_extend_first.md`). Thêm 3 flag mới:

```cmd
REM Dry-run batch (mặc định) — in plan: bài nào sẽ push, bài nào skip
C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\*"

REM Apply batch — push thật, có rate-limit + resume
C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\*" --apply

REM Apply giới hạn 10 bài đầu (smoke test trước scale full)
C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\*" --apply --limit 10

REM Sleep giữa 2 request (default 2.0s — chống rate-limit LingQ)
C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "..." --apply --sleep 3.5
```

**Behavior:**
- `--batch <glob>` mutually exclusive với positional `<folder>`. Có cả 2 → exit 1, error "không thể vừa batch vừa single".
- Glob expand qua `glob()` PHP (sort tự nhiên — `1.1, 1.10, 1.11, 1.2` chứ KHÔNG sort numeric — chấp nhận, không phải critical).
- Mỗi folder loop gọi lại logic K2/K3 hiện có (refactor thành function `push_one_folder($folder, $opts, $client, $csvRows, $logger)`).
- `--limit N` cắt sau N folder ĐƯỢC PROCESS (không tính skip).
- `--sleep S` (float seconds) — `usleep((int)($S*1e6))` sau mỗi POST/PATCH thành công. Default 2.0s. Skip không sleep.
- Resume tự động: trước khi loop, đọc `data/lingq_lessons.csv` 1 lần, build set `$pushed = ['horen/1.1/' => 44743345, ...]`. Folder đã có `source_local` match → skip với log "skip already-pushed", trừ khi `--force-update` (PATCH luôn).
- Re-sync CSV: ở mode batch CHỈ re-sync 1 lần ở cuối (không phải sau mỗi push như K), trừ khi `--no-resync`. Lý do: tránh 344 lần re-sync × 6s = 34 phút lãng phí.
- Folder không có file expected (vd thiếu `_transcript.md`) → log WARN, skip folder đó, không exit. Counter `skipped_missing`.

**Output cuối:**
```
=== BATCH SUMMARY ===
total folders:    344
already-pushed:    1   (skip)
missing files:     5   (skip — thiếu _transcript.md hoặc .mp3 cho Hören)
pushed text-only:  0   (folder có _transcript.md nhưng không có .mp3)
pushed audio:    338
patched:           0   (--force-update)
errors:            0
elapsed:        18m32s
re-sync CSV:    458 → 802 rows (new=344)
log file:       module/lingq_sync/logs/lessons_2026-05-25.log
```

---

## 3. Ví dụ dữ liệu thật

**Input folder Hören điển hình** (`input/html/deutsch-vorbereitung/horen/1.1/`):
```
1.1.mp3              ← audio file (Hören mới có)
1.1_transcript.md    ← transcript markdown có frontmatter (bai/teil/teil_desc/chu_de/url)
1.1_questions.md     ← câu hỏi, KHÔNG push lên LingQ
```

**Coverage hiện tại (recon Cowork 2026-05-25):**
- Tổng folder Hören: **344**
- Có `.mp3`:           **339**  (5 folder thiếu audio → push text-only)
- Có `_transcript.md`: **344**  (100% coverage)

**CSV state (`data/lingq_lessons.csv` — 459 rows):**
- Columns: `lesson_id,course_id,title,language,audio_url,words_count,unknown_count,source_local,first_seen,last_synced`
- `source_local` đã có data cho `horen/1.1/` + `lesen/1.1/` (Phase K verify).
- Lưu ý parse: CSV có comma trong field title — **dùng `str_getcsv`** chứ KHÔNG `explode(',')`.

**LingQ API endpoint** (đã verify Phase K):
- `POST /api/v3/de/lessons/` body multipart text → trả `{id, ...}` HTTP 201
- `PATCH /api/v3/de/lessons/<id>/` với `audio` field multipart → upload audio
- Course id default lấy từ `config.php` key `lessons_course_id` (đã set tới course "DTZ Vorbereitung").

---

## 4. Acceptance test

Chạy theo thứ tự, dừng nếu fail:

**Test 1 — Dry-run plan đúng:**
```cmd
C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\*"
```
Expect: in plan ~344 folder, 1 đã pushed (`horen/1.1/`), 5 thiếu audio. KHÔNG gọi API thật. Exit 0.

**Test 2 — Smoke 5 bài apply:**
```cmd
C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\*" --apply --limit 5
```
Expect:
- `horen/1.1/` skip (already pushed)
- Push thử 5 folder tiếp theo theo glob sort (`1.10, 1.100, 1.101, 1.102, 1.103` — chấp nhận lexicographic, đừng cố sort numeric)
- Mỗi push: POST text HTTP 201 → PATCH audio HTTP 200
- Sleep 2.0s giữa requests
- Cuối: re-sync 1 lần, CSV tăng 5 rows. Log `lessons_2026-05-25.log` có 5 dòng "POST lesson ... HTTP 201".

User sẽ vào LingQ UI verify 5 bài hiện ở course "DTZ Vorbereitung". Nếu UI OK → confirm cho Claude Code chạy full.

**Test 3 — Full batch (chỉ sau Test 2 user OK):**
```cmd
C:\php\php74\php.exe module\lingq_sync\lessons_push.php --batch "input\html\deutsch-vorbereitung\horen\*" --apply
```
Expect: ~18–25 phút (344 × ~3s POST + 2s sleep). Exit 0. SUMMARY hiện đúng counter.

**Test 4 — Idempotent re-run:**
Chạy lại Test 3 ngay sau. Expect: tất cả 344 SKIP "already-pushed", elapsed < 10s, errors=0.

**Test 5 — Folder thiếu audio:**
Tìm 1 folder thật trong 5 folder thiếu .mp3 (debug bằng `find input/html/deutsch-vorbereitung/horen -maxdepth 2 -name "*_transcript.md" | xargs -I{} sh -c 'd=$(dirname {}); ls $d/*.mp3 2>/dev/null || echo MISSING $d'`). Chạy single mode push folder đó với `--apply` — expect push text-only thành công + log WARN "no audio file, text-only mode".

---

## 5. Cấm đụng module nào

- **KHÔNG** sửa `lessons_sync.php`, `lessons_delete.php`, `notes_builder.php`, `push.php` (cards), `sync.php`, `update_local.php`, `lingq_client.php` — Phase L chỉ extend `lessons_push.php`.
- **KHÔNG** đổi schema `data/lingq_lessons.csv` (10 cột giữ nguyên — Phase K vừa land).
- **KHÔNG** tạo file mới như `lessons_push_batch.php` / `batch_runner.php`. User đã pick "extend" — theo memory `feedback_module_engineer_extend_first.md`, default EXTEND.
- **KHÔNG** đụng `data/03_unified/vocab_master.csv` hay bất kỳ file vocab nào (Phase L làm LESSONS, không CARDS).
- **KHÔNG** tự commit / push git. Edit xong báo "edit xong, chờ review Cursor".
- **KHÔNG** tự re-sync CSV sau mỗi push trong batch mode (chỉ 1 lần cuối).

---

## 6. Performance scale

- LingQ rate limit unofficial: ~60 req/min thấy OK (Phase K test 22:27–22:36 không 429). Sleep default 2.0s = 30 req/min an toàn x2.
- 344 folder × (POST 3s + PATCH 2s audio + 2s sleep) = ~40 phút worst case. Acceptable cho overnight run.
- Memory peak: CSV 459 rows giữ in-memory dict (`source_local => lesson_id`) = ~50KB, không lo.
- Audio upload payload: mp3 thường 30s–3min ≈ 0.5–5MB → curl multipart streaming, không load full vào memory (kiểm xem `lingq_client.php` đang dùng `CURLOPT_INFILE` chưa, nếu không thì `CURLFile` cũng OK ở mức MB này).
- Log file đuôi `lessons_YYYY-MM-DD.log` append. Sau full batch dự kiến ~2000 dòng, không cần rotate.
- Network drop giữa batch: hiện chấp nhận. Lần chạy lại sẽ resume nhờ CSV dedupe. KHÔNG cần retry tự động trong Phase L (sẽ làm Phase M nếu cần).

---

## 7. Format report

Sau khi xong, paste vào chat Cowork (vai Module Engineer audit) báo cáo dạng:

```
PHASE L — DONE / PARTIAL / BLOCKED

CODE:
- file edit:    module/lingq_sync/lessons_push.php
- diff:         +X / -Y dòng (wc -l trước/sau)
- new flags:    --batch, --limit, --sleep
- helper functions tách: push_one_folder(), build_pushed_set(), ...

TEST:
- Test 1 (dry-run):  PASS / FAIL (note)
- Test 2 (smoke 5):  PASS / FAIL (lesson_ids: ...)
- Test 3 (full):     PASS / FAIL (elapsed, counter)
- Test 4 (idempotent): PASS / FAIL
- Test 5 (no-audio): PASS / FAIL

ARTIFACT:
- CSV rows trước/sau: 459 → NNN
- LingQ course "DTZ Vorbereitung" lesson count: 2 → NNN
- log file: module/lingq_sync/logs/lessons_2026-05-25.log (NNNN dòng)

NEXT (nếu PARTIAL/BLOCKED): rõ ràng cần gì
```

Đồng thời:
- Append 1 dòng vào `docs/ai/DECISIONS.md`: `DD-20260525-XXX  Phase L Hören batch push live — N lessons → LingQ "DTZ Vorbereitung"`
- Append `docs/ai/LESSONS.md` nếu có insight đáng nhớ (vd: glob sort lexicographic accept, audio upload streaming OK).
- Append `docs/ai/FAILURES.md` nếu fail (vd: rate-limit 429 ở sleep < 2s).

KHÔNG cần update README module nếu chỉ thêm flag — README hiện đã có Usage block, chỉ cần thêm 4–5 dòng `--batch` vào đó.
