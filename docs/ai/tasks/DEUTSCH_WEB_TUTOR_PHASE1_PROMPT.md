# Deutsch Web — Tutor Phase 1 (Role + Tutor Dashboard + Collaborative Note Editor)

> **Task ID:** `deutsch_web_tutor_phase1`
> **Người triển khai:** Claude Code
> **Stack:** PHP 7.4, MySQL 5.7 (`apptwv_deutsch`), no framework, no Composer. Deploy `https://deutsch.twv.app`.
> **Ref:** `brainstorm/tutor-cowork-learning-loop/README.md` · `docs/ai/DECISIONS.md` DD-20260529-006
> **Lock:** Tạo `.ai-locks/tutor_phase1.lock` đầu task, xóa cuối task.
>
> **Paste 1 dòng cho Claude Code:** `Đọc docs/ai/tasks/DEUTSCH_WEB_TUTOR_PHASE1_PROMPT.md và làm theo. Tạo lock .ai-locks/tutor_phase1.lock. KHÔNG tự chạy live --apply. Báo "edit xong, chờ review Cursor".`

---

## 1. End-user

**2 gia sư** (role=`tutor`) mỗi người có username + password riêng → login vào `deutsch.twv.app` → redirect tới `/tutor` → thấy danh sách học viên được gán → hiện tại chỉ **Henry** (role=`student`) → chọn bài Hören đang học → mở note editor → ghi chú, giải nghĩa từ, phân tích lỗi **trong khi dạy qua Zoom**.

**Henry** (student) → login bình thường → mở bài Hören → thấy nút **"📝 Notizen"** → click → mở cùng trang note của buổi đó → **cả hai sửa cùng lúc, tự đồng bộ mỗi 3 giây** (không cần reload).

Vợ Henry (A1-A2) là student thứ 2 trong DB nhưng **chưa được gán cho gia sư nào** → gia sư không thấy.

---

## 2. Màn cuối cùng (definition of done)

### Files tạo mới

```
module/deutsch_web/
├── migrations/
│   └── 004_tutor.sql              ← idempotent (IF NOT EXISTS + information_schema check)
├── api/
│   └── notes.php                  ← GET + POST /api/notes (session auth)
├── views/
│   ├── tutor_dashboard.php        ← dashboard gia sư (danh sách student + lesson)
│   └── tutor_note.php             ← Quill editor + polling realtime
└── public/assets/
    └── tutor_note.js              ← Quill init, auto-save debounce 1s, poll 3s
```

### Files sửa

```
lib/auth.php          ← thêm role vào session + helpers auth_role(), auth_require_role()
public/index.php      ← thêm routes /tutor, /tutor/note, /api/notes (GET+POST)
views/drill_horen.php ← thêm nút "📝 Notizen" trong tab bar (1-2 dòng)
```

### Behavior sau khi xong

- Tutor login → tự redirect `/tutor` (không về `/`)
- Tutor vào `/` hoặc `/lesson/X` → redirect `/tutor`
- Student vào `/tutor` → redirect `/`
- `/tutor/note` → accessible cả tutor lẫn student (collaboration page)
- Không có tutor nào → login bình thường → về `/` như cũ

---

## 3. Data thật

### Schema migration 004_tutor.sql

```sql
-- Cột role vào users (idempotent MySQL 5.7 style)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role');
SET @ddl := IF(@c = 0,
  "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'student' COMMENT 'student|tutor|admin'",
  'SET @noop := 1');
PREPARE st FROM @ddl; EXECUTE st; DEALLOCATE PREPARE st;

-- Bảng gán gia sư ↔ học viên
CREATE TABLE IF NOT EXISTS tutor_students (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  tutor_id   INT NOT NULL,
  student_id INT NOT NULL,
  status     VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pair (tutor_id, student_id),
  FOREIGN KEY (tutor_id)   REFERENCES users(id),
  FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng ghi chú buổi học (1 note per student+lesson+date, nhiều tutor cùng sửa)
CREATE TABLE IF NOT EXISTS tutor_notes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  student_id   INT NOT NULL,
  lesson_id    VARCHAR(16) NULL,
  session_date DATE NOT NULL,
  content      MEDIUMTEXT NULL,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_note (student_id, lesson_id, session_date),
  INDEX idx_student_date (student_id, session_date),
  FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### SQL setup sau khi migrate (user chạy tay qua phpMyAdmin)

```sql
-- Tạo 2 gia sư (password_hash = password_hash('matkhau', PASSWORD_BCRYPT) — thay bằng hash thật)
INSERT INTO users (username, password_hash, role) VALUES
  ('tutor1', '$2y$10$PLACEHOLDER_HASH_1', 'tutor'),
  ('tutor2', '$2y$10$PLACEHOLDER_HASH_2', 'tutor');

