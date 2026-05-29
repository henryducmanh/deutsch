# deutsch_web — Phase 1

Web phục vụ bài **Hören** DTZ B1 (login + drill + click-từ + progress + API read).
Stack: **PHP 7.4** (`C:\php\php74\php.exe`), **SQLite** (PDO), no framework, no Composer.
Deploy: `https://deutsch.twv.app`.

> **Ranh giới:** web chỉ **queue** dữ liệu vào bảng `events`. `vocab_master.csv` vẫn là source of truth —
> Cowork (máy local) pull qua API rồi curate + push LingQ. Web KHÔNG gọi GitHub/LingQ, KHÔNG đụng dự án mieu.

---

## Yêu cầu PHP (1 lần)

App cần extension **`pdo_sqlite`** + **`sqlite3`**. Trên `C:\php\php74` 2 dòng này đang bị
comment trong `php.ini` (DLL có sẵn trong `ext/`). Chọn 1 trong 2:

- **Cách A (khuyến nghị):** mở `C:\php\php74\php.ini`, bỏ dấu `;` ở 2 dòng:
  `extension=pdo_sqlite` và `extension=sqlite3`. (Implementer KHÔNG tự sửa — php.ini là
  tài nguyên dùng chung ngoài scope dự án.)
- **Cách B (không đụng php.ini):** thêm cờ vào mọi lệnh php:
  `-d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll`

Kiểm tra: `C:\php\php74\php.exe -m` phải thấy `pdo_sqlite` + `sqlite3`.

---

## Setup local

```bat
:: 1) Config
copy module\deutsch_web\config.example.php module\deutsch_web\config.php
:: → mở config.php, dán api_key ngẫu nhiên dài. Sinh nhanh:
C:\php\php74\php.exe -r "echo bin2hex(random_bytes(32));"

:: 2) Tạo schema (idempotent — chạy lại an toàn)
C:\php\php74\php.exe module\deutsch_web\scripts\migrate.php

:: 3) Seed user
C:\php\php74\php.exe module\deutsch_web\scripts\migrate.php --add-user henry
::   → hỏi password (ẩn). Hoặc truyền thẳng (kém an toàn):
::   ...migrate.php --add-user henry --password "MatKhauDai"

:: 4) Serve local — LƯU Ý: built-in server cần index.php làm ROUTER script
C:\php\php74\php.exe -S localhost:8080 -t module/deutsch_web/public module/deutsch_web/public/index.php
```

> **Vì sao serve khác prompt draft?** PHP built-in server (`php -S`) khi KHÔNG có router script
> sẽ trả 404 cho path không phải file thật (vd `/login`, `/lesson/4.29`). Truyền
> `public/index.php` làm router thì mọi route hoạt động; file tĩnh (`assets/*`) vẫn được serve trực tiếp
> (index.php `return false` cho file thật). Trên Apache twv.app thì `.htaccess` lo rewrite, không cần thủ thuật này.

Mở `http://localhost:8080` → chưa login → `/login`.

---

## Routes

| Route | Method | Auth | Mô tả |
|---|---|---|---|
| `/login` | GET/POST | — | Form đăng nhập (session). |
| `/logout` | GET | session | Xóa session. |
| `/` | GET | session | Danh sách bài Hören (badge điểm cao nhất). |
| `/lesson/{id}` | GET | session | Drill 1 bài (audio + radio + transcript + panel vocab). |
| `/track` | POST | session | Web ghi event (`horen_complete` / `word_mark` / `lesson_open`). |
| `/api/events?since=` | GET | **Bearer** | Event mới hơn `since` (ISO `Z`). |
| `/api/events/ack` | POST | **Bearer** | Set `synced_at`, KHÔNG xóa row. Body `{"event_ids":[...]}`. |
| `/api/unknown_words/pending` | GET | **Bearer** | Từ `word_mark` chưa sync. |
| `/api/lessons/{id}/vocab` | GET | session **hoặc** Bearer | Mảng vocab từ lesson JSON. POST = stub 501 (Phase 2). |

