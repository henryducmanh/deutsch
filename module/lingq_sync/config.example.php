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

    // Phase F — locale của hints VI lưu lên server. LingQ API v2 dùng hints array
    // [{text, locale}, ...]. Sync.php filter theo locale này; push.php build payload
    // với locale này. Đổi nếu muốn UI hiện ngôn ngữ khác.
    'hint_locale' => 'vi',

    // Phase D — push thresholds (safety guard chống wipe accidental).
    // manual: chạy tay (default), cron: chạy auto qua cron.bat / --auto-confirm.
    // Hit threshold → require --force-delete-all (manual) hoặc abort hẳn (cron).
    'push_thresholds' => [
        'manual_max_delete_pct' => 80,   // manual: cần --force-delete-all nếu DELETE > 80%
        'auto_max_delete_pct'   => 20,   // cron:   abort nếu DELETE > 20%
        'auto_max_delete_abs'   => 50,   // cron:   abort nếu DELETE > 50 absolute (whichever first)
    ],
];
