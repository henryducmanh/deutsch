# Deploy deutsch.twv.app trên cPanel (shared hosting apptwv)

> Hướng dẫn cài `module/deutsch_web` lên `https://deutsch.twv.app`.
> Stack chốt: PHP 7.4 + **MySQL** (DB riêng `apptwv_deutsch`). Ref: `docs/ai/DECISIONS.md` DD-20260529-006.
> **Tiền đề:** Claude Code đã chạy xong addendum MySQL (`docs/ai/tasks/DEUTSCH_WEB_MYSQL_ADDENDUM_PROMPT.md`). Nếu chưa → làm bước đó trước.

---

## 0. Môi trường server (đã xác nhận từ cPanel)

| Mục | Giá trị |
|---|---|
| Account | `apptwv` (`/home/apptwv/`) |
| Docroot subdomain | `/home/apptwv/web/deutsch.twv.app/` → **sẽ đổi sang `/web/deutsch.twv.app/public`** (Cách A, đã chốt) |
| `.twv` (folder) | thư mục tạm — **xóa được** |
| `.htaccess` hiện có | khối **handler PHP 7.4 do cPanel tự sinh** (`# BEGIN cPanel-generated handler`) — **PHẢI giữ**, xem mục 4 |
| MySQL | 5.7.44, localhost (UNIX socket), quản lý qua phpMyAdmin |
| PHP | MultiPHP — có 7.4.33 và 8.1.34; `pdo_mysql` ✓, `mysqli` ✓, `curl/mbstring/json` ✓ |

> ✅ **Đã làm rõ (2026-05-29):** `.twv` là thư mục tạm — xóa. `.htaccess` trong docroot là khối handler PHP 7.4 cPanel tự sinh. Henry đồng ý đổi Document Root sang `/web/deutsch.twv.app/public` → đi **Cách A**. Lưu ý thứ tự thao tác ở mục 3–4 để KHÔNG mất handler PHP.

---

## 1. Tạo MySQL database + user (cPanel → MySQL® Databases)

1. **Create New Database:** tên `deutsch` → cPanel thành `apptwv_deutsch`.
2. **Add New User:** username `deutschu` → thành `apptwv_deutschu`. Đặt password mạnh (lưu lại để điền `config.php`).
3. **Add User To Database:** chọn user `apptwv_deutschu` + DB `apptwv_deutsch` → tick **ALL PRIVILEGES** → Make Changes.

> Database này hoàn toàn tách biệt với DB mieu/các app `apptwv_*` khác — chỉ chung MySQL server.

---

## 2. Chọn PHP version + kiểm extension (cPanel → Select PHP Version / MultiPHP Manager)

1. MultiPHP Manager → chọn domain `deutsch.twv.app` → set **PHP 7.4** (khớp bản build + lingq_sync). (8.1 cũng chạy được nếu sau này muốn.)
2. Select PHP Version → Extensions → đảm bảo bật: `pdo_mysql`, `mysqli`, `mbstring`, `json`, `curl`, `openssl`. (Đã ✓ theo ảnh check.)
3. Không cần `pdo_sqlite` nữa (đã chuyển MySQL).

---

## 3. Upload code + bố trí docroot

App có `public/` là web-root, phần `lib/ api/ views/ lessons/ config.php` **không được** để web truy cập trực tiếp.

### Cách A — đổi Document Root sang `public/` (đã chốt)

**Thứ tự quan trọng** (để không mất handler PHP 7.4):

1. Xóa thư mục tạm `.twv` trong `web/deutsch.twv.app/`.
2. Upload toàn bộ nội dung `module/deutsch_web/` (File Manager Upload, hoặc zip rồi Extract) vào `/home/apptwv/web/deutsch.twv.app/`.
3. cPanel → **Domains** → `deutsch.twv.app` → **Manage** → đổi **Document Root** thành `web/deutsch.twv.app/public` → Save.
4. **Set lại PHP version SAU khi đổi docroot:** MultiPHP Manager → chọn `deutsch.twv.app` → PHP 7.4 → Apply. cPanel sẽ ghi khối handler `# BEGIN cPanel-generated handler` vào **`public/.htaccess`** (docroot mới).
5. Mở `public/.htaccess`: giữ nguyên khối handler cPanel, thêm rewrite rules (mục 4) **bên dưới**. Nếu cPanel chưa chèn → copy khối handler từ `.htaccess` cũ ở `web/deutsch.twv.app/` sang đầu `public/.htaccess`.

