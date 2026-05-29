<?php
// migrate.php — chạy migrations/*.sql 1 lần (idempotent) + seed user.
// CLI only. KHÔNG gõ DDL tay ở console.
//
// Dùng:
//   php scripts/migrate.php                       → tạo schema (users + events)
//   php scripts/migrate.php --add-user henry      → thêm user, hỏi password (ẩn nếu được)
//   php scripts/migrate.php --add-user henry --password "..."   → password qua arg (không khuyến khích)

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../lib/db.php';

// ── Parse args ──
$args = $argv;
array_shift($args);
$addUser = null;
$passwordArg = null;
for ($i = 0; $i < count($args); $i++) {
    if ($args[$i] === '--add-user' && isset($args[$i + 1])) { $addUser = $args[++$i]; }
    elseif ($args[$i] === '--password' && isset($args[$i + 1])) { $passwordArg = $args[++$i]; }
}

// ── Migrate ──
$files = dw_migrate();
$dbcfg = dw_config()['db'] ?? [];
echo "Migrated " . count($files) . " file(s): users + events ready (idempotent).\n";
echo "DB: " . ($dbcfg['user'] ?? '?') . "@" . ($dbcfg['host'] ?? '?') . "/" . ($dbcfg['name'] ?? '?') . " (MySQL)\n";

// ── Seed user (optional) ──
if ($addUser !== null) {
    $username = trim($addUser);
    if ($username === '') { fwrite(STDERR, "username rỗng.\n"); exit(1); }

    $password = $passwordArg;
    if ($password === null) {
        // Hỏi password (ẩn nếu shell hỗ trợ, fallback hiện).
        fwrite(STDOUT, "Password cho '$username': ");
        $password = read_password_hidden();
        fwrite(STDOUT, "\n");
    }
    if ($password === null || $password === '') { fwrite(STDERR, "password rỗng.\n"); exit(1); }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo = db();
    $exists = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $exists->execute([$username]);
    if ($row = $exists->fetch()) {
        $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $up->execute([$hash, $row['id']]);
        echo "User '$username' đã tồn tại → cập nhật password (id={$row['id']}).\n";
    } else {
        $ins = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $ins->execute([$username, $hash]);
        echo "User '$username' đã tạo (id=" . $pdo->lastInsertId() . "), password đã hash.\n";
    }
}

echo "Done.\n";

// Đọc password không hiện màn hình (Windows: dùng powershell; *nix: stty). Fallback: đọc thường.
function read_password_hidden()
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: nhờ powershell đọc SecureString.
        $cmd = 'powershell -NoProfile -Command "$p=Read-Host -AsSecureString;' .
               '$b=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p);' .
               '[Runtime.InteropServices.Marshal]::PtrToStringAuto($b)"';
        $out = @shell_exec($cmd);
        if ($out !== null) { return rtrim($out, "\r\n"); }
    } else {
        @shell_exec('stty -echo');
        $line = fgets(STDIN);
        @shell_exec('stty echo');
        if ($line !== false) { return rtrim($line, "\r\n"); }
    }
    // Fallback: đọc thường (hiện màn hình).
    $line = fgets(STDIN);
    return $line === false ? null : rtrim($line, "\r\n");
}
