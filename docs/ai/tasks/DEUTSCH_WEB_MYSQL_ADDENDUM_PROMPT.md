# Deutsch Web — Addendum: chuyển SQLite → MySQL (deploy twv.app)

> **Task ID:** `deutsch_web_mysql`
> **Người triển khai:** Claude Code (Implementer). **Phụ thuộc:** đã build xong Phase 1 (SQLite) — task này CHỈ đổi tầng DB.
> **Lý do:** server deutsch.twv.app là shared cPanel **MySQL-first** (MySQL 5.7.44, `pdo_mysql` ✓; `pdo_sqlite` không xác nhận). DB riêng `apptwv_deutsch`. Ref: `docs/ai/DECISIONS.md` DD-20260529-006 #1 (đã cập nhật).
> **Lock:** tạo `.ai-locks/deutsch_web_mysql.lock` đầu task, xóa cuối.
>
> **Paste cho Claude Code:** `Đọc docs/ai/tasks/DEUTSCH_WEB_MYSQL_ADDENDUM_PROMPT.md và làm theo.`

---

## Goal

App chạy y hệt nhưng dùng **MySQL (PDO)** thay SQLite. Giữ nguyên router/views/api/auth/drill — chỉ đổi tầng kết nối + schema + config. Code phải chạy được cả PHP **7.4 và 8.1** (cPanel MultiPHP).

## Files đụng tới (chỉ những file này)

- `module/deutsch_web/lib/db.php` — đổi PDO DSN sang MySQL.
- `module/deutsch_web/migrations/001_init.sql` — viết lại cú pháp MySQL (InnoDB, utf8mb4).
- `module/deutsch_web/scripts/migrate.php` — chạy migration qua PDO MySQL; giữ `--add-user`.
- `module/deutsch_web/config.example.php` — đổi `db_path` → block `db` creds.
- `module/deutsch_web/.gitignore` — bỏ `data/*.sqlite` (không còn), giữ ignore `config.php`.
- `module/deutsch_web/README.md` — cập nhật mục DB + setup.
- **KHÔNG** đụng: api/*, views/*, lib/auth.php, lib/api_auth.php, lib/lesson_loader.php, public/*, lessons/* — trừ khi có truy vấn SQL phụ thuộc cú pháp SQLite (vd `datetime('now')`) thì sửa sang MySQL (`NOW()` / để DB default).

## Main work

1. **`config.example.php`** — thay `db_path` bằng:
   ```php
   'db' => [
       'host'    => 'localhost',          // cPanel: localhost (UNIX socket)
       'name'    => 'apptwv_deutsch',
       'user'    => 'apptwv_deutschu',
       'pass'    => 'PASTE_DB_PASSWORD',
       'charset' => 'utf8mb4',
   ],
   ```
   Giữ nguyên `api_key`, `session_name`, `audio_host`, `lessons_dir`, `horen_index`.

2. **`lib/db.php`** — PDO MySQL singleton:
   ```php
   $cfg = $config['db'];
   $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
   $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
       PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
       PDO::ATTR_EMULATE_PREPARES   => false,
   ]);
   ```
   Bỏ mọi `PRAGMA` SQLite. Giữ API hàm (vd `db()`) y nguyên để file khác không phải sửa.

3. **`migrations/001_init.sql`** — cú pháp MySQL 5.7 (JSON type OK):
   ```sql
   CREATE TABLE IF NOT EXISTS users (
     id            INT AUTO_INCREMENT PRIMARY KEY,
     username      VARCHAR(64)  NOT NULL UNIQUE,
     password_hash VARCHAR(255) NOT NULL,
     created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

   CREATE TABLE IF NOT EXISTS events (
     event_id   CHAR(36)    NOT NULL PRIMARY KEY,   -- uuid v4 sinh ở PHP
     user_id    INT         NOT NULL,
     type       VARCHAR(32) NOT NULL,               -- horen_complete | word_mark | lesson_open
     lesson_id  VARCHAR(16) NULL,
     payload    JSON        NOT NULL,
     created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
     synced_at  DATETIME    NULL DEFAULT NULL,
     INDEX idx_events_created (created_at),
     INDEX idx_events_sync (synced_at),
     INDEX idx_events_type (type),
     CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users(id)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```
   - Nếu lo `JSON` không hỗ trợ: fallback `payload LONGTEXT NOT NULL` (app đã `json_encode` rồi). Mặc định dùng `JSON`.

4. **`scripts/migrate.php`** — đọc `migrations/*.sql`, tách theo `;`, `PDO::exec()` từng câu (idempotent nhờ `IF NOT EXISTS`). In `users + events ready`. Giữ `--add-user <username>` (hỏi/nhận password → `password_hash()` → INSERT, bắt lỗi trùng username).

5. **Quét SQL phụ thuộc SQLite trong toàn module** (vd `datetime('now')`, `INSERT OR IGNORE`, `?` binding vẫn OK): đổi sang MySQL. `created_at`/`synced_at` để DB default hoặc set bằng PHP `date('Y-m-d H:i:s')` (ack đã là format này — giữ nguyên). Mọi truy vấn vẫn **prepared statement bind** (cấm nối chuỗi).

## Test (Henry chạy — môi trường local có MySQL, hoặc thẳng trên server staging)

1. Tạo DB+user local (hoặc dùng server). `copy config.example.php config.php` → điền creds.
2. `C:\php\php74\php.exe module\deutsch_web\scripts\migrate.php` → in `users + events ready`. Chạy lại → idempotent, không lỗi.
3. `... migrate.php --add-user henry` → insert user, password hashed.
4. Serve: `C:\php\php74\php.exe -S localhost:8080 -t module/deutsch_web/public module/deutsch_web/public/index.php` → login → drill 4.29 render OK.
5. Prüfen 1 bài + click 1 từ → kiểm phpMyAdmin/`SELECT * FROM events` thấy 2 row, `synced_at` NULL.
6. `GET /api/events?since=...` (Bearer) → 2 event. `POST /api/events/ack` → `synced_at` set, row CÒN.
7. Chạy thử PHP 8.1 (nếu có) → không deprecation chặn.

## Cấm (giữ như prompt Phase 1)

- ❌ Đụng dự án mieu / DB `apptwv_*` khác. CHỈ dùng `apptwv_deutsch`.
- ❌ Hardcode DB creds / api_key — đọc `config.php` (gitignore).
- ❌ Nối chuỗi SQL từ input — prepared statement hết.
- ❌ `git commit/push`, `php -l` local. Edit xong báo "edit xong, chờ review Cursor".
- ❌ Đổi schema `lessons/*.json`, đổi logic drill/api ngoài tầng DB.

## Report cuối

```
✅ Deutsch Web — MySQL conversion done
Files: lib/db.php, migrations/001_init.sql, scripts/migrate.php, config.example.php, .gitignore, README.md
Verified: PDO MySQL, prepared statements, migrate idempotent, ack giữ row, chạy PHP 7.4 (+8.1 nếu test).
Lock cleared: .ai-locks/deutsch_web_mysql.lock removed.
Pending: review Cursor.
```
