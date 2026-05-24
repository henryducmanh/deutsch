# LingQ Sync — Phase C + Phase D v1 + Phase F + Phase J

**Phase C (sync):** daily pull của LingQ status về repo qua PHP CLI.
Output: `data/lingq_cards.csv` (shadow store).

**Phase D (push):** 2-way sync với `data/03_unified/vocab_master.csv` làm source of truth.
- `update_local.php` regenerate `data/lingq_target.csv` từ vocab_master (no API).
- `push.php` diff target vs snapshot → POST/PATCH/DELETE lên LingQ.
- `cron.bat` orchestrate 4 step: sync → update_local → push → sync.

Spec gốc: `docs/ai/tasks/LINGQ_SYNC_PROMPT.md` (Phase C), `docs/ai/tasks/LINGQ_PUSH_PROMPT.md` (Phase D).

---

## Quick start (5 dòng)

```
copy module\lingq_sync\config.example.php module\lingq_sync\config.php
REM Lấy token tại https://www.lingq.com/accounts/apikey/ và paste vào config.php
C:\php\php74\php.exe module\lingq_sync\sync.php --dry-run
C:\php\php74\php.exe module\lingq_sync\sync.php
schtasks /create /tn "LingQ Sync Daily" /tr "C:\twv_share\app\deutsch\module\lingq_sync\cron.bat" /sc daily /st 06:00
```

---

## Files

| File | Phase | Mô tả |
|---|---|---|
| `config.example.php` | C+D | Template config. Copy thành `config.php` (gitignored). Phase D thêm `push_thresholds`. |
| `lingq_client.php` | C+D | cURL client. Phase C: `fetchAllCards()`. Phase D: `createCard()`, `updateCard()`, `deleteCard()`. Retry 5xx backoff 1s/3s/9s; 4xx fast-fail (delete 404 = treat as already-gone). |
| `sync.php` | C | Pull LingQ → `data/lingq_cards.csv` (atomic write, idempotent). |
| `update_local.php` | D+J | Đọc `vocab_master.csv` (+ chunks_master, weak_words, MISTAKES_LOG khi Phase J) → render `data/lingq_target.csv`. Không gọi API. |
| `push.php` | D+J | Diff target vs snapshot → POST/PATCH/DELETE. Phase J: notes diff với merge logic. Default = dry-run; cần `--apply`. |
| `notes_builder.php` | J | Utility: `build_enriched_notes()`, `merge_notes_for_patch()`, `parse_mistakes_log()`, `truncate_to_max()`, loader 3 nguồn. |
| `cron.bat` | D | Orchestrator 4 step daily 10:00 (sync → update_local → push --auto-confirm → sync). |
| `logs/` | C+D | `YYYY-MM-DD.log` per-run; `cron_YYYY-MM-DD.log` orchestrator. |
| `../../data/lingq_cards.csv` | C+J | Shadow snapshot — UTF-8 BOM, 12 cột (v2 Phase J). v1 11-col tự upgrade lần sync đầu. |
| `../../data/lingq_target.csv` | D+J | Desired state — UTF-8 BOM, 12 cột match snapshot schema. |
| `../../data/lingq_cards_backup_*.csv` | D | Snapshot backup trước mỗi DELETE phase. KHÔNG xoá manual. |

---

## CSV schema

```
v2 (Phase J, 12 cột):
lingq_id,term,fragment,hint,status,extended_status,tags,importance,last_studied_correct,first_seen,last_synced,notes

v1 (pre-Phase-J, 11 cột — auto upgrade lần sync đầu sau khi cài Phase J):
lingq_id,term,fragment,hint,status,extended_status,tags,importance,last_studied_correct,first_seen,last_synced
```

- `tags` join bằng `;` (không phải `,`) để không vỡ CSV.
- `fragment` + `notes` escape `\n` thành literal `\n` (mỗi row 1 dòng).
- `first_seen` set lần đầu — KHÔNG đổi khi update (kể cả lúc rebuild v1→v2).
- `last_synced` cập nhật mỗi sync run.
- `notes` (Phase J) = markdown enriched với marker prefix `[AI-sync YYYY-MM-DD | VOC-...]`.
- Sort theo `lingq_id` (asc) cho stable diff.

