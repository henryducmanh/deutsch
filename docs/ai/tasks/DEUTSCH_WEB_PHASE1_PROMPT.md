# Deutsch Web — Phase 1 (PHP serve drill + login + click-từ + progress + API read)

> **Task ID:** `deutsch_web_phase1`
> **Người triển khai:** Claude Code (vai Implementer — KHÔNG phải 8 vai domain).
> **Stack:** PHP 7.4 (`C:\php\php74\php.exe`), **SQLite** (PDO), no framework, no Composer. Deploy lên `https://deutsch.twv.app`.
> **Ref brief:** `brainstorm/deutsch-web-platform-brief.md` · **Quyết định:** `docs/ai/DECISIONS.md` DD-20260529-006.
> **Lock:** Claude Code tạo `.ai-locks/deutsch_web_impl.lock` đầu task, xóa cuối task.
>
> **Paste 1 dòng cho Claude Code:** `Đọc docs/ai/tasks/DEUTSCH_WEB_PHASE1_PROMPT.md và làm theo.`

---

## 1. End-user

**Henry** — solo dev, học DTZ B1, thi 06/2026. Muốn học bài Hören **mọi nơi** (laptop ở nhà + mobile khi đi ngoài), không phụ thuộc máy có repo local. Cần:

- Đăng nhập `deutsch.twv.app` (1 user, password riêng — KHÔNG dùng tài khoản mieu).
- Mở danh sách bài Hören → làm 1 bài → chấm điểm tại chỗ, xem transcript.
- Click từ trong đề / panel vocab → đánh dấu **"chưa biết"** (toggle new / ok / hard) → lưu server.
- Điểm bài + từ đánh dấu được ghi DB → Cowork (máy local) pull về sau qua API để append `weak_words` + push LingQ.
- Không ghi tay `output/drills/horen_progress.csv` nữa.

**Ranh giới tuyệt đối:** web chỉ **queue** dữ liệu. `vocab_master.csv` vẫn là source of truth, Cowork curate rồi append. Web KHÔNG gọi GitHub API, KHÔNG push LingQ, KHÔNG đụng dự án mieu (`C:\twv_share\app\code\mieu` chỉ tham khảo pattern, không import/ghép DB).

---

## 2. Màn cuối cùng (definition of done)

### Files xuất hiện sau khi Claude Code làm xong

```
module/deutsch_web/
├── public/
│   ├── index.php                 ← front controller / router
│   ├── .htaccess                 ← rewrite tất cả về index.php
│   └── assets/
│       ├── drill.css             ← TÁCH từ <style> của horen_test_4.29-4.31.html (Phase 0)
│       └── drill.js              ← TÁCH từ <script>, bỏ vocabData hardcode, load qua JSON (Phase 0)
├── api/
│   ├── events.php                ← GET /api/events?since= , POST /api/events/ack
│   ├── unknown_words.php         ← GET /api/unknown_words/pending
│   └── lessons.php               ← GET /api/lessons/{id}/vocab  (+ POST stub Phase 2)
├── views/
│   ├── login.php
│   ├── lesson_list.php
│   └── drill_horen.php           ← template render từ lesson JSON (Phase 0)
├── lib/
│   ├── db.php                    ← PDO SQLite singleton + migrate runner
│   ├── auth.php                  ← session login (pattern mieu, KHÔNG dùng DB mieu)
│   ├── api_auth.php              ← check Bearer token cho /api/*
│   └── lesson_loader.php         ← đọc lessons/{id}.json + horen_lessons.csv
├── migrations/
│   └── 001_init.sql              ← schema SQLite (users, events)
├── lessons/
│   ├── 4.29.json                 ← ĐÃ CÓ (Cowork sinh — canonical, KHÔNG sửa schema)
│   ├── 4.30.json                 ← Phase 0 sinh thêm từ prototype (chứng minh multi-lesson)
│   └── 4.31.json                 ← Phase 0 sinh thêm
├── data/
│   └── .gitkeep                  ← deutsch_web.sqlite sinh runtime ở đây (gitignored)
├── scripts/
│   └── migrate.php               ← chạy migrations/*.sql 1 lần (KHÔNG gõ DDL tay ở console)
├── config.example.php            ← template, user copy thành config.php
├── .gitignore                    ← config.php, data/*.sqlite, logs/
└── README.md                     ← setup local + deploy twv.app + troubleshooting
```