-- Gán cả 2 gia sư cho Henry (giả sử Henry id=1 — kiểm tra SELECT id FROM users WHERE username='henry')
INSERT INTO tutor_students (tutor_id, student_id) VALUES
  ((SELECT id FROM users WHERE username='tutor1'), (SELECT id FROM users WHERE username='henry')),
  ((SELECT id FROM users WHERE username='tutor2'), (SELECT id FROM users WHERE username='henry'));
```

> **Lưu ý cho Claude Code:** KHÔNG tự INSERT user thật. Chỉ tạo file migration SQL + hướng dẫn setup trong phần report.

### API data flow

```
GET /api/notes?lesson_id=4.29&student_id=1&date=2026-05-31
→ {
    "note_id": 1,
    "lesson_id": "4.29",
    "student_id": 1,
    "session_date": "2026-05-31",
    "content": "<h2>Digitalisierung</h2><ul><li>Aussage 1 → d</li></ul>",
    "updated_at": "2026-05-31T10:05:23Z"
  }

POST /api/notes
body: {"lesson_id":"4.29","student_id":1,"date":"2026-05-31","content":"<p>...</p>"}
→ {"ok": true, "note_id": 1, "updated_at": "2026-05-31T10:05:24Z"}
```

---

## 4. Acceptance tests (theo thứ tự)

**Test 1 — Migration idempotent**
```
php scripts/migrate.php
→ chạy lại lần 2 không lỗi
→ SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='role'
→ phải trả về 1 row
→ SHOW TABLES LIKE 'tutor_students' → 1 row
→ SHOW TABLES LIKE 'tutor_notes' → 1 row
```

**Test 2 — Tutor login redirect**
```
SQL: UPDATE users SET role='tutor' WHERE username='henry_test_tutor'; (user thử nghiệm)
SQL: INSERT INTO tutor_students (tutor_id, student_id) VALUES (X, henry_id);
→ login với tutor → Location header = /tutor
→ /tutor hiển thị "Henry" trong danh sách học viên
→ vào / → tự redirect /tutor
```

**Test 3 — Tutor mở note**
```
→ /tutor → click Henry → chọn lesson 4.29, date hôm nay → Submit
→ redirect /tutor/note?lesson_id=4.29&student_id=1&date=YYYY-MM-DD
→ trang load Quill editor, toolbar hiển thị đủ: Bold/Italic/Underline/Highlight/List/Blockquote/H2
→ editor trống (note chưa có)
```

**Test 4 — Auto-save + GET**
```
→ gõ "**Lỗi Akkusativ**" trong Quill → bôi đậm "Lỗi Akkusativ" → đợi 1.2s
→ Network tab: thấy POST /api/notes → 200 → response có updated_at
→ Mở tab mới: GET /api/notes?... → content chứa <strong>Lỗi Akkusativ</strong>
```

**Test 5 — Student thấy nút Notizen trong drill**
```
→ login henry (role=student) → /lesson/4.29
→ thấy "📝 Notizen" trong tab bar (hoặc sticky header)
→ click → redirect /tutor/note?lesson_id=4.29&student_id=1&date=today
→ Quill load → content từ Test 4 hiển thị
```

**Test 6 — Realtime sync (2 tab)**
```
→ Tab A: /tutor/note (tutor) — nội dung từ Test 4
→ Tab B: /tutor/note (student henry) — cùng URL
→ Trong Tab A: thêm "Ghi chú thêm" → đợi 1s auto-save
→ Trong Tab B: đợi ≤ 3s → poll → Quill cập nhật tự động (không reload trang)
```

**Test 7 — Isolation role**
```
→ Student henry truy cập /tutor → redirect /
→ Người không đăng nhập truy cập /tutor/note → redirect /login
→ POST /api/notes không có session → 401
```

---

## 5. Cấm đụng

- **KHÔNG** sửa bất kỳ file trong `module/lingq_sync/`, `module/deutsch_web_sync/`, `module/scan_extract/`
- **KHÔNG** sửa lesson JSON files trong `module/deutsch_web/lessons/`
- **KHÔNG** sửa logic vocab (api/vocab.php, api/lessons.php, api/unknown_words.php)
- **KHÔNG** sửa schema bảng `events` hoặc `vocab`
- **KHÔNG** cài Composer package — chỉ dùng CDN cho Quill.js
- **KHÔNG** implement Quill table plugin — quá phức tạp, dùng list + blockquote thay thế
- **KHÔNG** tự chạy SQL INSERT user thật — chỉ ghi hướng dẫn trong report

---

## 6. Performance / scale

- **Người dùng đồng thời:** ≤ 4 (2 gia sư + Henry + vợ) → shared cPanel hosting OK
- **Polling interval:** 3000ms — acceptable cho note-taking; không cần WebSocket
- **Debounce auto-save:** 1000ms sau lần gõ cuối → tránh flood POST
- **Conflict:** last-write-wins (đơn giản, chấp nhận được với ≤ 2 người cùng sửa 1 note)
- **Không gõ:** nếu user đang gõ (hasFocus + lastKeyTime < 2s) → KHÔNG overwrite Quill khi poll trả nội dung mới → chờ user ngừng gõ 2s mới sync
- **Content size:** MEDIUMTEXT = 16MB → đủ cho notes dài nhiều buổi
- **Note key:** UNIQUE (student_id, lesson_id, session_date) → upsert an toàn với INSERT ... ON DUPLICATE KEY UPDATE

---

## 7. Format report Claude Code in cuối

```
=== TUTOR PHASE 1 DONE ===