Status mapping (LingQ → German Brain):

| `status` | `extended_status` | nghĩa |
|---|---|---|
| 1 | 0 | mới |
| 2 | 0 | nhận biết |
| 3 | 0 | quen |
| 4 | 0 | thành thạo |
| 4 | 3 | known (out of LingQ count) |

---

## Acceptance tests

1. **Bootstrap config:** `copy config.example.php config.php` → paste token.
2. **Dry run:** `php sync.php --dry-run` → in plan, không write csv, exit 0.
3. **Live sync lần 1:** `php sync.php` → exit 0, csv ≥ 2728 rows, log file tồn tại.
4. **Idempotent:** chạy lại → `New: 0, Updated: 0`.
5. **Update detection:** đổi status 1 LingQ trên web → chạy lại → `Updated: 1`, `first_seen` giữ nguyên.
6. **Wrong token:** sửa `api_key` sai → exit 1, log `HTTP 401 Unauthorized`, csv cũ không bị xoá.
7. **5xx retry:** `base_url=https://httpstat.us/503` → exit 1 sau 3 retry với backoff 1s/3s/9s.
8. **Cron:** đăng ký task → 6:00 AM hôm sau có `logs/cron_YYYY-MM-DD.log`.

---

## Troubleshooting

### HTTP 401 Unauthorized
Token sai/hết hạn. Lấy lại tại https://www.lingq.com/accounts/apikey/ và paste vào `config.php` dòng `api_key`.

### HTTP 403 Forbidden
Token đúng nhưng không có quyền với language hiện tại. Check Premium status hoặc giá trị `language` (mặc định `de`).

### Timeout / cURL errno 28
Mạng chậm. Tăng `timeout` trong `config.php` từ 30 → 60. Retry tự backoff 1s/3s/9s sẵn.

### CSV bị corrupt
Atomic write protect (write `.tmp` → rename), nhưng nếu disk full giữa chừng vẫn có thể bị mất. Khôi phục:
- Check git log → restore `data/lingq_cards.csv` từ commit cũ.
- Hoặc xoá CSV → chạy lại `php sync.php`, lúc đó `first_seen` sẽ reset cho mọi row (mất history thời điểm phát hiện).

### CSV header mismatch warning
Có nghĩa schema CSV cũ khác schema hiện tại. Sync sẽ treat as empty (rebuild from scratch). Backup CSV cũ trước nếu cần history `first_seen`.

### Cron không chạy
- Kiểm tra Task Scheduler: `schtasks /query /tn "LingQ Sync Daily"`.
- Test manual: `cron.bat` từ cmd → có generate log file?
- Path `C:\php\php74\php.exe` đúng? Đổi trong `cron.bat` nếu PHP install ở chỗ khác.

### `php -l` treo trên Windows mount
Theo CLAUDE.md user, KHÔNG dùng `php -l` để verify syntax — chạy thật bằng `php sync.php --dry-run` thay thế.

---

## Cấm đụng (echo từ spec)

- ❌ `data/03_unified/vocab_master.csv` — vai Vocab Extractor merge sau.
- ❌ `data/01_ai_extracted/` — archive raw.
- ❌ `data/chunks_master.csv`, `data/weak_words.csv`, `data/sources_master.csv`.
- ❌ Hard-code API key.
- ❌ Composer / vendor — pure PHP stdlib.
- ❌ Auto git commit/push.

---

---

## Phase D — push (2-way sync)

### Concept

```
vocab_master.csv  ──► update_local.php  ──► lingq_target.csv
                                              │
                                              ▼
                                          push.php  ◄──── lingq_cards.csv (snapshot)
                                              │
                                              ▼
                                          LingQ API (POST/PATCH/DELETE)
                                              │
                                              ▼
                                          sync.php  ──► lingq_cards.csv (refresh)
```

