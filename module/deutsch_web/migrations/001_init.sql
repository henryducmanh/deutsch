-- 001_init.sql — schema MySQL 5.7 (InnoDB, utf8mb4). Idempotent (IF NOT EXISTS) → chạy lại an toàn.
-- Chạy qua scripts/migrate.php (tách theo ';', exec từng câu). KHÔNG gõ DDL tay ở console.

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(64)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log học tập append-only. synced_at NULL = pending (chưa Cowork ack).
CREATE TABLE IF NOT EXISTS events (
  event_id   CHAR(36)    NOT NULL PRIMARY KEY,   -- uuid v4 sinh ở PHP
  user_id    INT         NOT NULL,
  type       VARCHAR(32) NOT NULL,               -- horen_complete | word_mark | lesson_open
  lesson_id  VARCHAR(16) NULL,                   -- vd '4.29'
  payload    JSON        NOT NULL,               -- JSON string (app json_encode)
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  synced_at  DATETIME    NULL DEFAULT NULL,
  INDEX idx_events_created (created_at),
  INDEX idx_events_sync (synced_at),
  INDEX idx_events_type (type),
  CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
