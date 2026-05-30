# Deutsch Web Sync — Phase 3 (`pull_events.php` CLI máy Cowork)

> **Task ID:** `deutsch_web_sync_phase3`
> **Người triển khai:** Claude Code (Implementer).
> **Stack:** PHP 7.4 local (`C:\php\php74\php.exe`), cURL, no DB, output = staging file + log. Chạy trên **máy Cowork** (có repo `deutsch` local), cron Windows 30'.
> **Tiền đề:** web Phase 1 đã live (`deutsch.twv.app`), API `/api/events`, `/api/unknown_words/pending`, `/api/events/ack` đã chạy + có `api_key` Bearer.
> **Lock:** tạo `.ai-locks/deutsch_web_sync.lock` đầu task, xóa cuối.
>
> **Paste cho Claude Code:** `Đọc docs/ai/tasks/DEUTSCH_WEB_SYNC_PHASE3_PROMPT.md và làm theo.`

---

## Goal

Script CLI **pull** event học tập từ `deutsch.twv.app` về máy Cowork → **stage** (không tự merge vào não dữ liệu) → auto-append progress → **ack**. Đây là cầu một chiều Web → local. Mục đích: từ user đánh dấu trên mobile xuất hiện ở repo local để Cowork (vai Vocab Extractor / Mistake Auditor) curate sau.