- `vocab_master.csv` = source of truth (curate manual, B1 DTZ).
- `lingq_target.csv` = desired state (derived, 11 cột match snapshot).
- `lingq_cards.csv` = mirror server (read-only outside `sync.php`).

### Diff rules

Match key: `term_lower = strtolower(trim(term))`.

| Target | Snapshot | Action | API |
|---|---|---|---|
| ✓ | ✗ | CREATE | `POST /de/cards/` với status=1 từ target |
| ✓ | ✓ (field khác) | UPDATE | `PATCH /de/cards/{pk}/` chỉ field thay đổi, **không đụng status** |
| ✗ | ✓ | DELETE | `DELETE /de/cards/{pk}/` |

UPDATE chỉ trigger khi `fragment`, `hint`, hoặc `tags` (set equality) khác. `status` luôn lấy từ server (user review trên LingQ mobile/web).

### Quick start Phase D

```
# 1. Regenerate target từ vocab_master (no API):
C:\php\php74\php.exe module\lingq_sync\update_local.php

# 2. Dry-run xem plan:
C:\php\php74\php.exe module\lingq_sync\push.php

# 3. Test single POST (verify LingQ API format trước khi mass push):
C:\php\php74\php.exe module\lingq_sync\push.php --apply --limit=1 --skip-delete --skip-update

# 4. First wipe + push full (1 lần duy nhất):
C:\php\php74\php.exe module\lingq_sync\push.php --apply --confirm-delete=2728 --force-delete-all --refresh

# 5. Cron daily (đã đăng ký "LingQ Sync Daily" 10:00, cron.bat đã update):
schtasks /run /tn "LingQ Sync Daily"
```

### push.php flags

| Flag | Mô tả |
|---|---|
| (default) | Dry-run — in plan, không gọi API. |
| `--apply` | Thật sự gọi API. |
| `--limit=N` | Cap mỗi phase tối đa N hành động. Test 1 card: `--limit=1`. |
| `--skip-create` / `--skip-update` / `--skip-delete` | Bỏ phase tương ứng. |
| `--confirm-delete=N` | Manual mode bắt buộc; phải match exact số planned. |
| `--force-delete-all` | Manual mode override `manual_max_delete_pct` (default 80%). |
| `--auto-confirm` | Cron mode — dùng threshold thấp hơn (20% pct / 50 abs). |
| `--refresh` | Sau apply, exec `sync.php` để snapshot khớp server. |
| `--no-lock` | Bỏ qua `.ai-locks/lingq_push_running.lock` (advanced). |

### Safety thresholds

`config.php` → `push_thresholds`:

| Key | Default | Mục đích |
|---|---|---|
| `manual_max_delete_pct` | 80 | Manual mode: DELETE > 80% → cần `--force-delete-all`. |
| `auto_max_delete_pct` | 20 | Cron mode: DELETE > 20% → abort hẳn. |
| `auto_max_delete_abs` | 50 | Cron mode: DELETE > 50 absolute → abort hẳn (whichever first). |

Threshold check chạy TRƯỚC bất kỳ API call nào.

### Status preservation

`PATCH` payload KHÔNG bao giờ chứa `status` hoặc `extended_status`. Lý do:
- Status = ground truth từ server (user review trên LingQ).
- Target luôn có `status=1` (default) — nhưng đó chỉ dùng cho POST mới.
- Nếu PATCH gửi status → đè progress user đã build → mất history.

Code enforcement: `diff_for_patch()` trong `push.php` chỉ emit `fragment`, `hint`, `tags`.

### Backup before destructive

Trước phase DELETE đầu tiên trong mỗi run, copy `data/lingq_cards.csv` → `data/lingq_cards_backup_<YYYY-MM-DD>_<HHMMSS>.csv`. KHÔNG xoá manual — retention không thuộc scope hiện tại.

### Lock file

