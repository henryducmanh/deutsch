<?php
// lesson_loader.php — đọc lessons/{id}.json + đối chiếu tên từ horen_lessons.csv.
// 344 bài = 344 file JSON nhỏ: danh sách đọc index CSV, drill đọc 1 file (mục 6 prompt).

require_once __DIR__ . '/db.php';

// Chặn path traversal: lesson_id chỉ chữ/số/dấu chấm/gạch (vd "4.29").
function lesson_id_valid($id)
{
    return is_string($id) && $id !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $id) === 1;
}

function lesson_path($id)
{
    $cfg = dw_config();
    $dir = rtrim($cfg['lessons_dir'], '/\\');
    return $dir . DIRECTORY_SEPARATOR . $id . '.json';
}

// Đọc 1 lesson JSON. null nếu không hợp lệ / không tồn tại / parse lỗi.
function lesson_load($id)
{
    if (!lesson_id_valid($id)) { return null; }
    $path = lesson_path($id);
    if (!is_file($path)) { return null; }
    $raw = file_get_contents($path);
    if ($raw === false) { return null; }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

// Map lesson_id → tên/chủ đề từ horen_lessons.csv (cột stt,bai,chu_de,url,sheet).
function horen_index()
{
    static $idx = null;
    if ($idx !== null) { return $idx; }
    $idx = [];
    $cfg = dw_config();
    $csv = $cfg['horen_index'] ?? '';
    if ($csv === '' || !is_file($csv)) { return $idx; }
    if (($fh = fopen($csv, 'r')) === false) { return $idx; }
    $header = fgetcsv($fh);
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) < 3) { continue; }
        // bai = cột 1 (index 1), chu_de = cột 2
        $bai = trim($row[1]);
        if ($bai === '') { continue; }
        $idx[$bai] = [
            'chu_de' => isset($row[2]) ? trim($row[2]) : '',
            'url'    => isset($row[3]) ? trim($row[3]) : '',
        ];
    }
    fclose($fh);
    return $idx;
}

// Danh sách bài có file JSON, kèm tên + điểm cao nhất đã làm (badge).
// $userId: lọc điểm theo user.
function lesson_list($userId)
{
    $cfg = dw_config();
    $dir = rtrim($cfg['lessons_dir'], '/\\');
    $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
    $idx = horen_index();

    // Điểm tốt nhất từng bài (từ events horen_complete của user).
    $scores = best_scores($userId);

    $out = [];
    foreach ($files as $f) {
        $id = basename($f, '.json');
        $data = lesson_load($id);
        if ($data === null) { continue; }
        $title = $data['title'] ?? ($idx[$id]['chu_de'] ?? $id);
        // teil: lấy từ JSON nếu có, fallback parse từ lesson_id ("1.5" → 1).
        $t = $data['teil'] ?? null;
        if ($t === null) {
            $parts = explode('.', $id, 2);
            $t = is_numeric($parts[0]) ? (int)$parts[0] : 0;
        }
        $out[] = [
            'lesson_id'   => $id,
            'title'       => $title,
            'thema'       => $data['thema'] ?? ($idx[$id]['chu_de'] ?? ''),
            'modul'       => $data['modul'] ?? 'Hören',
            'niveau'      => $data['niveau'] ?? '',
            'teil'        => (int)$t,
            'source_book' => $data['source_book'] ?? 'deutsch-vorbereitung',
            'best'        => $scores[$id] ?? null, // ['correct'=>x,'total'=>y] hoặc null
        ];
    }
    // Sort số học: 1.1 < 1.2 < 1.10 < 1.101 (tránh string sort sai thứ tự)
    usort($out, function($a, $b) {
        $pa = explode('.', $a['lesson_id'], 2);
        $pb = explode('.', $b['lesson_id'], 2);
        $t1 = (int)($pa[0] ?? 0); $n1 = (int)($pa[1] ?? 0);
        $t2 = (int)($pb[0] ?? 0); $n2 = (int)($pb[1] ?? 0);
        return $t1 !== $t2 ? $t1 - $t2 : $n1 - $n2;
    });
    return $out;
}

// Điểm cao nhất mỗi lesson_id từ events.horen_complete của user.
function best_scores($userId)
{
    $st = db()->prepare(
        "SELECT lesson_id, payload FROM events WHERE user_id = ? AND type = 'horen_complete'"
    );
    $st->execute([(int)$userId]);
    $best = [];
    foreach ($st->fetchAll() as $row) {
        $lid = $row['lesson_id'];
        $p = json_decode($row['payload'], true);
        if (!is_array($p) || $lid === null) { continue; }
        $c = (int)($p['correct'] ?? 0);
        $t = (int)($p['total'] ?? 0);
        if (!isset($best[$lid]) || $c > $best[$lid]['correct']) {
            $best[$lid] = ['correct' => $c, 'total' => $t];
        }
    }
    return $best;
}
