# LingQ Integration — module `lingq_sync/`

> **TL;DR:** `vocab_master.csv` (curate thủ công) là **source of truth**. LingQ chỉ là UI học (web + mobile SRS). Pipeline 2-chiều: pull status từ server, push field khác từ local. Cron Windows daily 10:00 chạy 4-step orchestrator.

---

## 1. Kiến trúc 3-file local

```
vocab_master.csv (source of truth)
    │
    │ update_local.php (no API, regen target)
    ▼
lingq_target.csv (desired state)
    │
    │ push.php (diff target vs cards → POST/PATCH/DELETE)
    ▼
LingQ server (mobile UI)
    │
    │ sync.php (pull all cards from server)
    ▼
lingq_cards.csv (server snapshot, with status updated)
```

| File | Vai trò | Schema |
|---|---|---|
| `data/03_unified/vocab_master.csv` | Source of truth (user curate per tutor session) | 14 cột (xem `data/README.md`) |
| `data/lingq_target.csv` | Desired state (sinh tự động) | **12 cột** match `lingq_cards.csv` (Phase J thêm `notes`) |
| `data/lingq_cards.csv` | Server snapshot (pull-only) | **12 cột** (v2 — auto upgrade từ v1 lần sync đầu) |

## 2. Diff logic (push.php)

