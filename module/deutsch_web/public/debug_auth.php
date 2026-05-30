<?php
// debug_auth.php — XÓA SAU KHI DEBUG XONG. Không để file này trên production.
// Upload vào docroot deutsch.twv.app, curl: curl -H "Authorization: Bearer TOKEN" https://deutsch.twv.app/debug_auth.php

header('Content-Type: application/json; charset=utf-8');

// ── 1. Gom mọi biến $_SERVER liên quan auth ──
$authVars = [];
foreach ($_SERVER as $k => $v) {
    if (stripos($k, 'auth') !== false || stripos($k, 'http_') !== false) {
        $authVars[$k] = $v;
    }
}
ksort($authVars);

// ── 2. apache_request_headers() ──
$apacheHeaders = [];
if (function_exists('apache_request_headers')) {
    foreach (apache_request_headers() as $k => $v) {
        if (strtolower($k) === 'authorization') {
            $apacheHeaders[$k] = $v;
        }
    }
} else {
    $apacheHeaders = ['_note' => 'apache_request_headers() KHÔNG khả dụng'];
}

// ── 3. Đọc token theo logic api_auth.php ──
$hdr = '';
$source = 'none';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'];
    $source = 'HTTP_AUTHORIZATION';
} elseif (function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    foreach ($h as $k => $v) {
        if (strtolower($k) === 'authorization') { $hdr = $v; $source = 'apache_request_headers'; break; }
    }
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $hdr = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    $source = 'REDIRECT_HTTP_AUTHORIZATION';
}

$token = null;
if (preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
    $token = trim($m[1]);
}

// ── 4. So sánh với config.php ──
$cfgPath = __DIR__ . '/../config.php';
$cfgExists = file_exists($cfgPath);
$cfgApiKey = null;
$tokenMatch = null;
if ($cfgExists) {
    $cfg = require $cfgPath;
    $cfgApiKey = isset($cfg['api_key']) ? $cfg['api_key'] : null;
    if ($token !== null && $cfgApiKey !== null) {
        $tokenMatch = hash_equals((string)$cfgApiKey, (string)$token);
    }
}

echo json_encode([
    'php_sapi'            => PHP_SAPI,
    'token_source'        => $source,
    'raw_hdr'             => $hdr ?: null,
    'token_extracted'     => $token,
    'token_length'        => $token !== null ? strlen($token) : null,
    'config_exists'       => $cfgExists,
    'config_key_length'   => $cfgApiKey !== null ? strlen($cfgApiKey) : null,
    'config_key_prefix10' => $cfgApiKey !== null ? substr($cfgApiKey, 0, 10) . '...' : null,
    'token_match'         => $tokenMatch,
    'auth_server_vars'    => $authVars,
    'apache_headers'      => $apacheHeaders,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
