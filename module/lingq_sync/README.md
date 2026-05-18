# LingQ Sync — Phase C v1

Daily pull của LingQ status về repo qua PHP CLI, không MySQL, không web UI.
Output: `data/lingq_cards.csv` (shadow store) — vai **Vocab Extractor** merge sang `vocab_master.csv` sau (KHÔNG tự động merge).

Spec gốc: `docs/ai/tasks/LINGQ_SYNC_PROMPT.md`.

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

| File | Mô tả |
|---|---|
| `config.example.php` | Template config. Copy thành `config.php` (gitignored). |
| `lingq_client.php` | cURL client + paginate + retry (5xx backoff 1s/3s/9s, 4xx fail nhanh). |
| `sync.php` | Entry point CLI. Hỗ trợ `--dry-run`. Diff vs CSV hiện tại theo `lingq_id`. Atomic write. |
| `cron.bat` | Wrapper cho Windows Task Scheduler, gọi `php74\php.exe` với absolute path. |
| `logs/` | Append log `YYYY-MM-DD.log` mỗi run; cron log `cron_YYYY-MM-DD.log`. |
| `../../data/lingq_cards.csv` | Shadow CSV — UTF-8 BOM, 11 cột. |

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

## Tham chiếu

- Spec đầy đủ: `docs/ai/tasks/LINGQ_SYNC_PROMPT.md`
- Pipeline router: `docs/ai/PIPELINE.md`
- Data contract: `data/DATA_CONTRACT.md`
