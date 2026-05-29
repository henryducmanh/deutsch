<?php
// db.php — PDO SQLite singleton + migrate runner.
// SQLite app-managed: schema chạy qua scripts/migrate.php (đọc migrations/*.sql, idempotent).
// KHÔNG gõ DDL tay ở console.

function dw_config()
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/../config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit("config.php chưa có. Copy config.example.php → config.php và điền api_key.");
        }
        $cfg = require $path;
    }
    return $cfg;
}

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = dw_config();
        $dir = dirname($cfg['db_path']);
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        $pdo = new PDO('sqlite:' . $cfg['db_path']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // WAL: đọc/ghi mượt khi cron pull lúc đang học (mục 6 prompt).
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
        $pdo->exec('PRAGMA busy_timeout=5000');
    }
    return $pdo;
}

// Chạy tất cả migrations/*.sql theo thứ tự tên. Idempotent nhờ IF NOT EXISTS.
function dw_migrate()
{
    $pdo = db();
    $files = glob(__DIR__ . '/../migrations/*.sql');
    sort($files);
    foreach ($files as $f) {
        $sql = file_get_contents($f);
        if ($sql !== false && trim($sql) !== '') {
            $pdo->exec($sql);
        }
    }
    return $files;
}

// UUID v4 sinh thuần PHP (không cần ext). Dùng cho events.event_id.
function dw_uuid4()
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Thời điểm UTC ISO-8601 (Z). Dùng khi cần trả/ghi created_at chuẩn.
function dw_now_iso()
{
    return gmdate('Y-m-d\TH:i:s\Z');
}