`.ai-locks/lingq_push_running.lock` được tạo khi `push.php` start, xoá khi exit. Nếu file tồn tại và age < 30 phút → exit với "Another push in progress". Stale lock (> 30 phút) tự overwrite.

### Acceptance tests Phase D

1. `update_local.php` → `data/lingq_target.csv` có 57 rows, 11 cột, tags format `wortart:X;level:B1;thema:Y;voc:VOC-...`.
2. `push.php` (dry-run) → in plan CREATE/UPDATE/DELETE + warnings nếu hit threshold.
3. `push.php --apply --limit=1 --skip-delete --skip-update` → POST 1 card, verify trên LingQ UI.
4. `push.php --apply --confirm-delete=N --force-delete-all` → wipe + push full.
5. Run lại sau full push → CREATE=0, UPDATE=0, DELETE=0 (idempotent).
6. Add 1 row vào vocab_master → `update_local.php` → `push.php` plan CREATE=1.
7. Bump status từ 1→3 trên LingQ UI, chạy cron orchestrator → status server giữ 3 sau push.
8. Vocab_master còn 5 rows + `push.php --apply --auto-confirm` → abort vì threshold cron mode.

---

## Phase F — hints format (plural array)

LingQ API v2 dùng **plural** `hints` field, không phải `hint` singular:

```json
{
  "term": "anpassen",
  "hints": [
    {"text": "thích nghi (reflexiv)", "locale": "vi"}
  ],
  "fragment": "..."
}
```

- POST/PATCH payload: gửi `hints = [{text, locale}, ...]`. Empty hint → `hints = []` (cho phép clear).
- Sync GET response: parse `card.hints[]`, filter `locale = cfg['hint_locale']`, lấy `text` đầu tiên.
- **CSV `hint` giữ plain string** (1 cột, mono-locale). Khi nhiều hint cùng locale → log WARN, dùng cái đầu tiên.
- Locale lấy từ `config.php` key `hint_locale` (default `vi`).

Trước Phase F dùng `hint` singular string trong cả POST và PATCH → API ignore → 53/57 entries trên LingQ UI không hiển thị nghĩa VI dù snapshot CSV có chữ. Phase F sửa cả 4 chỗ: `sync.php` parse + `push.php` POST/PATCH builders + `lingq_client.php` 2 static helpers (`buildHintsArray`, `pickHintText`).

---

## Phase J — enriched notes sync

Phase J extend Phase D bằng cách render **enriched notes** join từ 4 nguồn deutsch:
`vocab_master.notes` (Grammar) + `chunks_master.csv` (Collocations) + `weak_words.csv` (Weak word) +
`docs/ai/MISTAKES_LOG.md` (Past mistakes). Output đẩy lên LingQ `card.notes` field.

### Schema bump

CSV `lingq_cards.csv` + `lingq_target.csv` bump 11 → 12 cột (thêm `notes` cuối).
`sync.php` tự detect v1 lần đầu, log WARN `Header mismatch v1(11) → v2(12), rebuilding from server`,
preserve `first_seen` qua partial-load.

### Source join

- **Grammar / Notes** ← `vocab_master.notes` (cột 14) cho từ đang xét.
- **Collocations** ← chunks_master rows có `chunk_de` hoặc `note` chứa wort (stripos lemma).
  Top `notes_max_collocations` (default 5).
- **Weak word** ← weak_words.csv row có `wort` khớp exact. Render `mistake_count: N — last: YYYY-MM-DD — rule: ...`.
- **Past mistakes** ← MISTAKES_LOG.md entries có text chứa wort hoặc vocab_id. Top `notes_max_mistakes` (default 5).
- **Cross-ref** ← regex `VOC-\d{8}-\d{3}` trong grammar + collocations content; self-id excluded.

### Idempotency marker

Render mở đầu bằng marker `[AI-sync YYYY-MM-DD | VOC-...]`.
Regex idempotency: `/\[AI-sync \d{4}-\d{2}-\d{2} \| VOC-[\w-]+\]/`.

