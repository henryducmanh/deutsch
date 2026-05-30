<?php
// config.example.php — template. Copy thành config.php và điền api_key.
// config.php nằm trong .gitignore — KHÔNG commit token thật.
//
//   copy config.example.php config.php   (Windows)
//   cp   config.example.php config.php    (*nix)
//
// api_key PHẢI TRÙNG chuỗi 'api_key' trong config.php của server deutsch_web
// (module/deutsch_web/config.php). Đây là Bearer token chung cho /api/*.

return [
    'api_base'        => 'https://deutsch.twv.app',
    'api_key'         => 'PASTE_SAME_BEARER_TOKEN_AS_SERVER_CONFIG',
    'timeout'         => 30,
    'retry'           => 3,

    // Progress file mục tiêu — auto-append (cơ học, thay ghi tay). Schema:
    //   bai,ngay,dung,tong,pct,ghi_chu
    'horen_progress'  => __DIR__ . '/../../output/drills/horen_progress.csv',

    // Staging từ đánh dấu (word_mark) chờ Cowork curate. Schema:
    //   event_id,word,word_status,lesson_id,context,clicked_at,curated
    'pending_words'   => __DIR__ . '/staging/pending_words.csv',

    // ── Vocab sync (push_vocab.php / pull_vocab.php) ──
    // Nguồn READ-ONLY đẩy lên server (push_vocab.php). Schema 16 cột (data/README.md).
    'vocab_csv'         => __DIR__ . '/../../data/03_unified/vocab_master.csv',

    // Staging từ web-add (curated=0) kéo về (pull_vocab.php). User review rồi mới
    // import thủ công vào vocab_master.csv. Schema:
    //   web_id,wort,wort_key,bedeutung,wortart,artikel,source_lesson,created_at
    'vocab_new_output'  => __DIR__ . '/../../output/drills/vocab_new_web.csv',
];
