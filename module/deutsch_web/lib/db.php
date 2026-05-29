<?php
// db.php — PDO MySQL singleton + migrate runner.
// Server deutsch.twv.app = shared cPanel MySQL-first (MySQL 5.7, pdo_mysql ✓).
// DB app-managed qua scripts/migrate.php (đọc migrations/*.sql, idempotent IF NOT EXISTS).
// KHÔNG gõ DDL tay ở console. Code chạy được cả PHP 7.4 và 8.1.

function dw_config()
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/../config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit("config.php chưa có. Copy config.example.php → config.php và điền creds + api_key.");
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
        $db = $cfg['db'] ?? null;
        if (!is_array($db)) {
            http_response_code(500);
            exit("config.php thiếu block 'db' (host/name/user/pass). Xem config.example.php.");
        }
        $charset = $db['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$charset}";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Pin connection về UTC (offset số → KHÔNG cần timezone tables trên shared host).
        // Giữ created_at (CURRENT_TIMESTAMP) đồng nhất UTC với ack gmdate() + since UTC của Cowork.
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

// Chạy tất cả migrations/*.sql theo thứ tự tên. Tách câu theo ';' rồi exec từng câu
// (MySQL PDO không chạy nhiều statement 1 exec khi emulate_prepares=false).
// Idempotent nhờ IF NOT EXISTS.
function dw_migrate()
{
    $pdo = db();
    $files = glob(__DIR__ . '/../migrations/*.sql');
    sort($files);
    foreach ($files as $f) {
        $sql = file_get_contents($f);
        if ($sql === false) { continue; }
        foreach (dw_split_sql($sql) as $stmt) {
            $pdo->exec($stmt);
        }
    }
    return $files;
}

// Tách file SQL thành từng câu lệnh. Bỏ comment dòng (-- ...) + dòng trống.
// Migration của module không chứa ';' bên trong chuỗi → split theo ';' an toàn.
function dw_split_sql($sql)
{
    $lines = preg_split('/\R/', $sql);
    $clean = [];
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '' || strpos($trim, '--') === 0) { continue; }
        $clean[] = $line;
    }
    $joined = implode("\n", $clean);
    $parts = explode(';', $joined);
    $out = [];
    foreach ($parts as $p) {
        if (trim($p) !== '') { $out[] = trim($p); }
    }
    return $out;
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
