# Deutsch Web — Tutor Addendum: Impersonation Mode

> **Task ID:** `deutsch_web_tutor_addendum`
> **Extends:** `DEUTSCH_WEB_TUTOR_PHASE1_PROMPT.md` (đã làm xong)
> **Lock:** Tạo `.ai-locks/tutor_addendum.lock` đầu task, xóa cuối task.
>
> **Paste 1 dòng cho Claude Code:** `Đọc docs/ai/tasks/DEUTSCH_WEB_TUTOR_ADDENDUM_PROMPT.md và làm theo. Tạo lock .ai-locks/tutor_addendum.lock. Báo "edit xong, chờ review Cursor".`

---

## Yêu cầu thay đổi

Gia sư **KHÔNG cần giao diện riêng**. Khi gia sư chọn học viên → web hiển thị **y chang như học viên đó đang login**: cùng bài, cùng vocab đánh dấu, cùng điểm, cùng nút "📝 Notizen" mở note chung. Gia sư share màn hình qua Zoom → học viên thấy màn hình của gia sư như đang nhìn chính màn hình mình.

### Cơ chế: Session Impersonation

```
Tutor login → /tutor → danh sách học viên → click "Học cùng Henry"
→ $_SESSION['view_student_id'] = henry_id
→ redirect /
→ toàn bộ web (lesson_list, drill, vocab, events, note) dùng view_student_id thay uid
→ banner nhỏ trên đầu: "👁 Đang xem: Henry  [← Thoát dashboard]"
```

---

## Các thay đổi cụ thể

### 1. `lib/auth.php` — thêm helper `auth_active_student_id()`

```php
/**
 * Trả về student_id đang được xem.
 * - Nếu tutor đang impersonate: $_SESSION['view_student_id']
 * - Ngược lại: auth_user_id() (chính mình)
 */
function auth_active_student_id()
{
    auth_session_start();
    if (isset($_SESSION['view_student_id']) && auth_role() === 'tutor') {
        return (int)$_SESSION['view_student_id'];
    }
    return auth_user_id();
}

function auth_role()
{
    auth_session_start();
    return $_SESSION['role'] ?? 'student';
}

function auth_is_tutor_viewing()
{
    auth_session_start();
    return auth_role() === 'tutor' && isset($_SESSION['view_student_id']);
}
```

Cập nhật `auth_attempt()`: sau khi verify password, thêm `$_SESSION['role'] = $row['role'];`

### 2. `public/index.php` — cập nhật routes

**Route `/tutor` (tutor dashboard):**
```php
case $path === '/tutor':
    auth_require();
    if (auth_role() !== 'tutor') { header('Location: /'); exit; }
    // Lấy danh sách học viên được gán cho tutor này
    $students = tutor_get_students(auth_user_id());
    require $BASE . '/views/tutor_dashboard.php';
    exit;
```

**Route mới `/tutor/select/{student_id}`:**
```php
case preg_match('#^/tutor/select/(\d+)$#', $path, $m) === 1:
    auth_require();
    if (auth_role() !== 'tutor') { header('Location: /'); exit; }
    // Verify tutor có quyền với student này
    if (!tutor_can_view_student(auth_user_id(), (int)$m[1])) {
        http_response_code(403); echo 'Không có quyền.'; exit;
    }
    auth_session_start();
    $_SESSION['view_student_id'] = (int)$m[1];
    header('Location: /');
    exit;
```

**Route mới `/tutor/exit`:**
```php
case $path === '/tutor/exit':
    auth_require();
    auth_session_start();
    unset($_SESSION['view_student_id']);
    header('Location: /tutor');
    exit;
```

**Route `/` — khi tutor đang impersonate:**
```php
case $path === '/':
    auth_require();
    if (auth_role() === 'tutor' && !isset($_SESSION['view_student_id'])) {
        header('Location: /tutor'); exit;  // tutor chưa chọn học viên → về dashboard
    }
    $lessons = lesson_list(auth_active_student_id());  // <-- đổi từ auth_user_id()
    $uname = $_SESSION['uname'] ?? '';
    require $BASE . '/views/lesson_list.php';
    exit;
```

### 3. Cập nhật tất cả `auth_user_id()` → `auth_active_student_id()` trong các file sau

Chỉ thay ở chỗ **liên quan đến data của học viên** (vocab, events, progress, note):

| File | Hàm/dòng cần đổi |
|---|---|
| `api/vocab.php` | `auth_user_id()` trong query SELECT/INSERT vocab level |
| `api/events.php` | `auth_user_id()` trong INSERT events (dùng active student khi tutor track progress) |
| `api/unknown_words.php` | `auth_user_id()` trong query |
| `api/lessons.php` | `auth_user_id()` trong vocab query |
| `api/notes.php` | default `student_id` khi không có param |
| `public/index.php` | route `/track` — event ghi theo active_student_id |

**KHÔNG đổi** `auth_user_id()` ở những chỗ liên quan đến **identity của người đang login** (security check, log ai đã login, tutor_id trong tutor_notes).

### 4. `views/tutor_dashboard.php` — đơn giản hoá

Chỉ cần:
```
Trang: /tutor
─────────────────────────────
Xin chào, kieulinh!

Học viên của bạn:
┌─────────────────────────────────────────┐
│  👤 Henry  (A2-B1)   [▶ Học cùng Henry] │
└─────────────────────────────────────────┘
```

