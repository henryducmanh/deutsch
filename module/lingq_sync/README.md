# LingQ Sync — Phase C + Phase D v1

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
| `update_local.php` | D | Đọc `vocab_master.csv` → render `data/lingq_target.csv`. Không gọi API. |
| `push.php` | D | Diff target vs snapshot → POST/PATCH/DELETE. Default = dry-run; cần `--apply`. |
| `cron.bat` | D | Orchestrator 4 step daily 10:00 (sync → update_local → push --auto-confirm → sync). |
| `logs/` | C+D | `YYYY-MM-DD.log` per-run; `cron_YYYY-MM-DD.log` orchestrator. |
| `../../data/lingq_cards.csv` | C | Shadow snapshot — UTF-8 BOM, 11 cột. |
| `../../data/lingq_target.csv` | D | Desired state — UTF-8 BOM, 11 cột match snapshot schema. |
| `../../data/lingq_cards_backup_*.csv` | D | Snapshot backup trước mỗi DELETE phase. KHÔNG xoá manual. |

---

## CSV schema

```
lingq_id,term,fragment,hint,status,extended_status,tags,importance,last_studied_correct,first_seen,last_synced
```

- `tags` join bằng `;` (không phải `,`) để không vỡ CSV.
- `fragment` escape `\n` thành literal `\n` (mỗi row 1 dòng).
- `first_seen` set lần đầu — KHÔNG đổi khi update.
- `last_synced` cập nhật mỗi sync run.
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

## Tham chiếu

- Spec đầy đủ Phase C: `docs/ai/tasks/LINGQ_SYNC_PROMPT.md`
- Spec đầy đủ Phase D: `docs/ai/tasks/LINGQ_PUSH_PROMPT.md`
- Spec đầy đủ Phase F: `docs/ai/tasks/LINGQ_PHASE_F_PROMPT.md`
- Pipeline router: `docs/ai/PIPELINE.md`
- Data contract: `data/README.md`
