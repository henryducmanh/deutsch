-- 001_init.sql — schema SQLite ban đầu. Idempotent (IF NOT EXISTS) → chạy lại an toàn.
-- Chạy qua scripts/migrate.php. KHÔNG gõ DDL tay ở console.

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
  payload     TEXT NOT NULL,                 -- JSON string
  created_at  TEXT NOT NULL DEFAULT (datetime('now')),
  synced_at   TEXT DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at);
CREATE INDEX IF NOT EXISTS idx_events_sync    ON events(synced_at);
CREATE INDEX IF NOT EXISTS idx_events_type    ON events(type);
