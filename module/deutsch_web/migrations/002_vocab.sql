-- 002_vocab.sql — bảng vocab cho deutsch_web (Phase 1-3 vocab feature).
-- MySQL 5.7 InnoDB utf8mb4. Idempotent (IF NOT EXISTS) → chạy lại an toàn qua scripts/migrate.php.
-- Nguồn: push_vocab.php đẩy từ data/03_unified/vocab_master.csv (curated=1).
-- Web add (tab "Neu wort") ghi row curated=0 → pull_vocab.php kéo về staging.

CREATE TABLE IF NOT EXISTS vocab (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  vocab_id    VARCHAR(64)  NULL,                 -- vd 'VOC-20260518-001' từ CSV (NULL nếu web-add)
  wort        VARCHAR(200) NOT NULL,
  wort_key    VARCHAR(200) NOT NULL,             -- lowercase(wort) — khóa dedup
  wortart     VARCHAR(50)  NULL,
  artikel     VARCHAR(10)  NULL,                 -- der/die/das (parse từ formen)
  bedeutung   TEXT         NULL,
  niveau      VARCHAR(10)  NULL DEFAULT 'B1',
  level       TINYINT      NOT NULL DEFAULT 1,   -- 1-4 (panel lv-new/ok/hard)
  thema       VARCHAR(100) NULL,
  tags        VARCHAR(500) NULL,
  source      VARCHAR(200) NULL,                 -- lesson_id khi web-add, NULL khi từ CSV
  curated     TINYINT      NOT NULL DEFAULT 1,   -- 1 = từ vocab_master, 0 = web-add chờ curate
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wort_key (wort_key),
  INDEX idx_vocab_id (vocab_id),
  INDEX idx_curated_created (curated, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