> **Phase 2 / sau (KHÔNG làm trong task này):** `module/deutsch_web_sync/pull_events.php` (CLI máy Cowork) + nối `weak_words` + `lingq_sync`. Đó là vai Cowork, không phải prompt này.

### UX expected

1. Vào `https://deutsch.twv.app` chưa login → redirect `/login`. Đăng nhập đúng → vào danh sách bài.
2. Danh sách bài Hören (đọc `lessons/*.json`, đối chiếu tên từ `input/html/deutsch-vorbereitung/horen_lessons.csv`). Bài đã làm có badge điểm.
3. Click 1 bài → `/lesson/4.29` → drill **giống hệt** prototype `horen_test_4.29-4.31.html`: audio sticky, radio a–f, nút Prüfen chấm điểm, transcript toggle, **panel vocab bên phải** (load từ lesson JSON, KHÔNG hardcode trong JS).
4. Bấm Prüfen → chấm 2/3 → tự POST `/track` event `horen_complete` → ghi DB (không reload).
5. Click 1 từ trong panel/đề → toggle new→hard → POST `/track` event `word_mark` → ghi DB.
6. Trên máy Cowork: `curl -H "Authorization: Bearer <key>" https://deutsch.twv.app/api/events?since=2026-05-29T00:00:00Z` → trả JSON các event mới.
7. `GET /api/unknown_words/pending` → trả từ đánh dấu chưa sync. `POST /api/events/ack` → set `synced_at`, KHÔNG xóa row.

---

## 3. Ví dụ dữ liệu thật

### 3.1 Lesson JSON (đã có — `lessons/4.29.json`, schema `deutsch_web_lesson_v1`)

Trích cấu trúc (file đầy đủ Cowork đã sinh, dùng làm khuôn cho 4.30/4.31):

```json
{
  "schema_version": "deutsch_web_lesson_v1",
  "lesson_id": "4.29",
  "modul": "Hören", "niveau": "B1", "thema": "Digitalisierung",
  "title": "Digitalisierung in der Bildung",
  "instructions": "Sie hören Aussagen zu einem Thema. Welcher der Sätze a–f passt...",
  "source": { "origin_url": "...uebung-1233.html", "lingq_lesson_id": 44825394, "lingq_course_id": 2747707 },
  "audio": { "url": "https://s3.amazonaws.com/media.lingq.com/.../4.29.a0dc1a5e1c85.mp3", "host": "lingq_s3", "local_path": "input/html/.../4.29/4.29.mp3" },
  "aussagen": [ { "id": "4.29-1", "label": "Aussage 1", "correct": "d", "options": [ {"key":"a","text":"..."}, ... ] }, ... ],
  "transcript": [ { "label": "Aussage 1", "text": "...", "key_phrase": "..." }, ... ],
  "vocab": [ { "w": "Digitalisierung", "art": "die · Subst.", "m": "số hóa", "lv": "new", "vocab_id": null }, ... ]
}
```

