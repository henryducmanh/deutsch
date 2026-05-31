-- 004_tutor.sql — Tutor Phase 1: cột role vào users + bảng tutor_students + tutor_notes.
-- Ref: docs/ai/tasks/DEUTSCH_WEB_TUTOR_PHASE1_PROMPT.md §3 + DD-20260529-006.
--
-- MySQL 5.7 KHÔNG hỗ trợ ALTER TABLE ADD COLUMN IF NOT EXISTS.
-- → idempotent bằng cách check information_schema rồi PREPARE/EXECUTE DDL động (giống 003_lemma.sql).
-- Mỗi câu kết bằng ';' (dw_split_sql tách theo ';'). KHÔNG để ';' bên trong chuỗi DDL.
-- Biến @ session-scope giữ qua các câu trong cùng connection (dw_migrate dùng 1 PDO).

-- ── Cột role vào users (student|tutor|admin) ──
SET @c_role := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role');
SET @ddl_role := IF(@c_role = 0,
  'ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT ''student'' COMMENT ''student|tutor|admin''',
  'SET @noop := 1');
PREPARE st_role FROM @ddl_role;
EXECUTE st_role;
DEALLOCATE PREPARE st_role;

-- ── Bảng gán gia sư ↔ học viên ──
CREATE TABLE IF NOT EXISTS tutor_students (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  tutor_id   INT NOT NULL,
  student_id INT NOT NULL,
  status     VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pair (tutor_id, student_id),
  INDEX idx_tutor (tutor_id),
  CONSTRAINT fk_ts_tutor   FOREIGN KEY (tutor_id)   REFERENCES users(id),
  CONSTRAINT fk_ts_student FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Bảng ghi chú buổi học (1 note per student+lesson+date, nhiều tutor cùng sửa) ──
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
  CONSTRAINT fk_tn_student FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
