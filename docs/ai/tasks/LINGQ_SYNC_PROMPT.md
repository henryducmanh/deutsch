# LingQ Sync — Phase C v1 (PHP local + cron Windows)

> **Task ID:** `lingq_sync_v1`
> **Người triển khai:** Claude Code (vai Implementer, KHÔNG phải 7 vai domain).
> **Stack:** PHP 7.4 local (`C:\php\php74\php.exe`), no MySQL, no web UI, output là CSV + log file.
> **Lock:** đã tạo `.ai-locks/lingq_sync_prompt.lock` — Claude Code tạo lock riêng `.ai-locks/lingq_sync_impl.lock` cho phase code.

---

## 1. End-user

**Henry** — solo dev, học DTZ B1. Tài khoản LingQ: `ducmanh` (Lifetime Premium German, 2728 LingQs hiện tại). Muốn:

- Pull toàn bộ LingQ status về repo **mỗi ngày 6h sáng** qua Windows Task Scheduler.
- Có CSV shadow `data/lingq_cards.csv` để vai **Vocab Extractor** merge vào `vocab_master.csv` sau (KHÔNG tự động merge).
- Pure PHP CLI script, log file plain text, không cần web UI / MySQL.
- Kiểm soát được: chạy được manual khi cần, có `--dry-run`, có log retry.

---

## 2. Màn cuối cùng (definition of done)

### Files xuất hiện sau khi Claude Code làm xong

```
module/lingq_sync/
├── config.example.php          ← template, user copy thành config.php
├── lingq_client.php             ← cURL client + paginate + retry
├── sync.php                     ← entry point CLI
├── cron.bat                     ← wrapper Windows Task Scheduler gọi
├── .gitignore                   ← ignore config.php + logs/
├── README.md                    ← setup + troubleshooting
└── logs/
    └── .gitkeep
data/
└── lingq_cards.csv              ← chỉ tạo header, populate khi sync lần đầu
```

### UX expected

User chạy `C:\php\php74\php.exe module\lingq_sync\sync.php` lần đầu thì terminal in:

```
[LingQ Sync] 2026-05-18 14:23:01
Config: module/lingq_sync/config.php
Language: de | Page size: 200

Fetching page 1...   200 cards (HTTP 200, 412ms)
Fetching page 2...   200 cards (HTTP 200, 388ms)
...
Fetching page 14...  128 cards (HTTP 200, 401ms)

Total fetched: 2728
Diff vs data/lingq_cards.csv:
  New:       2728
  Updated:   0
  Unchanged: 0
  Removed:   0  (LingQ xoá khỏi account)

Wrote: data/lingq_cards.csv (2728 rows)
Log:   module/lingq_sync/logs/2026-05-18.log
Done in 8.4s. Exit 0.
```

Chạy lần 2 cùng ngày → `New: 0, Updated: 0, Unchanged: 2728`.

Sau khi user upgrade 1 LingQ trên web từ status 2→3 và chạy lại → `Updated: 1`, csv row đó `status` đổi sang `3` và `last_synced` cập nhật, nhưng `first_seen` giữ nguyên.

---

## 3. Ví dụ dữ liệu thật

### LingQ API response (paginated)

```
GET https://www.lingq.com/api/v2/de/cards/?page=1&page_size=200
Authorization: Token <API_KEY>
```

```json
{
  "count": 2728,
  "next": "https://www.lingq.com/api/v2/de/cards/?page=2&page_size=200",
  "previous": null,
  "results": [
    {
      "pk": 12345678,
      "term": "entwicklung",
      "fragment": "Die technologische Entwicklung verändert die Welt.",
      "hint": "development; growth",
      "status": 2,
      "extended_status": 0,
      "tags": ["technology", "B1"],
      "importance": 0,
      "last_studied_correct": "2026-05-15T08:23:00Z"
    }
  ]
}
```

### Row trong `data/lingq_cards.csv` (sau sync)

```csv
lingq_id,term,fragment,hint,status,extended_status,tags,importance,last_studied_correct,first_seen,last_synced
12345678,entwicklung,"Die technologische Entwicklung verändert die Welt.","development; growth",2,0,"technology;B1",0,2026-05-15T08:23:00Z,2026-05-18,2026-05-18
```

