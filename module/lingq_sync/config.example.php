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

    // Phase J — enriched notes sync (vocab.notes + chunks + weak_words + MISTAKES_LOG → LingQ.notes).
    // Marker placeholders: %DATE% = today YYYY-MM-DD; %ID% = vocab_master.id (VOC-...).
    // Idempotency regex (hardcoded): /\[AI-sync \d{4}-\d{2}-\d{2} \| VOC-[\w-]+\]/.
    'notes_prefix'              => '[AI-sync %DATE% | %ID%]',
    // 2026-05-19: verify thực tế (probe PATCH lên pk=659048133) → server lưu chính xác
    // tới 100,000 chars (test stopped, no truncation observed). Set conservative 50000.
    'notes_max_chars'           => 50000,
    'notes_max_collocations'    => 5,    // top N chunks per row (filter theo wort lemma).
    'notes_max_mistakes'        => 5,    // top N entries từ MISTAKES_LOG per row.
    'notes_enrichment'          => true, // false → fallback: chỉ push '[AI-sync ...] <vocab.notes plain>'.
    'notes_strict_chunk_match'  => false,// true → word-boundary regex match (tránh Mut→Mutter false hit).

    // Phase K — Lessons push (K2 text / K3 audio). lessons_push.php POST bài lên LingQ.
    // lessons_course_id: PK của 1 collection (course) user TỰ TẠO trên LingQ web để chứa
    //   bài push. Lấy từ URL course (.../library/course/<PK>) hoặc collections/my API.
    //   Để '' → push.php --apply sẽ chặn + in hướng dẫn (trừ khi chạy với --no-course).
    'lessons_course_id'   => '',
    'lessons_level'       => 3,         // 0..6 (3 = Intermediate 1, hợp B1). Xem README endpoint table.
    'lessons_status'      => 'private', // 'private' = chỉ user; 'shared' = public library.
    'lessons_default_tags'=> ['DTZ', 'B1'], // tag chung; lessons_push thêm Lesen/Hören + Teil<N>.
];
