# Task: LINGQ_URLMD_SYNC — Tự động cập nhật url.md từ lingq_lessons.csv

> Đọc file này và làm theo. Tạo lock `.ai-locks/lingq_urlmd_sync.lock`. KHÔNG tự chạy `--apply` mass operation. Báo "edit xong, chờ review Cursor".

---

## 1. End-user

Solo dev học tiếng Đức. Sau mỗi lần push bài lên LingQ bằng `lessons_push.php`, CSV `data/lingq_lessons.csv` được cập nhật với `audio_url` và `lesson_id`. Hiện tại phải thủ công ghi 2 URL vào `url.md` trong từng thư mục bài. Cần script tự động hóa việc này.

---

## 2. Màn cuối cùng (Definition of Done)

Chạy:
```
C:\php\php74\php.exe module\lingq_sync\update_url_md.php
C:\php\php74\php.exe module\lingq_sync\update_url_md.php --apply
C:\php\php74\php.exe module\lingq_sync\update_url_md.php --folder input\html\deutsch-vorbereitung\horen\4.29\
```

Kết quả:
- Dry-run: in ra danh sách folder sẽ được update (lesson_id, audio_url, đường dẫn url.md)
- Apply: ghi/tạo `url.md` trong từng folder `source_local` với đầy đủ URL LingQ + audio URL
- Idempotent: chạy lại không thay đổi nếu url.md đã đúng
- Báo cáo: `N updated, M skipped (already OK), K created (new)`

---

## 3. Ví dụ dữ liệu thật

**Input CSV row** (`data/lingq_lessons.csv`):
```
44825394,2747707,"Digitalisierung in der Bildung",de,https://s3.amazonaws.com/media.lingq.com/resources/contents/audio/4.29.a0dc1a5e1c85.mp3,,41,input/html/deutsch-vorbereitung/horen/4.29/,2026-05-29,2026-05-29
```

**Output url.md** (`input/html/deutsch-vorbereitung/horen/4.29/url.md`):
```markdown
# URLs — Digitalisierung in der Bildung

## LingQ
- Lesson: https://www.lingq.com/en/learn/de/web/reader/44825394/
- lesson_id: 44825394
- course_id: 2747707
- Audio: https://s3.amazonaws.com/media.lingq.com/resources/contents/audio/4.29.a0dc1a5e1c85.mp3
- Synced: 2026-05-29

## Nguồn gốc
- Source: (giữ nguyên nếu đã có trong url.md cũ — KHÔNG xoá)
```

**Trường hợp url.md đã tồn tại:**
- Nếu đã có `lesson_id` khớp → kiểm tra `audio_url`, nếu khác → update dòng Audio, ghi `Synced` mới
- Nếu chưa có `lesson_id` → append section `## LingQ` vào cuối file (KHÔNG xoá nội dung cũ)
- Nếu `source_local` rỗng trong CSV → skip (bài không thuộc local repo)

---

## 4. Acceptance Tests

```
[ ] DRY-RUN: chạy không --apply → in list folders sẽ update, KHÔNG ghi file
[ ] SINGLE: --folder input\html\deutsch-vorbereitung\horen\4.29\ --apply → chỉ update 1 folder đó
[ ] BATCH apply: chạy không --folder → scan toàn bộ CSV rows có source_local → update tất cả
[ ] IDEMPOTENT: chạy lại sau khi apply → "0 updated, N skipped (already OK)"
[ ] PRESERVE: url.md đã có dòng "## Nguồn gốc" → dòng đó phải còn nguyên sau update
[ ] SKIP: CSV row có source_local rỗng → không tạo url.md
[ ] NEW: folder chưa có url.md → tạo mới với đủ lesson_id + audio_url + course_id + synced_date
```

---

## 5. Cấm đụng

- `data/lingq_lessons.csv` — chỉ đọc, KHÔNG ghi
- `module/lingq_sync/lingq_client.php` — KHÔNG gọi API LingQ (chỉ đọc CSV local)
- `data/03_unified/vocab_master.csv` — không liên quan
- Nội dung cũ trong url.md ngoài section `## LingQ` — preserve

---

## 6. Performance / Scale

- CSV hiện ~516 rows, khoảng 50-100 rows có `source_local` → xử lý đồng bộ, không cần async
- KHÔNG cần rate-limit (không gọi API)
- File I/O: dùng `file_get_contents` + `file_put_contents`, không cần stream
- Exit codes: 0 = OK, 1 = fatal (CSV parse error, file write error)

---

## 7. Format report cuối

```
[LINGQ URL.MD SYNC] 2026-05-29 HH:MM:SS
Mode: apply | dry-run
CSV rows scanned: N
  source_local present: K
  source_local empty (skip): N-K

Results:
  Created (new url.md):  X folder(s)
  Updated (audio_url):   Y folder(s)
  Skipped (already OK):  Z folder(s)
  Errors:                0

Log: module/lingq_sync/logs/urlmd_sync_YYYY-MM-DD.log
Done in X.Xs. Exit 0.
```

---

**File output:** `module/lingq_sync/update_url_md.php`
**Lock:** `.ai-locks/lingq_urlmd_sync.lock` (TTL 60 min)
**Tham khảo:** `module/lingq_sync/lessons_push.php` (cùng pattern CSV parse + dry-run/apply)
