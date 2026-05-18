<?php
// LingQ API config. Copy thành config.php và điền API key.
// API key lấy tại: https://www.lingq.com/accounts/apikey/
// config.php nằm trong .gitignore — KHÔNG commit token thật.

return [
    'api_key'   => 'PASTE_YOUR_LINGQ_TOKEN_HERE',
    'language'  => 'de',
    'base_url'  => 'https://www.lingq.com/api/v2',
    'page_size' => 200,
    'timeout'   => 30,
    'retry'     => 3,
    'sleep_ms'  => 500,
];