**Auth `/api/lessons/{id}/vocab`:** cho qua nếu có session (web đang dùng) HOẶC Bearer đúng (CLI). Lý do:
drill.js đã nhận vocab qua `window.LESSON` (inject server-side) nên web không cần Bearer; endpoint vẫn mở cho CLI.

---

## Cách dữ liệu chảy

1. Henry làm bài trên web → bấm **Prüfen** → JS tự `POST /track` event `horen_complete` (score, wrong[]).
2. Click badge số của từ trong panel → cycle `new → hard → ok` → `POST /track` event `word_mark` (word_status + context câu chứa từ).
3. Máy Cowork chạy CLI (Phase sau `module/deutsch_web_sync/pull_events.php`):
   - `GET /api/events?since=<lần trước>` → lấy event mới.
   - `GET /api/unknown_words/pending` → từ cần thêm vào `weak_words` + push LingQ.
   - `POST /api/events/ack` → đánh dấu đã xử lý (`synced_at` set, row giữ lại để audit).

---

## Test nhanh API (curl)

```bash
KEY=<api_key trong config.php>
BASE=http://localhost:8080

curl -H "Authorization: Bearer $KEY" "$BASE/api/events?since=2026-05-29T00:00:00Z"
curl -H "Authorization: Bearer $KEY" "$BASE/api/unknown_words/pending"
curl -X POST -H "Authorization: Bearer $KEY" -d '{"event_ids":["<id>"]}' "$BASE/api/events/ack"
# Thiếu/sai Bearer → HTTP 401.
```

---

## Deploy twv.app

1. Upload `module/deutsch_web/` lên server, **trỏ web root vào thư mục `public/`**.
2. Bật `mod_rewrite` (Apache) — `.htaccess` đã có sẵn rewrite + forward Authorization header.
3. Tạo `config.php` trên server (không commit), dán `api_key`.
4. Chạy `php scripts/migrate.php` + `--add-user henry` trên server.
5. Bật HTTPS → mở `'secure' => true` trong `lib/auth.php` cookie params (đang comment).
6. `data/` phải ghi được (SQLite + WAL). `data/*.sqlite` + `config.php` đã trong `.gitignore`.

---

## Schema DB

`migrations/001_init.sql` — `users` (id, username, password_hash) + `events` (append-only,
`synced_at NULL = pending`). WAL mode bật trong `lib/db.php`. Chạy qua `scripts/migrate.php`
(idempotent nhờ `IF NOT EXISTS`) — **KHÔNG gõ DDL tay**.

Payload theo type (cột `events.payload`, JSON):

```jsonc
// horen_complete
{ "score":"2/3", "correct":2, "total":3, "wrong":["4.29-1","4.29-3"], "notes":"" }
// word_mark
{ "word":"antizipieren", "word_status":"hard", "context":"...", "vocab_id":null }
// lesson_open
{ }
```

---

## Lesson JSON

`lessons/{id}.json` schema `deutsch_web_lesson_v1`. `4.29.json` do Cowork sinh (canonical).
`4.30/4.31` Implementer sinh từ prototype `output/drills/horen_test_4.29-4.31.html` + url.md.
`vocab[].vocab_id = null` = chưa link `vocab_master` (Cowork điền sau, KHÔNG bịa ID).
Audio dùng `audio.url` (LingQ S3) — KHÔNG upload MP3 lên server.

---

## Troubleshooting

- **`PDOException: could not find driver`**: chưa bật `pdo_sqlite` → xem mục "Yêu cầu PHP" trên (cách A hoặc B).
- **404 cho `/login`** khi `php -S`: thiếu router script → thêm `module/deutsch_web/public/index.php` vào cuối lệnh serve.
- **500 "config.php chưa có"**: chưa copy `config.example.php` → `config.php`.
- **401 mọi API**: `api_key` còn để `PASTE_LONG_RANDOM_TOKEN_HERE`, hoặc thiếu header `Authorization: Bearer`.
- **Bearer mất trên Apache CGI**: `.htaccess` đã forward `HTTP_AUTHORIZATION`; `api_auth.php` đọc cả `REDIRECT_HTTP_AUTHORIZATION` + `apache_request_headers()`.
- **`data/` không ghi được**: cấp quyền ghi cho thư mục `data/` (SQLite cần tạo file + WAL/SHM).
```
