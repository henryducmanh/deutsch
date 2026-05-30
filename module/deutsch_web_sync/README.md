# deutsch_web_sync — Pull events Web → local (Phase 3)

Cầu **một chiều** `deutsch.twv.app` → repo `deutsch` local. Kéo event học tập (user
đánh dấu trên mobile) về máy Cowork, **stage** lại để Cowork curate sau. KHÔNG tự merge
vào "não dữ liệu".

| Làm | Không làm |
|---|---|
| ✅ auto-append `output/drills/horen_progress.csv` (progress cơ học) | ❌ append `data/03_unified/vocab_master.csv` |
| ✅ stage `word_mark` → `staging/pending_words.csv` (cột `curated=0`) | ❌ append `data/weak_words.csv` |
| ✅ dump audit raw `staging/events_<ts>.json` | ❌ gọi `lingq_sync` / push LingQ (Phase 4) |
| ✅ ack server SAU khi ghi xong | ❌ ack khi staging/progress chưa ghi |

---

## Quick start (Henry)

```bat
copy module\deutsch_web_sync\config.example.php module\deutsch_web_sync\config.php
REM mở config.php → dán api_key (TRÙNG token trong module\deutsch_web\config.php)
C:\php\php74\php.exe module\deutsch_web_sync\pull_events.php --dry-run   REM xem trước, không ghi
C:\php\php74\php.exe module\deutsch_web_sync\pull_events.php            REM live
schtasks /create /tn "Deutsch Web Sync" /tr "C:\twv_share\app\deutsch\module\deutsch_web_sync\cron.bat" /sc minute /mo 30
```

## Flags

| Flag | Tác dụng |
|---|---|
| `--dry-run` | Bước 1–5 (GET + phân loại + in count). KHÔNG ghi file, KHÔNG ack, KHÔNG update state. Exit 0. |
| `--since=<ISO>` | Override `last_sync` (vd `--since=2026-05-29T00:00:00Z`). Dùng để pull lại quá khứ. |

## Luồng (pull_events.php)

1. Load `config.php` + `state/last_sync.json` (thiếu → `1970-01-01T00:00:00Z`).
2. `GET {api_base}/api/events?since=...` (Bearer). Retry 3× 1s/3s/9s trên 5xx/timeout; 4xx fail nhanh.
3. Bỏ event có `event_id` trong `processed_events.log` (idempotent).
4. Dump event mới → `staging/events_<ts>.json`.
5. Phân loại:
   - `horen_complete` → append `horen_progress.csv` (`bai,ngay,dung,tong,pct,ghi_chu`).
   - `word_mark` → append `staging/pending_words.csv` (`event_id,word,word_status,lesson_id,context,clicked_at,curated`).
   - `lesson_open` → chỉ nằm trong dump.
   - Payload thiếu field bắt buộc → **WARN + skip** (KHÔNG ack, KHÔNG vào processed log → lần sau pull lại).
6. Ghi `processed_events.log`.
7. `POST /api/events/ack` (chỉ sau khi staging+progress+processed ghi xong; mọi ghi đều atomic tmp→rename).
8. Cập nhật `state/last_sync.json` = `created_at` lớn nhất vừa pull.

## Workflow Cowork curate (sau khi pull)

1. Mở `staging/pending_words.csv` → các từ user đánh dấu chờ xét, `curated=0`.
2. Vai **Vocab Extractor** / **Mistake Auditor** xét từng từ (judgment, KHÔNG cơ học):
   - quyết wortart / artikel / nghĩa → append thủ công `data/weak_words.csv` và/hoặc `data/03_unified/vocab_master.csv`.
3. Đánh dấu dòng đã xử lý: sửa `curated` `0` → `1`.
4. Chạy `lingq_sync` push như thường (**Phase 4**) để đẩy lên LingQ.

> `horen_progress.csv` thì auto — script đã append, không cần làm tay.

## Files

```
config.example.php   template (commit)        config.php          token thật (gitignored)
pull_events.php      entry CLI                 cron.bat            wrapper Task Scheduler
state/last_sync.json con trỏ sync (runtime)    staging/            dump JSON + pending_words.csv (runtime)
processed_events.log dedup append-only         logs/<date>.log     log script
```

## Troubleshooting

| Triệu chứng | Nguyên nhân / fix |
|---|---|
| `HTTP 401` exit 1 | `api_key` trong `config.php` SAI / không trùng token server. Đối chiếu `module\deutsch_web\config.php`. KHÔNG ghi/ack gì → an toàn chạy lại. |
| `HTTP 500: api_key chưa cấu hình` | Server `deutsch_web/config.php` còn placeholder. Set token thật bên server. |
| `GET network error sau N lần` | Mạng / server down. Retry 3× rồi fail. Chạy lại sau. |
| Pull lặp ra `0` dù vừa click | Server `synced_at` đã set (đã ack) **và** `last_sync` đã tiến. Bình thường (idempotent). |
| Cần kéo lại từ quá khứ | `--since=2026-05-01T00:00:00Z` (event đã ack vẫn trả theo `created_at`, nhưng `processed_events.log` lọc → muốn re-stage thì xóa dòng tương ứng trong `processed_events.log`). |
| `last_sync` sai / kẹt | Sửa tay `state/last_sync.json` → `{"last_sync":"1970-01-01T00:00:00Z"}` để reset, rồi chạy lại. |
| `cURL extension chưa bật` | Bật `extension=curl` trong `php.ini` của `C:\php\php74`. |