Nút "Học cùng Henry" → href="/tutor/select/{henry_id}"

Helper cần thêm vào `lib/auth.php` hoặc `lib/lesson_loader.php`:
```php
function tutor_get_students($tutor_id) {
    $st = db()->prepare('
        SELECT u.id, u.username
        FROM tutor_students ts
        JOIN users u ON u.id = ts.student_id
        WHERE ts.tutor_id = ? AND ts.status = "active"
    ');
    $st->execute([$tutor_id]);
    return $st->fetchAll();
}

function tutor_can_view_student($tutor_id, $student_id) {
    $st = db()->prepare('SELECT 1 FROM tutor_students WHERE tutor_id=? AND student_id=? AND status="active"');
    $st->execute([$tutor_id, $student_id]);
    return (bool)$st->fetch();
}
```

### 5. Banner impersonation — shared partial `views/_tutor_banner.php` (file mới)

```php
<?php // _tutor_banner.php — include ở đầu lesson_list.php và drill_horen.php
if (auth_is_tutor_viewing()):
    $vuid = $_SESSION['view_student_id'];
    $st = db()->prepare('SELECT username FROM users WHERE id=? LIMIT 1');
    $st->execute([$vuid]);
    $vname = $st->fetchColumn() ?: '?';
?>
<div style="background:#1a1a2e;color:#fff;padding:8px 16px;font-size:13px;display:flex;align-items:center;gap:16px;">
  <span>👁 Đang xem: <strong><?= htmlspecialchars($vname) ?></strong></span>
  <a href="/tutor/exit" style="color:#ff9f43;font-size:12px;">← Thoát dashboard</a>
</div>
<?php endif; ?>
```

Include ở đầu `<body>` trong `views/lesson_list.php` và `views/drill_horen.php`:
```php
require __DIR__ . '/_tutor_banner.php';
```

### 6. `api/notes.php` — student_id fallback

Khi GET/POST không truyền `student_id` → dùng `auth_active_student_id()` làm default (thay vì `auth_user_id()`).

---

## Acceptance tests (addendum)

**Test A — Tutor login → dashboard**
```
Login kieulinh → Location: /tutor → thấy "Henry" + nút "Học cùng Henry"
```

**Test B — Impersonation redirect**
```
Click "Học cùng Henry"
→ GET /tutor/select/{henry_id}
→ $_SESSION['view_student_id'] set
→ redirect /
→ lesson_list hiển thị bài Hören của Henry
→ banner vàng "👁 Đang xem: henry | ← Thoát dashboard" ở trên cùng
```

**Test C — Giống hệt học viên**
```
Mở /lesson/4.29 với session tutor (đang impersonate henry)
→ giao diện y chang henry login: cùng vocab đánh dấu, cùng 0/3 score, cùng audio
→ nút "📝 Notizen" xuất hiện (link đến note của henry + lesson 4.29)
→ banner "👁 Đang xem: henry" hiển thị ở trên
```

**Test D — Note chung**
```
Tutor mở "📝 Notizen" từ bài 4.29 → note editor load với student_id = henry_id
Henry mở cùng URL → thấy cùng note
Tutor gõ → 3s → Henry thấy cập nhật
```

**Test E — Thoát về dashboard**
```
Click "← Thoát dashboard" → GET /tutor/exit → unset view_student_id → redirect /tutor
→ /tutor hiển thị lại danh sách học viên
→ /tutor/exit với session student → redirect / (không lỗi)
```

**Test F — Security**
```
POST /api/notes với session tutor (impersonate henry) → note lưu student_id = henry_id (không phải tutor_id)
GET /tutor/select/{wife_id} với session tutor (wife chưa được gán) → 403
```

---

## Cấm đụng (giữ nguyên từ Phase 1)

- KHÔNG sửa lesson JSON, lingq_sync, deutsch_web_sync, scan_extract
- KHÔNG đổi `auth_user_id()` ở chỗ security/identity (chỉ đổi chỗ data query)
- KHÔNG cài Composer

---

## Format report

```
=== TUTOR ADDENDUM DONE ===

Files TẠO MỚI:
- views/_tutor_banner.php

Files SỬA:
- lib/auth.php (thêm auth_active_student_id, auth_role, auth_is_tutor_viewing; update auth_attempt)
- public/index.php (routes /tutor/select, /tutor/exit; đổi / route; đổi auth_user_id → auth_active_student_id)
- views/tutor_dashboard.php (đơn giản hoá)
- views/lesson_list.php (include banner)
- views/drill_horen.php (include banner)
- api/vocab.php (auth_user_id → auth_active_student_id)
- api/events.php (auth_user_id → auth_active_student_id)
- api/unknown_words.php (auth_user_id → auth_active_student_id)
- api/lessons.php (auth_user_id → auth_active_student_id)
- api/notes.php (default student_id → auth_active_student_id)

Acceptance test:
  Test A (dashboard): PASS
  Test B (impersonation): PASS
  Test C (giống học viên): PASS
  Test D (note chung): PASS
  Test E (thoát): PASS
  Test F (security): PASS

Lock xóa: .ai-locks/tutor_addendum.lock
```

---

**Last updated:** 2026-05-31 (v1.0 — Module Engineer addendum sau feedback gia sư share màn hình)
