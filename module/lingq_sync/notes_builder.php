<?php
/**
 * notes_builder.php — Phase J utility: render enriched notes từ 4 nguồn
 *   (vocab_master.notes + chunks_master + weak_words + MISTAKES_LOG.md).
 *
 * Public functions:
 *   build_enriched_notes($vocRow, $allChunks, $allWeak, $allMistakes, $cfg, $today=null)
 *     → string markdown (prefix [AI-sync ... | VOC-...]). Empty string nếu không section nào có content.
 *
 *   load_chunks_master($path)   → array rows (skip lines bắt đầu '#').
 *   load_weak_words($path)      → array rows (skip lines bắt đầu '#').
 *   parse_mistakes_log($path)   → array of {date, category, text}.
 *
 *   merge_notes_for_patch($target, $serverNotes)
 *     → final string để PATCH lên server.
 *     Cases: server empty → target; server có marker → replace marker block + giữ user text;
 *            server có user text không marker → append target sau "\n---\n";
 *            target='' → strip marker block khỏi server (giữ user text).
 *
 *   truncate_to_max($text, $maxChars)  → cắt cuối + marker "... (truncated at N chars)".
 *
 * AI-marker regex (idempotency key): /\[AI-sync \d{4}-\d{2}-\d{2} \| VOC-[\w-]+\]/
 */