> ⚠️ Apache **không** đọc `.htaccess` nằm trên DocumentRoot. Sau khi docroot = `public/`, file `.htaccess` cũ ở `web/deutsch.twv.app/` sẽ bị bỏ qua → handler PHP phải nằm trong `public/.htaccess`, nếu không file `.php` chạy sai bản PHP. Đó là lý do phải làm bước 4–5.

**Kết quả:** web chỉ thấy `public/` (index.php + assets); `lib/ api/ lessons/ config.php` nằm 1 cấp trên docroot → an toàn. Router `public/index.php` tự `require` các file `../api/*`, `../lib/*` (không bị lộ).

```
/home/apptwv/web/deutsch.twv.app/
├── public/        ← Document Root mới
│   ├── index.php  ·  .htaccess  ·  assets/{drill.css,drill.js}
├── api/  lib/  views/  lessons/  migrations/  scripts/
├── config.php     ← creds, NGOÀI docroot ✓
└── README.md
```

### Cách B — docroot cố định (nếu cPanel không cho đổi Document Root)

1. Upload `module/deutsch_web/` vào **ngoài** web root, vd `/home/apptwv/deutsch_web/`.
2. Copy nội dung `public/assets/` vào `/home/apptwv/web/deutsch.twv.app/assets/` (asset tĩnh cần web phục vụ).
3. Tạo `/home/apptwv/web/deutsch.twv.app/index.php`:
   ```php
   <?php require '/home/apptwv/deutsch_web/public/index.php';
   ```
   (`__DIR__` trong file gốc vẫn trỏ `/home/apptwv/deutsch_web/public` → các `require ../lib` chạy đúng.)
4. `.htaccess` (mục 4) đặt tại docroot, rewrite mọi request về `index.php` trừ `assets/`.

> Cách A gọn hơn và an toàn hơn (config.php không nằm trong vùng web). Ưu tiên A; chỉ dùng B nếu không đổi được docroot.

---

## 4. .htaccess (router + bảo mật)

Trong `public/.htaccess` (Cách A). **Giữ nguyên khối cPanel handler ở trên cùng**, thêm phần dưới đây **bên dưới** khối đó — đừng xóa/đè khối handler:

```apache
# (Phía trên là khối "# BEGIN cPanel-generated handler ... # END" — GIỮ NGUYÊN)

RewriteEngine On

# Ép HTTPS (cPanel AutoSSL)
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Phục vụ file tĩnh có thật (assets) trực tiếp
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Còn lại → front controller
RewriteRule ^ index.php [L]

# Chặn lộ file nhạy cảm
<FilesMatch "(^\.|\.(php|sqlite|sql|md|json)$)">
  # cho phép index.php; chặn dotfiles + nguồn
</FilesMatch>
<Files config.php>
  Require all denied
</Files>
```

> Nếu dùng Cách A, `config.php` đã nằm ngoài docroot nên không cần block; khối `<Files>` chỉ là phòng thủ thừa, vô hại.

---

## 5. config.php

```bash
# trong thư mục app trên server
cp config.example.php config.php
```
Sửa `config.php`:
```php
'db' => [
    'host'    => 'localhost',
    'name'    => 'apptwv_deutsch',
    'user'    => 'apptwv_deutschu',
    'pass'    => '<password vừa đặt ở bước 1>',
    'charset' => 'utf8mb4',
],
'api_key' => '<token ngẫu nhiên dài — sinh: openssl rand -hex 32>',
```
`api_key` này chỉ Cowork/CLI dùng (Bearer) — KHÔNG lộ ra frontend.

---

## 6. Migrate + tạo user đăng nhập