### Merge cases khi PATCH

| Server notes | Action |
|---|---|
| Empty | PUT target as-is. |
| Có marker AI cũ | Tách user_part (mọi text trước marker — preserve nguyên trạng kể cả `\n---\n`), ghép `user_part + new_target_block`. |
| Có user text không marker | Append `serverNotes + "\n---\n" + target`. |
| Target = '' (zero source) + server có marker | Strip marker block + trailing `\n---\n` separator → giữ user text. |

Sau merge, nếu `final == serverNotes` → no PATCH (idempotent). Mặc định round-trip 2 lần → 0 changes.

### Limit verify (2026-05-19)

Curl probe pk=659048133: PATCH lên 250/1000/5000/20000/**100000** chars — server lưu **EXACT, no truncation observed**.
→ `notes_max_chars` default = 50000 (rộng rãi, không bao giờ hit trong thực tế).

### Phase J flag mới

| Flag | Mô tả |
|---|---|
| `--force-overwrite-notes` | Bypass merge — đè user_text trong notes (nguy hiểm). Bắt buộc STDIN confirm gõ `OVERWRITE-NOTES`. Không tương thích `--auto-confirm`. |

### Phase J config keys (`config.example.php`)

| Key | Default | Mục đích |
|---|---|---|
| `notes_prefix` | `[AI-sync %DATE% | %ID%]` | Marker template. `%DATE%` `%ID%` được thay runtime. |
| `notes_max_chars` | 50000 | Cắt cuối + `(truncated at N chars)` marker. |
| `notes_max_collocations` | 5 | Top N chunks per row. |
| `notes_max_mistakes` | 5 | Top N MISTAKES_LOG entries per row. |
| `notes_enrichment` | `true` | `false` → fallback: chỉ `[AI-sync ...] + vocab_master.notes plain` (no joins). |
| `notes_strict_chunk_match` | `false` | `true` → word-boundary regex (tránh `Mut`→`Mutter`). Default stripos cho linh hoạt flexion (`Schüler`→`Schülern`). |

### Acceptance tests Phase J

Đã verify programmatic (42/42 unit pass):

1. ✅ Schüler render đầy đủ 5 section với fixtures (chunks + weak + mistakes + cross-ref).
2. ✅ Server có user text không marker → Case D append `\n---\n` + target.
3. ✅ Server có marker cũ → Case C replace; user text trước marker preserved.
4. ✅ Idempotent: server = canonical merged output → re-merge return server (no PATCH).
5. ✅ Target empty (zero source) + server có marker → strip block, giữ user text.
6. ✅ Partial source: chỉ Grammar section → KHÔNG render heading rỗng cho 4 section còn lại.
7-8. ✅ Add mistake/chunk → next render include (verified qua T1 fixture).
9. ✅ Truncate tại `notes_max_chars` với marker `(truncated at N chars)`.
10. ✅ Schema bump 11→12 lần đầu: `sync.php` WARN + rebuild preserve `first_seen` (qua partial-load v1).
11. ✅ Phase D regression x8: `fragment-changed=0, hint-changed=0, tags-changed=0` trong plan dry-run sau khi target chỉ thay đổi notes.
12. ⏳ POST CREATE với notes: dry-run plan CREATE=3 với target.notes; verify live qua user manual `--apply --limit=1`.
13. ✅ `notes_enrichment=false` fallback: marker + plain `vocab_master.notes`.
14. ✅ `--force-overwrite-notes` + STDIN: nếu confirm string ≠ `OVERWRITE-NOTES` → abort exit 1.
15. ✅ Round-trip lossy: `update_local.php` chạy 2 lần liên tiếp → `lingq_target.csv` identical (diff empty).

---

## Phase K — Lessons CRUD

Phase C/D/J chỉ xử lý **CARDS** (vocab từ rời). Phase K thêm CRUD cho **LESSONS** —
học từ trong ngữ cảnh (đoạn văn highlight vàng) thay vì từ rời. CSV riêng `data/lingq_lessons.csv`.

Chia 3 stage commit độc lập: **K1** list+delete · **K2** push text (Lesen) · **K3** push audio (Hören).

### Endpoint LingQ (verified 2026-05-24, token thật + OPTIONS)

| Thao tác | Method + URL | Ghi chú |
|---|---|---|
| LIST my lessons | `GET /api/v3/{lang}/search/?shelf=my_lessons` | Paginate qua `next` (KHÔNG có `count`). v3, khác base_url v2 → `LingqClient::v3Base()`. |
| GET 1 lesson | `GET /api/v2/{lang}/lessons/{id}/` | Dùng cho delete preview fallback. |
| DELETE | `DELETE /api/v2/{lang}/lessons/{id}/` | OPTIONS allow `DELETE`. 404 = already gone. |
| CREATE | `POST /api/v2/{lang}/lessons/` | OPTIONS allow `POST`. Fields: `title,text,status,level,collection,tags,external_audio,duration,audio`(file). K2/K3. |

### K1 — list + delete

```cmd
REM Sync toàn bộ lessons của user → data/lingq_lessons.csv (atomic, idempotent)
C:\php\php74\php.exe module\lingq_sync\lessons_sync.php
C:\php\php74\php.exe module\lingq_sync\lessons_sync.php --dry-run

REM Xoá bài (DRY-RUN mặc định — chỉ in preview; cần --apply để xoá thật)
C:\php\php74\php.exe module\lingq_sync\lessons_delete.php <id1> <id2> ...
C:\php\php74\php.exe module\lingq_sync\lessons_delete.php <id1> --apply
C:\php\php74\php.exe module\lingq_sync\lessons_delete.php <id1> --apply --no-resync
```

| File | Mô tả |
|---|---|
| `lessons_sync.php` | Paginated fetch `shelf=my_lessons` → `data/lingq_lessons.csv`. Idempotent: giữ `first_seen` + `source_local` + `words_count` cũ, bump `last_synced`. Removed (có trong CSV, mất trên web) → KHÔNG drop, chỉ đếm. |
| `lessons_delete.php` | DRY-RUN default in preview (title/audio/unknown/course). `--apply`: snapshot backup `data/lingq_lessons_backup_<ts>.csv` → DELETE từng id (404 = already gone) → re-sync CSV (trừ `--no-resync`). |

CSV `data/lingq_lessons.csv` (UTF-8 BOM, 10 cột, sort `lesson_id` asc):

```
lesson_id,course_id,title,language,audio_url,words_count,unknown_count,source_local,first_seen,last_synced
```

- `words_count`: **để trống** — endpoint `search?shelf=my_lessons` chỉ trả `newWordsCount` (→ `unknown_count`), không trả tổng word count. Per-lesson GET để lấy sẽ phá ngân sách 30s với 455 bài → cố ý bỏ. Chỉ set nếu push (K2/K3) ghi.
- `source_local`: chỉ set khi push từ folder local (K2/K3). Sync preserve theo `lesson_id`. Dùng cho idempotency check + update khi text gốc đổi.
- Log: `logs/lessons_YYYY-MM-DD.log`.

Acceptance K1 (verified 2026-05-24): sync 455 bài/3 trang/7.4s; idempotent run #2 = New 0/Updated 0/Unchanged 455; delete dry-run preview OK (in-CSV + fallback getLesson cho id lạ → 404).

---

## Tham chiếu

- Spec đầy đủ Phase C: `docs/ai/tasks/LINGQ_SYNC_PROMPT.md`
- Spec đầy đủ Phase D: `docs/ai/tasks/LINGQ_PUSH_PROMPT.md`
- Spec đầy đủ Phase F: `docs/ai/tasks/LINGQ_PHASE_F_PROMPT.md`
- Spec đầy đủ Phase J: `docs/ai/tasks/LINGQ_NOTES_SYNC_PROMPT.md`
- Pipeline router: `docs/ai/PIPELINE.md`
- Data contract: `data/README.md`
