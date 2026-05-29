<?php
// api_auth.php — check Bearer token cho /api/*. Token đọc từ config.php (api_key).
// Dùng cho CLI/Cowork pull event. KHÔNG dùng cho route web (route web dùng session).

require_once __DIR__ . '/db.php';

function api_bearer_token()
{
    $hdr = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        foreach ($h as $k => $v) {
            if (strtolower($k) === 'authorization') { $hdr = $v; break; }
        }
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $hdr = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
        return trim($m[1]);
    }
    return null;
}

// So sánh hằng-thời-gian, chặn timing attack.
function api_require_key()
{
    $cfg = dw_config();
    $expected = $cfg['api_key'] ?? '';
    $token = api_bearer_token();
    if ($expected === '' || $expected === 'PASTE_LONG_RANDOM_TOKEN_HERE') {
        api_json(500, ['error' => 'api_key chưa cấu hình trong config.php']);
    }
    if ($token === null || !hash_equals($expected, $token)) {
        api_json(401, ['error' => 'Unauthorized']);
    }
}

function api_json($code, $data)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Đọc body JSON của POST. Trả mảng (rỗng nếu lỗi parse).
function api_body_json()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') { return []; }
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}
