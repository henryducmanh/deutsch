<?php
// config.example.php — template. Copy thành config.php và điền api_key.
// config.php nằm trong .gitignore — KHÔNG commit token thật / password.
//
//   copy config.example.php config.php   (Windows)
//   cp   config.example.php config.php    (*nix)

return [
    // SQLite DB sinh runtime ở data/ (gitignored). Tự tạo khi migrate.
    'db_path'      => __DIR__ . '/data/deutsch_web.sqlite',

    // Bearer token cho /api/* — CHỈ Cowork/CLI dùng. Dán chuỗi ngẫu nhiên DÀI.
    // Sinh nhanh: php -r "echo bin2hex(random_bytes(32));"
    'api_key'      => 'PASTE_LONG_RANDOM_TOKEN_HERE',

    'session_name' => 'deutsch_web_sess',

    // lingq_s3 = frontend dùng audio.url trong lesson JSON (KHÔNG upload MP3 lên server).
    // 'local' = phase sau (chưa hỗ trợ).
    'audio_host'   => 'lingq_s3',

    'lessons_dir'  => __DIR__ . '/lessons',

    // Index 344 bài Hören (cột stt,bai,chu_de,url,sheet) để map tên bài ở danh sách.
    'horen_index'  => __DIR__ . '/../../input/html/deutsch-vorbereitung/horen_lessons.csv',
];