Qua SSH (nếu có) hoặc cPanel **Terminal**:
```bash
cd /home/apptwv/web/deutsch.twv.app        # (Cách B: /home/apptwv/deutsch_web)
/usr/local/bin/ea-php74 scripts/migrate.php            # tạo bảng users + events
/usr/local/bin/ea-php74 scripts/migrate.php --add-user henry   # đặt password đăng nhập web
```
> Đường dẫn PHP CLI trên cPanel thường là `/usr/local/bin/ea-php74`. Nếu không có Terminal/SSH: tạo tạm file `_migrate_web.php` gọi logic migrate, chạy 1 lần qua trình duyệt rồi **xóa ngay** (đừng để lại).

Kiểm bằng phpMyAdmin → DB `apptwv_deutsch` → thấy 2 bảng `users`, `events`; bảng `users` có 1 row henry.

---

## 7. Test live

1. Mở `https://deutsch.twv.app` → redirect `/login`.
2. Đăng nhập henry → vào danh sách bài → mở `/lesson/4.29` → audio (LingQ S3) play được, radio a–f, panel Vokabeln mở ra.
3. Prüfen 1 bài + click 1 từ → phpMyAdmin `SELECT type,lesson_id,payload FROM events` thấy 2 row, `synced_at` NULL.
4. Từ máy Cowork:
   ```bash
   curl -H "Authorization: Bearer <api_key>" "https://deutsch.twv.app/api/events?since=2026-05-29T00:00:00Z"
   curl -H "Authorization: Bearer <api_key>" https://deutsch.twv.app/api/unknown_words/pending
   ```
5. Mở bằng điện thoại → kiểm responsive + panel vocab.

---

## 8. Audio

Phase 1 dùng thẳng URL LingQ S3 trong `lessons/*.json` (`audio.url`) → **không** upload 220 MB MP3 lên twv.app. Nếu sau này S3 đổi: bật `audio_host=local` trong config + upload MP3 (đã có `audio.local_path` làm gốc) — việc của Phase sau.

---

## 9. Checklist bảo mật (trước khi coi là xong)

- [ ] HTTPS bật (cPanel → SSL/TLS Status → AutoSSL cho deutsch.twv.app), `.htaccess` ép redirect.
- [ ] `config.php` ngoài docroot (Cách A) hoặc bị `Require all denied` (Cách B).
- [ ] `api_key` ≥ 32 ký tự ngẫu nhiên; password user mạnh + đã `password_hash`.
- [ ] Mọi truy vấn prepared statement (Claude Code đã làm — verify khi review Cursor).
- [ ] Không có file `_migrate_web.php` tạm còn sót trên server.
- [ ] DB user `apptwv_deutschu` chỉ có quyền trên `apptwv_deutsch`, không đụng DB khác.

---

## 10. Bước sau (KHÔNG thuộc deploy này)

- **Phase 3:** `module/deutsch_web_sync/pull_events.php` chạy trên máy Cowork (cron 30') → pull API → staging → append `weak_words` → `lingq_sync` → ack. Cần soạn prompt riêng.
- Batch 344 bài Hören thành `lessons/*.json` (Phase 5).

---

## 11. Troubleshooting nhanh

| Triệu chứng | Nguyên nhân thường gặp |
|---|---|
| 500 ngay trang đầu | Sai DB creds trong `config.php`, hoặc PHP version chưa bật `pdo_mysql`. Xem `error_log` trong docroot. |
| `/login` ra 404 | `.htaccess` rewrite chưa đúng / `mod_rewrite` chưa nhận; hoặc Document Root chưa trỏ `public` (Cách A). |
| Trang trắng, không lỗi | Bật `display_errors` tạm trong `config`/php.ini-user, xem log. |
| Audio không phát | URL S3 đổi/hết hạn → kiểm `lessons/*.json` `audio.url`. |
| API trả 401 | Thiếu/sai header `Authorization: Bearer`. |
| Tiếng Việt/Đức lỗi font trong DB | DB/bảng/connection chưa `utf8mb4` (đã set ở schema + DSN). |
