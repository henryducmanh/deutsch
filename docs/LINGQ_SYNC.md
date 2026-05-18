# LingQ Sync — Quick Reference

> Tra cứu nhanh các lệnh hay dùng. Spec đầy đủ: [`docs/ai/tasks/LINGQ_SYNC_PROMPT.md`](ai/tasks/LINGQ_SYNC_PROMPT.md). Code: [`module/lingq_sync/`](../module/lingq_sync/).

---

## 3 lệnh chính (chạy trong root `C:\twv_share\app\deutsch`)

```bat
C:\php\php74\php.exe module\lingq_sync\sync.php --dry-run
C:\php\php74\php.exe module\lingq_sync\sync.php
schtasks /create /tn "LingQ Sync Daily" /tr "C:\twv_share\app\deutsch\module\lingq_sync\cron.bat" /sc daily /st 06:00
```

| Lệnh | Khi nào dùng |
|---|---|
| `sync.php --dry-run` | Test sau khi đổi token, đổi config, hoặc lần đầu. Fetch nhưng KHÔNG ghi CSV. |
| `sync.php` | Sync thật. Output: `data/lingq_cards.csv` + log `module/lingq_sync/logs/<date>.log`. |
| `schtasks /create ...` | Đăng ký cron Windows daily 6h sáng. Chỉ chạy 1 lần lúc setup. |

---

## Setup lần đầu (4 bước)

1. **Token:** vào https://www.lingq.com/accounts/apikey/ → copy token.
2. **Config:**
   ```bat
   copy module\lingq_sync\config.example.php module\lingq_sync\config.php
   ```
   Mở `config.php`, paste token vào field `'api_key'`.
3. **Test:** chạy `sync.php --dry-run` → exit 0, không ghi CSV.
4. **Live + cron:** chạy `sync.php` lần đầu để dump 2728 LingQ, rồi đăng ký `schtasks` daily.

---

## Quản lý cron Windows

```bat
REM Xem task đã đăng ký
schtasks /query /tn "LingQ Sync Daily"

REM Chạy ngay (không chờ 6h sáng)
schtasks /run /tn "LingQ Sync Daily"

REM Xoá task
schtasks /delete /tn "LingQ Sync Daily" /f

REM Đổi giờ chạy sang 7:00
schtasks /change /tn "LingQ Sync Daily" /st 07:00
```

Cron log riêng: `module/lingq_sync/logs/cron_<date>.log`.

---

## Troubleshoot

| Triệu chứng | Khả năng cao | Fix |
|---|---|---|
| `HTTP 401 Unauthorized` | Token sai hoặc hết hạn | Tạo token mới tại link trên, paste vào `config.php`. |
| `HTTP 403 Forbidden` | Token đúng nhưng account tier không cho API | Kiểm tra Lifetime Premium còn active không. |
| Exit 1, log `extension curl not loaded` | PHP 7.4 build thiếu extension | Mở `C:\php\php74\php.ini`, bỏ `;` trước `extension=curl`. |
| CSV `data/lingq_cards.csv` rỗng sau sync | Hết quota API hoặc network drop | Check `logs/<date>.log`, retry sau 5 phút. |
| Cron không chạy 6h sáng | Máy ngủ / tắt | `schtasks /change /tn "LingQ Sync Daily" /ru SYSTEM` hoặc đổi giờ. |
| Log nhiều `Removed: N` | LingQ bị xoá trên web | KHÔNG xoá CSV row; review thủ công file `lingq_cards.csv`. |

---

## Cấm

- ❌ Sửa trực tiếp `data/lingq_cards.csv` bằng Excel rồi save lại — Excel sẽ phá UTF-8 BOM và quote. Edit qua Notepad++ / VSCode nếu cần.
- ❌ Commit `config.php` (đã có trong `.gitignore`, nhưng double-check trước khi `git add .`).
- ❌ Sync 2 chiều (push known status về LingQ) — chưa support, sẽ là phase D.
- ❌ Tự merge `lingq_cards.csv` → `vocab_master.csv`. Việc đó là vai **Vocab Extractor**, trigger: "đóng vai Vocab Extractor merge LingQ".

---

**Last updated:** 2026-05-18.