if (!defined('LINGQ_NOTES_BUILDER_LOADED')) {
    define('LINGQ_NOTES_BUILDER_LOADED', 1);

    /**
     * Build enriched notes markdown for one vocab_master row.
     *
     * @param array $vocRow assoc keys: id, wort, notes, ... (14 cột vocab_master).
     * @param array $allChunks rows từ chunks_master.csv.
     * @param array $allWeak rows từ weak_words.csv.
     * @param array $allMistakes entries parsed từ MISTAKES_LOG.md.
     * @param array $cfg config (notes_prefix, notes_max_chars, notes_max_collocations,
     *                          notes_max_mistakes, notes_strict_chunk_match).
     * @param string|null $today YYYY-MM-DD (default = date('Y-m-d')).
     * @return string enriched notes (markdown). '' nếu không section nào.
     */
    function build_enriched_notes(array $vocRow, array $allChunks, array $allWeak, array $allMistakes, array $cfg, $today = null)
    {
        if ($today === null) $today = date('Y-m-d');
        $wort = trim((string)(isset($vocRow['wort']) ? $vocRow['wort'] : ''));
        $id   = trim((string)(isset($vocRow['id']) ? $vocRow['id'] : ''));
        if ($wort === '') return '';

        $maxColl = isset($cfg['notes_max_collocations']) ? (int)$cfg['notes_max_collocations'] : 5;
        $maxMist = isset($cfg['notes_max_mistakes']) ? (int)$cfg['notes_max_mistakes'] : 5;
        $strict  = !empty($cfg['notes_strict_chunk_match']);

        $sections = [];

        // 1. Grammar / Notes (vocab_master.notes).
        $grammar = trim((string)(isset($vocRow['notes']) ? $vocRow['notes'] : ''));
        if ($grammar !== '') {
            $sections['Grammar / Notes'] = $grammar;
        }

        // 2. Collocations từ chunks_master.csv.
        $colls = [];
        foreach ($allChunks as $c) {
            $de   = (string)(isset($c['chunk_de']) ? $c['chunk_de'] : '');
            $vn   = (string)(isset($c['chunk_vn']) ? $c['chunk_vn'] : '');
            $note = (string)(isset($c['note']) ? $c['note'] : '');
            if (!chunk_matches_wort($de, $wort, $strict) && !chunk_matches_wort($note, $wort, $strict)) continue;
            $line = '- ' . trim($de);
            if (trim($vn) !== '') $line .= ' (' . trim($vn) . ')';
            $colls[] = $line;
            if (count($colls) >= $maxColl) break;
        }
        if (!empty($colls)) {
            $sections['Collocations'] = implode("\n", $colls);
        }

        // 3. Weak word — find exact wort match.
        $weakMatch = null;
        foreach ($allWeak as $w) {
            if (trim((string)(isset($w['wort']) ? $w['wort'] : '')) === $wort) {
                $weakMatch = $w;
                break;
            }
        }
        if ($weakMatch !== null) {
            $parts = [];
            $mc = trim((string)(isset($weakMatch['mistake_count']) ? $weakMatch['mistake_count'] : ''));
            if ($mc !== '') $parts[] = "mistake_count: {$mc}";
            $ld = trim((string)(isset($weakMatch['last_mistake_date']) ? $weakMatch['last_mistake_date'] : ''));
            if ($ld !== '') $parts[] = "last: {$ld}";
            $rr = trim((string)(isset($weakMatch['related_rule']) ? $weakMatch['related_rule'] : ''));
            if ($rr !== '') $parts[] = "rule: {$rr}";
            if (!empty($parts)) {
                $sections['Weak word'] = implode(' — ', $parts);
            }
        }

        // 4. Past mistakes từ MISTAKES_LOG entries.
        $mists = [];
        foreach ($allMistakes as $m) {
            $text = trim((string)(isset($m['text']) ? $m['text'] : ''));
            $cat  = trim((string)(isset($m['category']) ? $m['category'] : ''));
            $hitWort = ($wort !== '' && stripos($text, $wort) !== false);
            $hitId   = ($id !== '' && stripos($text, $id) !== false);
            if (!$hitWort && !$hitId) continue;
            $line = '- ' . (string)(isset($m['date']) ? $m['date'] : '?');
            if ($cat !== '') $line .= ' (' . $cat . ')';
            // Compress whitespace để mistake block không nuốt cả paragraph dài.
            $compactText = preg_replace('/\s+/', ' ', $text);
            $line .= ': ' . $compactText;
            $mists[] = $line;
            if (count($mists) >= $maxMist) break;
        }
        if (!empty($mists)) {
            $sections['Past mistakes'] = implode("\n", $mists);
        }

        // 5. Cross-ref — regex VOC-\d{8}-\d{3} trong grammar + collocations content.
        $blob = $grammar . "\n" . implode("\n", $colls);
        if (preg_match_all('/VOC-\d{8}-\d{3}/', $blob, $mm)) {
            $refs = array_values(array_unique($mm[0]));
            $refs = array_values(array_diff($refs, [$id]));
            if (!empty($refs)) {
                $lines = array_map(function ($r) { return '- ' . $r; }, $refs);
                $sections['Cross-ref'] = implode("\n", $lines);
            }
        }

        if (empty($sections)) return '';

        // 6. Render: marker + sections in fixed order.
        $prefix = isset($cfg['notes_prefix']) ? (string)$cfg['notes_prefix'] : '[AI-sync %DATE% | %ID%]';
        $marker = strtr($prefix, ['%DATE%' => $today, '%ID%' => $id]);

        $out = $marker . "\n";
        $order = ['Grammar / Notes', 'Collocations', 'Weak word', 'Past mistakes', 'Cross-ref'];
        foreach ($order as $h) {
            if (!isset($sections[$h])) continue;
            $out .= "\n## " . $h . "\n" . $sections[$h] . "\n";
        }
        // Trim trailing newlines để compare ổn định.
        $out = rtrim($out, "\r\n") . "\n";

        $maxChars = isset($cfg['notes_max_chars']) ? (int)$cfg['notes_max_chars'] : 10000;
        return truncate_to_max($out, $maxChars);
    }

    /**
     * Match lemma trong haystack.
     *   strict=false (default): stripos — match cả substring.
     *   strict=true: word boundary regex (Unicode-aware) — match exact word forms.
     * Trade-off: stripos rẻ + match flexion (Schüler → Schülern), strict tránh false positive (Mut→Mutter).
     */
    function chunk_matches_wort($haystack, $wort, $strict)
    {
        $h = (string)$haystack;
        $w = (string)$wort;
        if ($h === '' || $w === '') return false;
        if (!$strict) {
            return stripos($h, $w) !== false;
        }
        $pat = '/(?<![\p{L}\p{N}_])' . preg_quote($w, '/') . '(?![\p{L}\p{N}_])/iu';
        return preg_match($pat, $h) === 1;
    }

    /**
     * Truncate text to $maxChars, append "\n... (truncated at N chars)" marker.
     * $maxChars ≤ 0 → no truncation.
     */
    function truncate_to_max($text, $maxChars)
    {
        $maxChars = (int)$maxChars;
        if ($maxChars <= 0) return (string)$text;
        $text = (string)$text;
        if (strlen($text) <= $maxChars) return $text;
        $tail = "\n... (truncated at {$maxChars} chars)";
        $room = $maxChars - strlen($tail);
        if ($room < 0) $room = 0;
        return substr($text, 0, $room) . $tail;
    }

    /**
     * Parse MISTAKES_LOG.md → array of entries {date, category, text}.
     * Format: ## YYYY-MM-DD — Category — rule short
     *         <body lines until next ##>
     * Entry trống / chỉ scaffold → trả [].
     */
    function parse_mistakes_log($path)
    {
        if (!file_exists($path)) return [];
        $fh = @fopen($path, 'r');
        if (!$fh) return [];

        $out = [];
        $cur = null;
        while (($line = fgets($fh)) !== false) {
            $r = rtrim($line, "\r\n");
            // Heading "## YYYY-MM-DD — Category — ..." (em-dash hoặc hyphen).
            if (preg_match('/^##\s+(\d{4}-\d{2}-\d{2})\s*[—\-]\s*([^—\-\n]+?)(?:\s*[—\-].*)?$/u', $r, $hm)) {
                if ($cur !== null) {
                    $out[] = ['date' => $cur['date'], 'category' => $cur['category'], 'text' => trim(implode("\n", $cur['body']))];
                }
                $cur = ['date' => $hm[1], 'category' => trim($hm[2]), 'body' => []];
                continue;
            }
            if ($cur !== null) {
                $cur['body'][] = $r;
            }
        }
        fclose($fh);
        if ($cur !== null) {
            $out[] = ['date' => $cur['date'], 'category' => $cur['category'], 'text' => trim(implode("\n", $cur['body']))];
        }
        return $out;
    }

    /**
     * Load chunks_master.csv → array rows (skip comment lines, BOM strip first cell).
     */
    function load_chunks_master($path)
    {
        return _load_csv_skipping_comments($path, [
            'id','chunk_de','chunk_vn','type','topic','level','source',
            'first_seen','last_practiced','frequency','note'
        ]);
    }

    /**
     * Load weak_words.csv → array rows.
     */
    function load_weak_words($path)
    {
        return _load_csv_skipping_comments($path, [
            'wort','wortart','artikel','mistake_count','last_mistake_date',
            'related_rule','related_mistakes_log_entries','status'
        ]);
    }

    /**
     * Generic CSV loader bỏ qua comment lines (# tại đầu dòng) + empty lines.
     * Header row (sau khi skip comment) phải khớp $expectedCols (length); nếu không
     * khớp, dùng thứ tự columns theo header thực tế.
     */
    function _load_csv_skipping_comments($path, array $expectedCols)
    {
        if (!file_exists($path)) return [];
        $fh = @fopen($path, 'r');
        if (!$fh) return [];

        $out = [];
        $header = null;
        while (($line = fgets($fh)) !== false) {
            $r = rtrim($line, "\r\n");
            if ($r === '') continue;
            if (strncmp($r, '#', 1) === 0) continue;
            $row = str_getcsv($r);
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }
            if ($header === null) {
                $header = $row;
                continue;
            }
            // Pad short rows.
            if (count($row) < count($header)) {
                $row = array_pad($row, count($header), '');
            }
            $assoc = array_combine($header, array_slice($row, 0, count($header)));
            if ($assoc === false) continue;
            $out[] = $assoc;
        }
        fclose($fh);
        return $out;
    }

    /**
     * Merge target enriched block với server existing notes.
     * Trả về final value để PATCH (hoặc compare equality → skip).
     *
     * Cases (per Phase J §3):
     *   - server empty → return target.
     *   - server có marker (case C): split tại offset marker, giữ user_part nguyên trạng
     *     (kể cả separator "\n---\n"), ghép user_part + new_target.
     *   - server có user text không marker (case D): append "\n---\n" + target.
     *   - target empty: strip marker block khỏi server (giữ user text trước marker,
     *     strip trailing "\n---" separator).
     *
     * Idempotency: server pushed bởi Case D iter 1 ("user\n---\nmarker...") → khi
     * server đó lại làm input Case C iter 2 → user_part = "user\n---\n" → output
     * giống y server (no patch).
     */
    function merge_notes_for_patch($target, $serverNotes)
    {
        $target = (string)$target;
        $serverNotes = (string)$serverNotes;
        $markerPattern = '/\[AI-sync \d{4}-\d{2}-\d{2} \| VOC-[\w-]+\]/';

        if (trim($serverNotes) === '') {
            return $target;
        }

        if (preg_match($markerPattern, $serverNotes, $mm, PREG_OFFSET_CAPTURE)) {
            $offset = (int)$mm[0][1];
            $userPart = substr($serverNotes, 0, $offset);
            if ($target === '') {
                // Strip trailing separator + whitespace user_part đã có.
                $stripped = preg_replace('/\s*\n---\s*\n?\s*$/', '', $userPart);
                return rtrim($stripped);
            }
            if (trim($userPart) === '') {
                return $target;
            }
            // Preserve user_part nguyên trạng (kể cả separator) để idempotent.
            return $userPart . $target;
        }

        // Server có user text, không có marker (case D).
        if ($target === '') {
            return $serverNotes;
        }
        return rtrim($serverNotes, "\r\n") . "\n---\n" . $target;
    }
}