| Tình huống | Action |
|---|---|
| `term` trong target, không trong snapshot | POST (status=1 fresh, kèm `notes` nếu target có) |
| `term` cả 2, field khác (fragment/hint/tags/**notes**) khác | PATCH (đè field từ target, **giữ status từ snapshot**) |
| `term` trong snapshot, không trong target | DELETE |

→ Quy tắc vàng: **status luôn từ snapshot, field khác luôn từ target**.

**Phase J — notes merge (idempotent):** `diff_for_patch()` gọi `merge_notes_for_patch(target, server_notes)`
trước khi compare. Cases:

| Server `notes` | Output |
|---|---|
| Empty | target as-is |
| Có marker `[AI-sync ... \| VOC-...]` | user_part trước marker (verbatim) + new_target_block |
| Có user text không marker | `server + "\n---\n" + target` (Case D append) |
| Target = '' + server có marker | strip marker block + trailing separator → giữ user text |

Sau merge: nếu `final == server_notes` → no PATCH (idempotent). `--force-overwrite-notes` bypass merge.

## 3. Workflow daily (tự động)

Windows Task Scheduler `LingQ Sync Daily` → `module/lingq_sync/cron.bat` chạy 4-step:

```
10:00:00  sync.php          (pull snapshot, lấy status user vừa review)
10:00:30  update_local.php  (regen target từ vocab_master)
10:00:31  push.php --apply --auto-confirm  (diff + POST/PATCH/DELETE)
10:05:00  sync.php          (post-push refresh)
```

Mỗi step abort nếu errorlevel != 0. Log: `module/lingq_sync/logs/cron_<date>.log`.

## 4. Workflow manual

**Sau khi update vocab_master.csv (sau tutor session):**

```bat
REM Chạy ngay không chờ 10h sáng mai:
module\lingq_sync\cron.bat
```

→ 1 lệnh, chạy nguyên 4 step.

**Hoặc trigger Windows Task:**
```bat
schtasks /run /tn "LingQ Sync Daily"
```

## 5. Cấu hình `config.php`

| Key | Giá trị | Ghi chú |
|---|---|---|
| `api_key` | Token từ lingq.com/accounts/apikey/ | Trong `.gitignore` |
| `language` | `de` | LingQ language code |
| `page_size` | 200 | Max LingQ cho phép |
| `sleep_ms` | 1500 | Rate-limit guard (3x từ default ban đầu) |
| `retry` | 5 | Retry trên 5xx + network error |
| `retry_429_backoff` | `[5,15,30,60,120]` | Wait seconds cho mỗi attempt khi gặp 429 |
| `hint_locale` | `vi` | LingQ hints array filter locale |
| `push_thresholds.manual_max_delete_pct` | 80 | Manual cần `--force-delete-all` nếu vượt |
| `push_thresholds.auto_max_delete_pct` | 20 | Cron abort nếu vượt (chống wipe accidental) |

## 6. Safety guards (push.php)

- **Dry-run mặc định** — không có `--apply` thì chỉ in plan, không gọi API write.
- **`--confirm-delete=N`** (manual mode): gõ số chính xác bằng plan để xác nhận.
- **`--force-delete-all`** (manual mode): bypass threshold 80%.
- **`--auto-confirm`** (cron mode): skip interactive prompt, NHƯNG abort nếu DELETE > 20% snapshot.
- **`--limit=N`**: chỉ apply N entries per phase (CREATE/UPDATE/DELETE). Test trước mass.
- **`--skip-delete`**: bỏ qua phase DELETE (chỉ POST + PATCH).
- **Backup tự động:** `data/lingq_cards_backup_<timestamp>.csv` trước mỗi mass DELETE.
- **Lock running:** `.ai-locks/lingq_push_running.lock` chống chạy đè.

## 7. Quirk API LingQ v2 (đã verify thực tế)

1. **Field hint phải là `hints` (plural, array of object)**, KHÔNG phải `hint` (singular, string).
   ```json
   "hints": [{"text": "thích nghi", "locale": "vi"}]
   ```
   Plain string sẽ bị server ignore → lưu empty trên UI.
2. **Rate limit HTTP 429** xuất hiện ở `sleep_ms=500` với bulk DELETE/PATCH. `sleep_ms=1500` ổn.
3. **Status 4 + extended_status 0 = thành thạo**, `extended_status=3` = "known" (loại khỏi LingQ count).
4. **DELETE 404 = đã bị xoá rồi** — KHÔNG throw, log WARN và skip.
5. **POST `fragment` plain string OK** (không cần gắn `lesson_id` như community forum lo).

## 8. PHP 7.4 Windows setup (1-time)

`C:\php\php74\php.ini` cần bật:

```ini
extension_dir = "ext"
extension=curl
extension=openssl
extension=mbstring
extension=fileinfo
extension=mysqli
curl.cainfo = "C:\php\php74\extras\ssl\cacert.pem"
openssl.cafile = "C:\php\php74\extras\ssl\cacert.pem"
```

`cacert.pem` (CA bundle Mozilla, ~190KB) tải tại https://curl.se/ca/cacert.pem → lưu vào `extras\ssl\`.

## 9. Troubleshoot nhanh

| Triệu chứng | Fix |
|---|---|
| `HTTP 401 Unauthorized` | Token đổi/expired → regenerate tại lingq.com/accounts/apikey/, update `config.php` line 7 |
| `HTTP 429 Too Many Requests` (sau khi đã fix) | Đợi 10-15 phút, code tự retry với backoff. Nếu liên tục → tăng `sleep_ms` lên 2000-3000 |
| `cURL errno=60 SSL certificate problem` | Thiếu `cacert.pem`, xem mục 8 |
| `FATAL: PHP cURL extension not loaded` | `php.ini` thiếu `extension=curl`, xem mục 8 |
| `Plan show DELETE > 0` mà server đã clean | Snapshot stale → chạy `sync.php` refresh trước |
| `UI không hiện MEANING (VI)` | Code chưa dùng `hints` array format → Phase F đã fix |
| `Another push in progress (lock age=XXs)` | Process push trước Ctrl+C không xoá lock → `del .ai-locks\lingq_push_running.lock` |
| CSV lingq_cards.csv tích lũy zombie rows | Phase E chưa apply (xem `docs/ai/tasks/LINGQ_PHASE_E_PROMPT.md`) |

## 10. Backup retention

Hiện tại push.php tự backup `lingq_cards_backup_<timestamp>.csv` trước mỗi mass DELETE — **không có retention policy**, tích lũy theo thời gian.

**Manual cleanup khi > 30 ngày:**
```bat
forfiles /P data /M lingq_cards_backup_*.csv /D -30 /C "cmd /c del @path"
```

→ Đề xuất phase G: auto cleanup trong push.php (chưa làm).

## 11. Backlog (phase tiếp theo, không gấp)

| Phase | Mô tả | Spec file |
|---|---|---|
| E | sync.php tự xoá zombie row khỏi CSV | `docs/ai/tasks/LINGQ_PHASE_E_PROMPT.md` |
| G | Backup retention auto (giữ 30 ngày) | TBD |
| H | Health check cuối cron.bat (1-line summary) | TBD |
| I | Stale lock guard đầu cron.bat (age > 60min → log WARN) | TBD |
| J | ✅ **DONE 2026-05-19** — Push enriched notes 4 nguồn (vocab.notes + chunks + weak_words + MISTAKES_LOG) → LingQ card.notes. Schema 11→12 cột, marker `[AI-sync ... \| VOC-...]`, idempotent merge, cron unchanged. | `docs/ai/tasks/LINGQ_NOTES_SYNC_PROMPT.md` |

## 13. Phase J — notes endpoint + limit verify (2026-05-19)

Verify thực hiện qua probe PHP one-shot trên pk=659048133 (`einsetzen`), restore notes='' sau.

### Endpoint shape

```
GET /api/v2/de/cards/{pk}/         → HTTP 200, JSON có key `notes` (type string).
GET /api/v2/de/cards/?page=N       → HTTP 200, results[].notes present (KHÔNG bị omit khỏi list view).
PATCH /api/v2/de/cards/{pk}/ + {notes: "..."} → HTTP 200, GET back lưu chính xác.
```

→ Không cần fan-out single GET sau list — Phase J sync.php parse `card.notes` ngay từ list response.

### Limit test (5 mức)

| Requested chars | PATCH HTTP | GET back len | Verdict |
|---|---|---|---|
| 250 | 200 | 250 | EXACT |
| 1,000 | 200 | 1,000 | EXACT |
| 5,000 | 200 | 5,000 | EXACT |
| 20,000 | 200 | 20,000 | EXACT |
| 100,000 | 200 | 100,000 | EXACT |

→ **Server lưu chính xác ít nhất 100,000 chars; không quan sát thấy truncation hoặc HTTP 4xx.**
→ Config `notes_max_chars = 50000` (conservative, sẽ không bao giờ hit trong thực tế).
→ Truncate policy (`truncate_to_max()`) vẫn implement đầy đủ — bị trigger khi user set config thấp.

### Phase J config keys (`config.php`)

| Key | Default | Note |
|---|---|---|
| `notes_prefix` | `[AI-sync %DATE% \| %ID%]` | `%DATE%` `%ID%` replaced runtime. |
| `notes_max_chars` | 50000 | Truncate cuối với marker `(truncated at N chars)`. |
| `notes_max_collocations` | 5 | Top N chunks per row. |
| `notes_max_mistakes` | 5 | Top N MISTAKES_LOG entries per row. |
| `notes_enrichment` | `true` | `false` → fallback marker + raw `vocab_master.notes`. |
| `notes_strict_chunk_match` | `false` | `true` → word-boundary regex (tránh `Mut`→`Mutter` false positive). |

---

## 14. Phase K — Lessons CRUD (2026-05-24)

Phase C/D/F/J xử lý **CARDS** (vocab từ rời). Phase K thêm CRUD cho **LESSONS** — học từ
trong ngữ cảnh (đoạn văn highlight vàng). CSV riêng `data/lingq_lessons.csv` (10 cột, BOM,
sort `lesson_id` asc). 3 stage: K1 list+delete · K2 push text (Lesen) · K3 push audio (Hören).

### Quirk API: lessons = **v3** (KHÁC cards v2)

`POST /api/v2/{lang}/lessons/` trả `["API is obsolete. Use v3 instead."]` (list, không phải
lỗi DRF dict). `base_url` config giữ v2 cho CARD; mọi LESSON op derive v3 qua `LingqClient::v3Base()`.

| Op | Endpoint v3 | Verify |
|---|---|---|
| LIST my | `GET /search/?shelf=my_lessons` (paginate `next`, no `count`) | 455 bài/3 trang |
| GET/DELETE | `GET`/`DELETE /lessons/{id}/` | OPTIONS + 404=gone |
| CREATE | `POST /lessons/` (JSON) | live 201 |
| AUDIO | `PATCH /lessons/{id}/` multipart `audio` | live 200, auto `duration` |
| COURSE | `POST /collections/` | live → `pk` |

### Flow

```
Lesen folder  X.X_text.md (frontmatter)
Hören folder  X.X_transcript.md + X.X.mp3
        │  parse → title + plaintext + tags[DTZ,B1,skill,Teil?]
        ▼
   lessons_push.php ──(JSON POST /lessons/)──► LingQ  (lesson_id)
        │                                         │
        │ if mp3: PATCH multipart audio ──────────┤ (audioUrl + duration)
        ▼                                         ▼
   lessons_sync.php ──(GET search my_lessons)──► data/lingq_lessons.csv
        ▲                                         │  upsert source_local + audio_url
        └── idempotency: source_local ◄───────────┘  (skip nếu đã push; --force-update → PATCH)

   lessons_delete.php <id> --apply: backup CSV → DELETE /lessons/{id}/ → re-sync
```

- **Idempotency**: `source_local` (relative folder path) trong CSV. `lessons_sync` preserve
  `source_local` + `first_seen` + `words_count` qua mỗi sync (giống Phase C cho cards).
- **words_count** để trống: search `my_lessons` chỉ trả `newWordsCount` (→ `unknown_count`),
  không trả tổng. Per-lesson GET sẽ phá ngân sách 30s (455 bài) → bỏ.
- **Course**: user tạo 1 collection (vd "DTZ Vorbereitung" PK 2747707) → `lessons_course_id`
  trong config.php. Rỗng → push `--apply` chặn (trừ `--no-course`).
- Config keys: `lessons_course_id`, `lessons_level` (3=B1), `lessons_status` (private),
  `lessons_default_tags` (['DTZ','B1']).

Acceptance live 2026-05-24: Lesen 1.1 → 201 id=44743333; Hören 1.1 → 201 id=44743345 +
audio 35s; idempotent skip; sync 455→457 bài. Chi tiết: `module/lingq_sync/README.md` §Phase K.

---

## 12. Files

```
module/lingq_sync/
├── config.php             gitignored, chứa api_key (+ Phase K: lessons_course_id)
├── config.example.php     template (Phase J + K keys)
├── lingq_client.php       cURL wrapper. CARD: GET/POST/PATCH/DELETE. LESSON (Phase K, v3):
│                          fetchAllLessons/getLesson/createLesson/updateLesson/deleteLesson/uploadLessonAudio
├── sync.php               pull cards → lingq_cards.csv (Phase J: 12 cột, parse card.notes)
├── update_local.php       vocab_master + 3 nguồn → lingq_target.csv (Phase J join)
├── push.php               diff cards → POST/PATCH/DELETE (Phase J: notes merge)
├── notes_builder.php      Phase J utility (build/merge/parse/truncate)
├── lessons_sync.php       Phase K1 — pull my_lessons → lingq_lessons.csv
├── lessons_delete.php     Phase K1 — DRY-RUN preview + --apply DELETE + backup + re-sync
├── lessons_push.php       Phase K2/K3 — push 1 folder (text + audio) → lesson, idempotent
├── cron.bat               4-step orchestrator (cards; unchanged)
├── README.md              module-level doc
└── logs/                  daily log (cards) + lessons_YYYY-MM-DD.log

docs/
├── LINGQ_SYNC.md          quick reference (3 lệnh hay dùng)
└── LINGQ_INTEGRATION.md   file này — kiến trúc + decisions + troubleshoot

docs/ai/tasks/
├── LINGQ_SYNC_PROMPT.md   Phase C spec (pull)
├── LINGQ_PUSH_PROMPT.md   Phase D spec (push 2-way)
├── LINGQ_PHASE_E_PROMPT.md  Phase E spec (zombie cleanup)
├── LINGQ_PHASE_F_PROMPT.md  Phase F spec (hints array format)
├── LINGQ_NOTES_SYNC_PROMPT.md  Phase J spec (enriched notes sync)
└── LINGQ_LESSONS_CRUD_PROMPT.md  Phase K spec (lessons CRUD: list/delete/push text+audio)
```

---

**Last updated:** 2026-05-24 (Phase C + D + F + J + **K** done; E + G + H + I backlog).
