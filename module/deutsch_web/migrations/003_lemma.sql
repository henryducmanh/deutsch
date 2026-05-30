-- 003_lemma.sql — Thêm parent_id + form_type cho quan hệ lemma → biến thể (inflected forms).
-- Ref: docs/ai/DECISIONS.md DD-20260527-005 + docs/ai/tasks/DEUTSCH_WEB_LEMMA_PROMPT.md §4.
--
-- MySQL 5.7 KHÔNG hỗ trợ ALTER TABLE ADD COLUMN/INDEX IF NOT EXISTS.
-- → idempotent bằng cách check information_schema rồi PREPARE/EXECUTE DDL động.
-- Mỗi câu kết bằng ';' (dw_split_sql tách theo ';'). KHÔNG để ';' bên trong chuỗi DDL.
-- Biến @ session-scope giữ qua các câu trong cùng connection (dw_migrate dùng 1 PDO).

-- ── Cột parent_id (FK logic → vocab.id của lemma; NULL = chính là lemma) ──
SET @c_parent := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vocab' AND COLUMN_NAME = 'parent_id');
SET @ddl_parent := IF(@c_parent = 0,
  'ALTER TABLE vocab ADD COLUMN parent_id INT NULL DEFAULT NULL COMMENT ''vocab.id cua lemma. NULL = chinh la lemma''',
  'SET @noop := 1');
PREPARE st_parent FROM @ddl_parent;
EXECUTE st_parent;
DEALLOCATE PREPARE st_parent;

-- ── Cột form_type (mã biến cách) ──
SET @c_ftype := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vocab' AND COLUMN_NAME = 'form_type');
SET @ddl_ftype := IF(@c_ftype = 0,
  'ALTER TABLE vocab ADD COLUMN form_type VARCHAR(20) NULL DEFAULT NULL COMMENT ''NOM.PL/GEN.SG... (Subst.), PRAET/PERF/PART.II/KONJ.II (Verb), KOMP/SUP (Adj.)''',
  'SET @noop := 1');
PREPARE st_ftype FROM @ddl_ftype;
EXECUTE st_ftype;
DEALLOCATE PREPARE st_ftype;

-- ── Index idx_parent_id (tìm nhanh tất cả biến thể của 1 lemma) ──
SET @i_parent := (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vocab' AND INDEX_NAME = 'idx_parent_id');
SET @ddl_idx := IF(@i_parent = 0,
  'ALTER TABLE vocab ADD INDEX idx_parent_id (parent_id)',
  'SET @noop := 1');
PREPARE st_idx FROM @ddl_idx;
EXECUTE st_idx;
DEALLOCATE PREPARE st_idx;