- `audio.host = "lingq_s3"` → frontend dùng `audio.url`. **KHÔNG upload MP3 220 MB lên twv.app phase này** (DD-20260529-006 #3).
- `vocab[].vocab_id = null` → CHƯA link `vocab_master`. KHÔNG bịa ID. Cowork điền sau.
- 4.30 / 4.31: lấy nội dung từ chính prototype `horen_test_4.29-4.31.html` (lesson-1, lesson-2, `vocabData[1]`, `vocabData[2]`, audio src 4.30/4.31) → đổ vào JSON cùng schema.

### 3.2 SQLite schema (`migrations/001_init.sql`)

```sql
CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Log học tập append-only. synced_at NULL = pending (chưa Cowork ack).
CREATE TABLE IF NOT EXISTS events (
  event_id    TEXT PRIMARY KEY,              -- uuid v4 sinh ở PHP
  user_id     INTEGER NOT NULL,
  type        TEXT NOT NULL,                 -- horen_complete | word_mark | lesson_open
  lesson_id   TEXT,                          -- vd '4.29'
  payload     TEXT NOT NULL,                 -- JSON string (xem 3.3)
  created_at  TEXT NOT NULL DEFAULT (datetime('now')),
  synced_at   TEXT DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at);
CREATE INDEX IF NOT EXISTS idx_events_sync    ON events(synced_at);
CREATE INDEX IF NOT EXISTS idx_events_type    ON events(type);
```

> **DDL:** SQLite app-managed qua `scripts/migrate.php` chạy 1 lần (đọc `migrations/*.sql`, idempotent nhờ `IF NOT EXISTS`). KHÔNG gõ DDL tay ở console. (Rule "schema_definitions.php + admin/db_tools" trong CLAUDE.md user là pattern **MySQL dự án mieu** — KHÔNG áp dụng cho app SQLite độc lập này. Nếu Henry muốn theo pattern db_tools thì báo trước khi build.)

### 3.3 Payload từng event type (JSON trong cột `payload`)

```jsonc
// type = horen_complete
{ "score": "2/3", "correct": 2, "total": 3, "wrong": ["4.29-1","4.29-3"], "notes": "" }

// type = word_mark
{ "word": "antizipieren", "word_status": "hard", "context": "Arbeitskräfte sollten Fähigkeiten ... antizipieren", "vocab_id": null }

// type = lesson_open
{ }
```

### 3.4 API response

```jsonc
// GET /api/events?since=2026-05-29T00:00:00Z   (Authorization: Bearer <key>)
{ "count": 2, "events": [
  { "event_id": "9f...", "type": "horen_complete", "lesson_id": "4.30", "payload": {"score":"3/3",...}, "created_at": "2026-05-29T14:30:11Z", "synced_at": null },
  { "event_id": "a1...", "type": "word_mark",      "lesson_id": "4.30", "payload": {"word":"antizipieren","word_status":"hard",...}, "created_at": "2026-05-29T14:31:02Z", "synced_at": null }
]}

// GET /api/unknown_words/pending
{ "count": 1, "words": [ { "event_id":"a1...", "word":"antizipieren", "word_status":"hard", "lesson_id":"4.30", "context":"...", "clicked_at":"2026-05-29T14:31:02Z" } ] }

// POST /api/events/ack    body: {"event_ids":["9f...","a1..."]}
{ "acked": 2 }
```

### 3.5 Config (`config.example.php`)

```php
<?php
return [
    'db_path'       => __DIR__ . '/data/deutsch_web.sqlite',
    'api_key'       => 'PASTE_LONG_RANDOM_TOKEN_HERE',   // Bearer cho /api/* — chỉ Cowork/CLI
    'session_name'  => 'deutsch_web_sess',
    'audio_host'    => 'lingq_s3',                        // lingq_s3 | local (phase sau)
    'lessons_dir'   => __DIR__ . '/lessons',
    'horen_index'   => __DIR__ . '/../../input/html/deutsch-vorbereitung/horen_lessons.csv',
];
```

---

## 4. Acceptance tests (manual, Henry chạy tuần tự)

1. **Bootstrap:** `copy module\deutsch_web\config.example.php module\deutsch_web\config.php` → dán `api_key` ngẫu nhiên dài.
2. **Migrate:** `C:\php\php74\php.exe module\deutsch_web\scripts\migrate.php` → tạo `data/deutsch_web.sqlite`, in `users + events created`. Chạy lại → idempotent, không lỗi.
3. **Seed user:** `C:\php\php74\php.exe module\deutsch_web\scripts\migrate.php --add-user henry` → hỏi password (hoặc arg) → insert row `users`, password đã `password_hash()`.
4. **Serve local:** `C:\php\php74\php.exe -S localhost:8080 -t module/deutsch_web/public` → mở `http://localhost:8080` → redirect `/login`.
5. **Login:** sai pass → báo lỗi, không vào. Đúng → vào `/` danh sách bài, thấy 4.29 / 4.30 / 4.31.
6. **Drill render:** mở `/lesson/4.29` → layout **khớp prototype** (audio sticky play được từ URL S3, radio a–f, panel Vokabeln mở ra bên phải với 11 từ, highlight từ trong đề/transcript).
7. **Chấm điểm:** chọn d/b/e → Prüfen → 3/3 perfect; mở DB (`sqlite3 ... "SELECT type,lesson_id,payload FROM events"`) thấy 1 row `horen_complete`.
8. **Click từ:** mở panel → click "unersetzlich" toggle sang hard → 1 row `word_mark` trong DB, payload có `word_status:"hard"` + `context`.
9. **API events:** `curl -H "Authorization: Bearer <key>" "http://localhost:8080/api/events?since=2026-05-29T00:00:00Z"` → JSON 2 event trên, `synced_at:null`.
10. **API auth fail:** bỏ header / sai token → HTTP 401, không lộ data.
11. **Pending words:** `curl -H "Authorization: Bearer <key>" http://localhost:8080/api/unknown_words/pending` → 1 từ.
12. **Ack:** `curl -X POST -H "Authorization: Bearer <key>" -d '{"event_ids":["<id>"]}' http://localhost:8080/api/events/ack` → `{"acked":1}`; query lại events thấy `synced_at` đã set, **row vẫn còn** (không xóa).
13. **Vocab API:** `GET /api/lessons/4.29/vocab` → trả mảng vocab từ JSON (Bearer hoặc session đều OK — quyết định trong README).
14. **Mobile check:** mở localhost từ điện thoại cùng LAN (hoặc DevTools responsive) → drill responsive, panel vocab dùng được.

---

## 5. Cấm đụng

- ❌ **Dự án mieu** (`C:\twv_share\app\code\mieu`) — chỉ đọc tham khảo pattern auth/session. KHÔNG import file, KHÔNG ghép DB, KHÔNG sửa bất cứ gì trong đó.
- ❌ `data/03_unified/vocab_master.csv`, `data/weak_words.csv`, `data/chunks_master.csv` — web KHÔNG ghi trực tiếp. Chỉ queue qua bảng `events`.
- ❌ `module/lingq_sync/` — KHÔNG gọi, KHÔNG sửa. Push LingQ là việc Cowork sau.
- ❌ GitHub API từ PHP web — web ghi SQLite, Cowork lo git/GitHub sau.
- ❌ Sửa **schema** `lessons/4.29.json` (Cowork đã chốt `deutsch_web_lesson_v1`). Được TẠO 4.30/4.31 cùng schema.
- ❌ Hardcode `api_key` / password — đọc từ `config.php` (trong `.gitignore`).
- ❌ Upload MP3 220 MB lên server (DD-20260529-006 #3 — dùng URL S3).
- ❌ Framework / Composer / vendor — pure PHP 7.4 stdlib + PDO SQLite.
- ❌ `git add / commit / push` — Edit xong báo "edit xong, chờ review trong Cursor".
- ❌ `php -l` local để verify (theo CLAUDE.md user — hay treo).
- ❌ Multi-user / tutor view / DB động vocab POST — Phase 2+, ngoài scope.
- ❌ Bịa từ / nghĩa / transcript — mọi nội dung lesson lấy từ file nguồn thật trong `input/html/.../horen/<bai>/` hoặc prototype.

---

## 6. Performance / scale

- **Hiện tại:** 1 user, 3 bài pilot. Mục tiêu mở rộng 344 bài Hören (Phase 5 batch).
- **SQLite:** đủ cho 1 user × hàng nghìn event. WAL mode (`PRAGMA journal_mode=WAL`) để đọc/ghi mượt khi cron pull lúc đang học.
- **Lesson loader:** đọc JSON theo từng request, cache OPcache tự lo. 344 bài = 344 file JSON nhỏ, không load hết 1 lần — danh sách đọc index CSV, drill đọc 1 file.
- **API `events?since=`:** index `idx_events_created` → query nhanh. Trả tối đa N (vd 500) / lần, có `next_since` nếu cần phân trang sau.
- **Ack:** update theo `event_id` (PK). Append-only, không xóa → audit được.
- **Audio:** stream thẳng từ LingQ S3 → 0 tải băng thông twv.app.
- **Bảo mật tối thiểu:** `/api/*` Bearer token; route web qua session; `password_hash`/`password_verify`; PDO **prepared statement** mọi query (CẤM nối chuỗi SQL từ input — kể cả lesson_id route param phải bind).

---

## 7. Format report (Claude Code in cuối session)

```
✅ Deutsch Web Phase 1 — done

Files created:
- module/deutsch_web/public/index.php              (XX dòng)
- module/deutsch_web/public/.htaccess              (XX dòng)
- module/deutsch_web/public/assets/drill.css       (XX dòng — tách từ prototype)
- module/deutsch_web/public/assets/drill.js        (XX dòng — bỏ vocabData hardcode)
- module/deutsch_web/api/events.php                (XX dòng)
- module/deutsch_web/api/unknown_words.php         (XX dòng)
- module/deutsch_web/api/lessons.php               (XX dòng)
- module/deutsch_web/views/{login,lesson_list,drill_horen}.php
- module/deutsch_web/lib/{db,auth,api_auth,lesson_loader}.php
- module/deutsch_web/migrations/001_init.sql
- module/deutsch_web/scripts/migrate.php
- module/deutsch_web/lessons/{4.30,4.31}.json       (4.29.json đã có sẵn)
- module/deutsch_web/config.example.php
- module/deutsch_web/.gitignore
- module/deutsch_web/README.md

Verified (KHÔNG php -l):
- [x] router + .htaccess rewrite
- [x] PDO SQLite prepared statement mọi query
- [x] drill render khớp prototype, vocab load từ JSON (không hardcode)
- [x] Bearer auth /api/*, session auth web
- [x] ack set synced_at, không xóa row
- [ ] live trên twv.app — cần Henry deploy + test mobile

To activate (cho Henry):
1) copy config.example.php config.php → dán api_key
2) C:\php\php74\php.exe module\deutsch_web\scripts\migrate.php
3) C:\php\php74\php.exe module\deutsch_web\scripts\migrate.php --add-user henry
4) Test local: C:\php\php74\php.exe -S localhost:8080 -t module/deutsch_web/public
5) Deploy: rsync/upload module/deutsch_web → docroot deutsch.twv.app, trỏ web root vào public/
6) Cron pull (Phase sau): module/deutsch_web_sync/pull_events.php

Lock cleared: .ai-locks/deutsch_web_impl.lock removed.
Pending: Cursor diff review (KHÔNG tự commit/push).
```

---

## Phụ lục — Phase 0 (làm TRƯỚC, trong cùng task)

**Mục tiêu:** tách prototype `output/drills/horen_test_4.29-4.31.html` thành asset tái dùng + lesson JSON, để Phase 1 chỉ việc serve.

1. **`public/assets/drill.css`** = nguyên khối `<style>` (dòng 7–209 prototype). Giữ nguyên class, biến màu, responsive. Không đổi tên class (`.vocab-panel`, `.aussage-block`, `lv-new/ok/hard`...).
2. **`public/assets/drill.js`** = phần `<script>` (dòng 486–857) nhưng:
   - **Bỏ** biến `vocabData` hardcode. Thay bằng đọc từ `window.LESSON` (PHP inject) hoặc `fetch('/api/lessons/{id}/vocab')`.
   - Hàm `switchLesson` đơn-bài: Phase 1 mỗi trang 1 bài (`/lesson/{id}`) thay vì 3 tab. Giữ logic player / check / transcript / vocab panel / inject marks y nguyên, chỉ tham số hóa theo 1 lesson object.
   - **Sửa bug regex** dòng 820 prototype: `'(?<![\w...])'` trong chuỗi JS bị nuốt `\w` → phải `'(?<![\\w\\u00c0-\\u017e])...'` (double-escape). Kiểm lại highlight chạy đúng.
3. **`views/drill_horen.php`** = khung HTML từ prototype (1 lesson) + `<?= json_encode($lesson) ?>` vào `window.LESSON`, link `assets/drill.css` + `assets/drill.js`. Render aussagen/options/transcript từ `$lesson` (server-side) thay vì hardcode.
4. **`lessons/4.30.json`, `lessons/4.31.json`**: sinh từ prototype (lesson-1, lesson-2 + `vocabData[1]/[2]` + audio src tương ứng), cùng schema `deutsch_web_lesson_v1`. `vocab_id: null`, KHÔNG bịa.
5. `lessons/4.29.json` **đã có** (Cowork sinh) — dùng làm khuôn, KHÔNG sửa.

**Nguồn nội dung 4.30/4.31** (nếu cần verify ngoài prototype): `input/html/deutsch-vorbereitung/horen/4.30/` và `/4.31/` (questions/transcript/url md).

---

## Tham chiếu nhanh

| File | Vai trò |
|---|---|
| `output/drills/horen_test_4.29-4.31.html` | Prototype UI vàng (tách Phase 0) |
| `module/deutsch_web/lessons/4.29.json` | Lesson JSON canonical (Cowork sinh) |
| `input/html/deutsch-vorbereitung/horen_lessons.csv` | Index 344 bài (cột stt,bai,chu_de,url,sheet) |
| `output/drills/horen_progress.csv` | Schema progress cũ (web thay thế) |
| `module/lingq_sync/README.md` | Pattern module PHP + cron (tham khảo, KHÔNG đụng) |
| `docs/ai/DECISIONS.md` DD-20260529-006 | 7 quyết định nền tảng |