Files TẠO MỚI:
- migrations/004_tutor.sql
- api/notes.php
- views/tutor_dashboard.php
- views/tutor_note.php
- public/assets/tutor_note.js

Files SỬA:
- lib/auth.php (thêm role + helpers)
- public/index.php (thêm 4 routes)
- views/drill_horen.php (thêm nút Notizen)

Migration: 004_tutor.sql — idempotent, chạy qua scripts/migrate.php
Setup sau migrate (user tự chạy SQL):
  [copy-paste SQL tạo tutor + assign — KHÔNG hash thật, user thay]

Acceptance test:
  Test 1 (migration): PASS / code OK — chưa chạy thật, verify sau migrate
  Test 2 (tutor login): PASS
  Test 3 (note load): PASS
  Test 4 (auto-save): PASS
  Test 5 (student Notizen): PASS
  Test 6 (realtime poll): PASS
  Test 7 (role isolation): PASS

KHÔNG đụng:
  ✓ lingq_sync, deutsch_web_sync, scan_extract
  ✓ lesson JSON files
  ✓ vocab/events schema
  ✓ Composer

Lock xóa: .ai-locks/tutor_phase1.lock ← xóa file này
```

---

**Lưu ý triển khai Quill.js:**

```html
<!-- CDN Quill 1.3.7 — không cần Composer, không cần npm -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
```

Toolbar config:
```js
var quill = new Quill('#editor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ 'header': [2, 3, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      [{ 'color': [] }, { 'background': [] }],  // highlight = background color
      [{ 'list': 'ordered'}, { 'list': 'bullet' }],
      [{ 'indent': '-1'}, { 'indent': '+1' }],
      ['blockquote', 'link'],
      ['clean']
    ]
  }
});
```

**Lưu ý polling:**

```js
// tutor_note.js — cấu trúc chính
var lastUpdatedAt = null;
var lastKeyTime = 0;
var saveTimer = null;

quill.on('text-change', function() {
  lastKeyTime = Date.now();
  clearTimeout(saveTimer);
  saveTimer = setTimeout(saveNote, 1000);  // debounce 1s
});

setInterval(function pollNote() {
  fetch('/api/notes?' + params)
    .then(r => r.json())
    .then(data => {
      if (data.updated_at === lastUpdatedAt) return;  // không đổi → skip
      var sinceKey = Date.now() - lastKeyTime;
      if (sinceKey < 2000) return;  // đang gõ → skip
      // update Quill
      var range = quill.getSelection();
      quill.setContents(quill.clipboard.convert(data.content));
      if (range) quill.setSelection(range);
      lastUpdatedAt = data.updated_at;
    });
}, 3000);
```

---

**Last updated:** 2026-05-31 (v1.0 — Lesson Planner / Module Engineer — Tutor Phase 1)
