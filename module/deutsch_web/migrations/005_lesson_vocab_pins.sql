-- 005_lesson_vocab_pins.sql — pin từ "Trong kho" (đã học bài khác) vào "Đang ôn" của 1 bài.
-- MySQL 5.7 InnoDB utf8mb4. Idempotent (IF NOT EXISTS) → chạy lại an toàn qua scripts/migrate.php.
-- 1 row = 1 từ (vocab.id) được ghim vào 1 lesson_id cho 1 user. Persist sau reload.
-- ON DELETE CASCADE theo vocab.id: xoá vocab → xoá mọi pin của từ đó.

CREATE TABLE IF NOT EXISTS lesson_vocab_pins (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id   INT         NOT NULL,
  lesson_id VARCHAR(16) NOT NULL,
  vocab_id  INT         NOT NULL,   -- vocab.id (PK, không phải vocab_id CSV)
  pinned_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pin (user_id, lesson_id, vocab_id),
  INDEX idx_user_lesson (user_id, lesson_id),
  CONSTRAINT fk_pin_vocab FOREIGN KEY (vocab_id) REFERENCES vocab(id) ON DELETE CASCADE,
  CONSTRAINT fk_pin_user  FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