### Status mapping (đã chốt, KHÔNG tự đổi)

| LingQ `status` | `extended_status` | German Brain meaning |
|---|---|---|
| 1 | 0 | mới (new) |
| 2 | 0 | nhận biết (recognizing) |
| 3 | 0 | quen (familiar) |
| 4 | 0 | thành thạo (learned, vẫn nằm trong vocab) |
| 4 | 3 | known (loại khỏi LingQ count) |

### Config file format (`config.example.php`)

```php
<?php
// LingQ API config. Copy thành config.php và điền API key.
// API key lấy tại: https://www.lingq.com/accounts/apikey/

return [
    'api_key'   => 'PASTE_YOUR_LINGQ_TOKEN_HERE',
    'language'  => 'de',
    'base_url'  => 'https://www.lingq.com/api/v2',
    'page_size' => 200,
    'timeout'   => 30,
    'retry'     => 3,
    'sleep_ms'  => 500,
];
```

---

## 4. Acceptance tests (manual, user chạy tuần tự)

1. **Bootstrap config:**
   `copy module\lingq_sync\config.example.php module\lingq_sync\config.php`
   → mở file, paste token thật vào.

2. **Dry run (không cần API key đúng để pass test phase này nếu code đúng):**
   `C:\php\php74\php.exe module\lingq_sync\sync.php --dry-run`
   → in plan, không write `data/lingq_cards.csv`, exit 0.

3. **Live sync lần 1:**
   `C:\php\php74\php.exe module\lingq_sync\sync.php`
   → exit 0, `data/lingq_cards.csv` có ≥ 2728 rows, log file `logs/2026-05-18.log` tồn tại.

4. **Idempotent:**
   Chạy lại ngay → `New: 0, Updated: 0`.

5. **Update detection:**
   Đổi status 1 LingQ trên `lingq.com` (UI) → chạy lại → `Updated: 1`, row đó `status` đổi, `first_seen` giữ nguyên.

6. **Error handling — wrong token:**
   Sửa `api_key` thành sai → chạy → exit 1, log có dòng `HTTP 401 Unauthorized`, csv hiện có KHÔNG bị xoá / corrupt.

7. **Error handling — timeout/5xx:**
   Tạm thời chỉnh `base_url` thành `https://httpstat.us/503` (mock) → exit 1 sau 3 retry với delay 1s/3s/9s.

8. **Cron registration:**
   ```
   schtasks /create /tn "LingQ Sync Daily" ^
     /tr "C:\twv_share\app\deutsch\module\lingq_sync\cron.bat" ^
     /sc daily /st 06:00
   ```
   → next 6:00 AM xuất hiện file `logs/cron_2026-05-19.log`.

---

## 5. Cấm đụng

- ❌ `data/03_unified/vocab_master.csv` — vai Vocab Extractor merge sau, KHÔNG touch ở đây.
- ❌ `data/01_ai_extracted/` — archive raw ChatGPT, freeze.
- ❌ `data/chunks_master.csv`, `data/weak_words.csv`, `data/sources_master.csv` — không phải scope phase này.
- ❌ Hard-code API key — phải đọc từ `config.php`, file đó nằm trong `.gitignore`.
- ❌ `git add / commit / push` — Edit xong báo "edit xong, chờ review Cursor".
- ❌ SQL / DDL — không có DB.
- ❌ Composer / vendor — pure PHP stdlib (cURL có sẵn PHP 7.4).
- ❌ Bịa fallback khi API field missing — log WARNING dòng đó, skip row, vẫn ghi rows OK.
- ❌ `php -l` local để verify syntax (theo CLAUDE.md user — treo).
- ❌ Mở rộng scope: không build Anki export ở đây, không touch `vocab_master.csv`, không push 2-chiều về LingQ.

---

## 6. Performance / scale