**Ranh giới (quan trọng):**
- ✅ Tự ghi `output/drills/horen_progress.csv` (progress = cơ học, thay ghi tay — brief 6.3).
- ✅ Stage từ `word_mark` vào `staging/pending_words.csv` để Cowork curate.
- ❌ **KHÔNG** tự append `data/03_unified/vocab_master.csv` (curated, append-only, AI quyết — brief 6.4 #2).
- ❌ **KHÔNG** tự append `data/weak_words.csv` (vai Mistake Auditor quyết wortart/artikel/rule).
- ❌ **KHÔNG** gọi `lingq_sync` ở đây (đó là bước Cowork sau khi curate — Phase 4).

## Files xuất hiện sau khi xong

```
module/deutsch_web_sync/
├── config.example.php          ← template (api_base, api_key, paths)
├── config.php                  ← user copy + điền (gitignored)
├── pull_events.php             ← entry CLI (--dry-run, --since=ISO override)
├── cron.bat                    ← wrapper Task Scheduler
├── .gitignore                  ← config.php, logs/, state/, staging/
├── README.md                   ← setup + workflow Cowork curate + troubleshooting
├── state/
│   └── last_sync.json          ← {"last_sync":"ISO8601"} (tạo runtime)
├── staging/
│   ├── events_<YYYYMMDD-HHMMSS>.json   ← dump raw mỗi lần pull (audit)
│   └── pending_words.csv               ← append word_mark chờ Cowork curate
├── processed_events.log        ← event_id đã xử lý (dedup, append-only)
└── logs/
    └── .gitkeep
```

## Main work — luồng `pull_events.php`

1. **Load config** + đọc `state/last_sync.json` (nếu thiếu → mặc định `1970-01-01T00:00:00Z`). `--since=<ISO>` override.
2. **GET** `{api_base}/api/events?since={last_sync}` với header `Authorization: Bearer {api_key}` + `Accept: application/json`. Retry 3 lần backoff 1s/3s/9s chỉ trên 5xx/timeout; 4xx fail nhanh.
3. Lọc bỏ event đã có `event_id` trong `processed_events.log` (idempotent).
4. **Dump** toàn bộ event mới ra `staging/events_<ts>.json` (audit nguyên gốc).
5. **Phân loại event mới:**
   - `type=horen_complete` → append `output/drills/horen_progress.csv` đúng schema hiện có `bai,ngay,dung,tong,pct,ghi_chu`:
     - `bai`=lesson_id, `ngay`=ngày (từ `created_at`, format `YYYY-MM-DD`), `dung`=payload.correct, `tong`=payload.total, `pct`=`round(correct/total*100)%`, `ghi_chu`=payload.notes (rỗng thì để trống). Dedup theo `bai+ngay` (đã có dòng cùng bài cùng ngày → ghi dòng mới, KHÔNG đè — append-only, để giữ lịch sử nhiều lần làm; hoặc skip nếu trùng event_id — chọn skip theo event_id).
   - `type=word_mark` → append `staging/pending_words.csv` schema:
     `event_id,word,word_status,lesson_id,context,clicked_at,curated` (cột `curated` để Cowork đánh dấu `0`→`1` sau khi đã merge vào weak_words/vocab_master).
   - `type=lesson_open` → chỉ nằm trong dump JSON, không cần xử lý thêm.
6. Ghi mọi `event_id` vừa xử lý vào `processed_events.log`.
7. **POST** `{api_base}/api/events/ack` body `{"event_ids":[...]}` (tất cả event vừa pull thành công). Chỉ ack SAU khi staging + progress đã ghi xong (atomic: ghi `.tmp` → rename).
8. Cập nhật `state/last_sync.json` = thời điểm `created_at` lớn nhất vừa pull (hoặc `now` nếu rỗng).
9. Log `logs/<date>.log` format `[YYYY-MM-DD HH:MM:SS] LEVEL msg`. In tổng kết:
   ```
   Pulled: N events (horen_complete: X, word_mark: Y, lesson_open: Z)
   horen_progress += X rows | pending_words += Y rows
   Acked: N | last_sync → <ISO>
   ```
- `--dry-run`: làm bước 1–5 (in plan + count), **KHÔNG** ghi file, **KHÔNG** ack, **KHÔNG** update state. Exit 0.

## Config (`config.example.php`)

```php
<?php
return [
    'api_base'        => 'https://deutsch.twv.app',
    'api_key'         => 'PASTE_SAME_BEARER_TOKEN_AS_SERVER_CONFIG',
    'timeout'         => 30,
    'retry'           => 3,
    'horen_progress'  => __DIR__ . '/../../output/drills/horen_progress.csv',
    'pending_words'   => __DIR__ . '/staging/pending_words.csv',
];
```

## Test (Henry chạy tuần tự)

1. `copy module\deutsch_web_sync\config.example.php module\deutsch_web_sync\config.php` → dán `api_key` (TRÙNG token trong config server).
2. **Dry-run:** `C:\php\php74\php.exe module\deutsch_web_sync\pull_events.php --dry-run` → in số event đang chờ (vd từ bài 4.29 vừa test), KHÔNG ghi gì, exit 0.
3. **Live:** `C:\php\php74\php.exe module\deutsch_web_sync\pull_events.php` → `staging/events_*.json` tạo ra; `horen_progress.csv` có thêm dòng 4.29; `pending_words.csv` có dòng word_mark (nếu đã click từ); ack OK; `last_sync.json` cập nhật.
4. **Idempotent:** chạy lại ngay → `Pulled: 0` (đã synced + processed).
5. **Word staging:** mở `staging/pending_words.csv` → thấy từ đã đánh dấu, cột `curated=0`.
6. **Error — sai token:** đổi `api_key` sai → chạy → `HTTP 401`, exit 1, KHÔNG ghi staging/progress, KHÔNG ack.
7. **Cron:** `schtasks /create /tn "Deutsch Web Sync" /tr "C:\twv_share\app\deutsch\module\deutsch_web_sync\cron.bat" /sc minute /mo 30` → sau 30' có `logs/cron_*.log`.

## Cấm

- ❌ Append/sửa `vocab_master.csv`, `weak_words.csv` — chỉ stage. Cowork curate sau.
- ❌ Gọi `lingq_sync` / push LingQ — Phase 4 (Cowork).
- ❌ Đụng dự án mieu.
- ❌ Hardcode `api_key` — đọc `config.php` (gitignore).
- ❌ Ack khi staging/progress chưa ghi xong (tránh mất event nếu crash giữa chừng).
- ❌ `git commit/push`, `php -l` local. Edit xong báo "edit xong, chờ review Cursor".
- ❌ Bịa field khi payload thiếu → log WARN, skip event đó, vẫn xử lý event khác + KHÔNG ack event bị skip.

## README cần có

- Quick start 5 dòng (copy config → dán token → dry-run → live → cron).
- **Workflow Cowork curate** (sau khi pull): mở `staging/pending_words.csv` → vai Vocab Extractor/Mistake Auditor xét từng từ → append `weak_words.csv`/`vocab_master.csv` thủ công có judgment → đánh `curated=1` → chạy `lingq_sync` push như thường (Phase 4).
- Troubleshooting: 401, timeout, last_sync sai (cách reset = sửa `state/last_sync.json`).

## Report cuối

```
✅ Deutsch Web Sync Phase 3 — done
Files: pull_events.php, config.example.php, cron.bat, .gitignore, README.md, state/, staging/, logs/.gitkeep
Verified: dry-run không ghi, idempotent qua processed_events.log, ack sau khi stage (atomic), horen_progress auto-append, word_mark → pending_words.csv (chờ Cowork curate), KHÔNG đụng vocab_master/weak_words/lingq_sync.
Lock cleared: .ai-locks/deutsch_web_sync.lock removed.
Pending: review Cursor.
```