- **Hiện tại:** 2728 LingQs → ~14 request (page_size 200) → ~10s tổng.
- **Tương lai (B2/C1):** dự kiến 10k-30k → ≤ 5 phút.
- **Rate limit guard:** `sleep_ms=500` giữa các request.
- **Retry policy:** 3 lần, backoff 1s → 3s → 9s, chỉ retry trên `5xx` hoặc timeout. `4xx` fail nhanh.
- **Idempotency key:** `lingq_id` (= `pk` trong API response).
- **Update logic per row:**
  - Match theo `lingq_id` → nếu đã có: update tất cả field TRỪ `first_seen`, set `last_synced=today`.
  - Nếu chưa có: append row mới, `first_seen=today, last_synced=today`.
  - Nếu LingQ tồn tại trong csv nhưng KHÔNG có trong API response (user delete trên web): KHÔNG xoá row, chỉ log `Removed: N` để user review thủ công sau.
- **CSV write:** atomic — write `data/lingq_cards.csv.tmp`, fsync, rename → tránh corrupt nếu kill giữa chừng.

---

## 7. Format report (Claude Code in ra cuối session)

```
✅ LingQ Sync module v1 — done

Files created:
- module/lingq_sync/config.example.php       (XX dòng)
- module/lingq_sync/lingq_client.php          (XX dòng)
- module/lingq_sync/sync.php                  (XX dòng)
- module/lingq_sync/cron.bat                  (XX dòng)
- module/lingq_sync/.gitignore                (3 dòng)
- module/lingq_sync/README.md                 (XX dòng)
- module/lingq_sync/logs/.gitkeep             (0 dòng)
- data/lingq_cards.csv                        (header only, 1 dòng)

Verified:
- [x] config.php trong .gitignore
- [x] cURL paginate logic readable, idempotency theo lingq_id
- [x] dry-run path không write csv
- [x] atomic CSV write (write .tmp → rename)
- [x] cron.bat dùng absolute path C:\php\php74\php.exe
- [ ] live API call — cần user paste token và chạy thật

To activate (cho Henry chạy):
1) Lấy API token: https://www.lingq.com/accounts/apikey/
2) copy module\lingq_sync\config.example.php module\lingq_sync\config.php
3) Mở config.php, dán token vào dòng 'api_key'
4) Test:  C:\php\php74\php.exe module\lingq_sync\sync.php --dry-run
5) Live:  C:\php\php74\php.exe module\lingq_sync\sync.php
6) Cron:  schtasks /create /tn "LingQ Sync Daily" /tr "C:\twv_share\app\deutsch\module\lingq_sync\cron.bat" /sc daily /st 06:00

Lock cleared: .ai-locks/lingq_sync_impl.lock removed.
Pending: Cursor diff review.
```

---

## Phụ lục — note kỹ thuật

- **PHP version mặc định:** `C:\php\php74\php.exe`. Không dùng php56 (cURL options cũ) hay php82 (deprecation warnings có thể spam log).
- **cURL options bắt buộc:**
  - `CURLOPT_RETURNTRANSFER => true`
  - `CURLOPT_TIMEOUT => 30`
  - `CURLOPT_HTTPHEADER => ["Authorization: Token {$key}", "Accept: application/json"]`
  - `CURLOPT_USERAGENT => "lingq-sync-deutsch/1.0"`
- **CSV encoding:** UTF-8 with BOM (Windows Excel friendly). Quote field nếu chứa `,` hoặc `"` hoặc newline. Newline escape thành `\\n` trong fragment.
- **Log format:** `[YYYY-MM-DD HH:MM:SS] LEVEL message`. Levels: INFO, WARN, ERROR. Mỗi run append vào `logs/<date>.log`.
- **cron.bat nội dung mẫu:**
  ```bat
  @echo off
  cd /d C:\twv_share\app\deutsch
  C:\php\php74\php.exe module\lingq_sync\sync.php >> module\lingq_sync\logs\cron_%date:~-4%-%date:~3,2%-%date:~0,2%.log 2>&1
  ```
- **Nếu cURL không available:** fallback `file_get_contents` với stream_context — nhưng PHP 7.4 standard Windows có sẵn cURL extension, kiểm tra `extension_loaded('curl')` đầu file, exit 1 nếu thiếu.
- **README.md cần có:** mô tả module, quick start 5 dòng, troubleshooting cho 401/403/timeout/CSV corrupt, link tới file này.
