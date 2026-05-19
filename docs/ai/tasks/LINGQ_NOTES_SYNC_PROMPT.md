# LingQ Notes Sync — Phase J v3 (extend Phase D + enriched notes từ 4 nguồn deutsch)

> **Task ID:** `lingq_notes_sync_v3`
> **Người triển khai:** Claude Code (vai Implementer).
> **Stack:** PHP 7.4 local (`C:\php\php74\php.exe`), extends `module/lingq_sync/`. **KHÔNG tạo file PHP mới** — chỉ sửa 3 file PHP + 1 config + 2 doc. Một file PHP utility nhỏ `notes_builder.php` được phép tạo nếu code merge phình > 300 dòng (tách riêng cho test gọn).
> **Lock:** `.ai-locks/lingq_notes_impl.lock` 1-per-task TTL 60min.
> **Prerequisite:** Phase C/D/F active, cron 4-step 10:00 daily ổn, schema vocab_master 14 cột (xem `data/README.md`).
>
> **Lịch sử quyết định:**
> - v1 (đã reject): tạo `push_notes.php` riêng, GET search/row, cron 22:00 riêng → duplicate code + tăng API calls.
> - v2 (đã reject): chỉ push thẳng `vocab_master.notes` (cột 14) — content quá ngắn cho học sâu B1.
> - **v3 (current):** extend Phase D + render **enriched notes** join từ 4 nguồn deutsch (`vocab_master.notes` + `chunks_master` + `weak_words` + `MISTAKES_LOG.md`) → đẩy lên LingQ field `notes`. Hint vẫn giữ `bedeutung` VI (Phase D).

---

## 1. End-user

**Henry** — solo dev kiêm DTZ B1 learner. Học tiếng Đức cần **hiểu sâu** một từ: ngữ pháp đặc thù, collocation đi kèm, lỗi đã mắc, từ liên quan. `vocab_master.notes` cột 14 chỉ là **note ngắn** (1-2 câu grammar trap). Bộ thông tin đầy đủ trải trên 4 file:

| Nguồn | Schema | Cho 1 từ "Schüler" có gì? |
|---|---|---|
| `data/03_unified/vocab_master.csv` cột 14 `notes` | string ngắn | "Dat.Pl. = Schülern (n-Deklination Pl.). m. wort gốc Schüler; Pl. Schüler." |
| `data/chunks_master.csv` (11 cột) | mỗi row 1 collocation | "den Schülern Wissen vermitteln", "Schüler unterrichten", ... |
| `data/weak_words.csv` (8 cột) | mỗi row 1 từ user hay sai | `wort=Schüler, mistake_count=2, related_rule=n-Deklination Pl.` |
| `docs/ai/MISTAKES_LOG.md` (markdown) | entry per pattern lỗi | "2026-05-15 — Grammar — viết 'Schülers' thay vì 'Schülern' (Dat.Pl.)" |

User muốn khi đọc text trên LingQ web/mobile, mở card → thấy **all-in-one** thông tin đó.

---

## 2. Notes limit (verify trước khi code — BẮT BUỘC)

### Web research (2026-05-19)

- LingQ forum xác nhận **Hint field = 250 char cứng** (vượt → không hiển thị flashcard).
- **Notes field: KHÔNG có doc public** về limit. Phải curl test thực tế.

### Endpoint verify protocol — extend §4 cho limit test

Trước khi viết enricher, Claude Code MUST chạy 4 curl với notes dài tăng dần:

```bash
# Test L1: 250 chars
curl -X PATCH -H "Authorization: Token $T" -H "Content-Type: application/json" \
     -d "$(jq -n --arg n "$(printf 'a%.0s' {1..250})" '{notes:$n}')" \
     "https://www.lingq.com/api/v3/de/cards/<test-pk>/"
# expect: 200/204

# Test L2: 1000 chars
# ...tương tự với 1000 ký tự...

# Test L3: 5000 chars
# Test L4: 20000 chars
# Test L5: 100000 chars (edge — chắc chắn vượt)

# Sau mỗi PATCH, GET lại verify server lưu chính xác bao nhiêu chars.
```

Kết quả phải **đính kèm vào `docs/LINGQ_INTEGRATION.md` mục 7 hoặc mục 13 mới (Phase J verify log)**:

```
notes max chars (verified 2026-05-19): N
  - PATCH N chars → 200 OK, GET back đúng N chars
  - PATCH N+1 chars → 413 / 400 / silent truncate
```

### Truncate policy (tuỳ kết quả)

| Notes max (verify) | Policy |
|---|---|
| Không giới hạn / >= 50k chars | Render full enriched, không truncate. |
| 1k-10k chars | Render full nếu fit, else truncate cuối với marker `\n... (truncated at N chars)`. |
| < 1k chars (giống hint 250) | RỦI RO — fallback chỉ push tóm tắt (Grammar + 2 collocation top). Document cảnh báo trong README. |

→ Config key mới `notes_max_chars` (int, default = kết quả verify; nếu unknown → 10000 conservative).

---

## 3. Enriched notes — format render

### Template Markdown (render trong `update_local.php`)

```
[AI-sync 2026-05-19 | VOC-20260519-002]

## Grammar / Notes
Dat.Pl. = Schülern (n-Deklination Pl.). m. wort gốc Schüler; Pl. Schüler.

## Collocations
- den Schülern Wissen vermitteln (truyền đạt kiến thức cho học sinh)
- Schüler unterrichten (giảng dạy học sinh)
- die Schüler aus der Schule (học sinh từ trường)

## Weak word
mistake_count: 2 — Pl. wrong form
last_mistake: 2026-05-15
rule: m. masc. n-Dekl. Sg/Pl: Schüler / Schülers / Schüler / Schüler // Schüler / Schüler / Schülern / Schüler

## Past mistakes
- 2026-05-15 (Grammar): viết "Schülers" thay vì "Schülern" (Dat.Pl.)
- 2026-05-12 (Grammar): quên n-Dekl. → "den Schüler" thay vì "den Schülern"

## Cross-ref
- VOC-20260518-028 (vermitteln)
```

- Sections **chỉ render nếu có content** — empty section skip hẳn (không in heading rỗng).
- Order section cố định: `Grammar/Notes → Collocations → Weak word → Past mistakes → Cross-ref`.
- Cross-ref auto-parse từ regex `VOC-\d{8}-\d{3}` trong content vocab_master.notes.

### Logic join (function `build_enriched_notes($vocRow, $allChunks, $allWeak, $allMistakes)`)

```
INPUT:
  vocRow      = 1 row vocab_master (assoc array 14 keys)
  allChunks   = mảng đọc 1 lần đầu update_local.php từ chunks_master.csv
  allWeak     = mảng đọc 1 lần đầu từ weak_words.csv
  allMistakes = đọc parse MISTAKES_LOG.md → mảng entry {date, category, text}

ALGO:
  1. section_grammar = vocRow['notes']  (trim, có thể empty)
  2. section_collocations =
       allChunks.filter(c => stripos(c.chunk_de, vocRow.wort) !== false
                           OR stripos(c.note ?? '', vocRow.wort) !== false)
                .map(c => "- " . c.chunk_de . " (" . c.chunk_vn . ")")
                .slice(0, cfg['notes_max_collocations'])  // default 5
  3. section_weak = allWeak.find(w => w.wort === vocRow.wort)  → 1 entry hoặc null
  4. section_mistakes = allMistakes.filter(m =>
       stripos(m.text, vocRow.wort) !== false
       OR stripos(m.text, vocRow.id) !== false
     ).slice(0, cfg['notes_max_mistakes'])  // default 5
  5. section_crossref = parse regex /VOC-\d{8}-\d{3}/ trên section_grammar + collocations
  6. Render Markdown theo template trên, skip section rỗng.
  7. Prefix với marker [AI-sync YYYY-MM-DD | VOC-...]
  8. Nếu strlen(result) > cfg['notes_max_chars']:
       cắt result tới max - 30 chars
       append "\n... (truncated at N chars)"
  9. Return string.
```

### Config keys mới (`config.example.php`)

```php
'notes_prefix'           => '[AI-sync %DATE% | %ID%]',
'notes_max_chars'        => 10000,   // ← bump sau khi verify thực tế
'notes_max_collocations' => 5,
'notes_max_mistakes'     => 5,
'notes_enrichment'       => true,    // false = fallback chỉ push vocab_master.notes plain
```

### Idempotency

- Marker regex giữ nguyên: `\[AI-sync \d{4}-\d{2}-\d{2} \| (VOC-[\w-]+)\]`.
- Case B (idempotent SKIP) check: nếu server notes == target enriched notes → no patch.
- Case C (replace): server có marker cũ (ngày khác hoặc cùng ngày khác content) → tách user_text trước marker, ghép user_text + new_target.
- Case D (append): server có user text không có marker → APPEND `\n---\n` + target.

→ Khi enrichment thay đổi (vd thêm 1 mistake hôm nay) → content target khác content server → trigger PATCH automatic. Đúng cadence ngày user cần.

---

## 4. Màn cuối cùng (files cụ thể)

### Files SỬA (priority order)

```
module/lingq_sync/
├── sync.php                ← EDIT: GET response parse card.notes → cột 12 lingq_cards.csv
├── update_local.php        ← EDIT: load 3 nguồn (chunks/weak/mistakes) 1 lần đầu run,
│                                  build enriched notes per row, write cột 12 lingq_target.csv
├── notes_builder.php       ← NEW (optional, tách nếu update_local.php > 300 dòng):
│                                  export function build_enriched_notes(...), parse_mistakes_log(...),
│                                  truncate_to_max(...)
├── push.php                ← EDIT: diff_for_patch() thêm key 'notes' (merge_notes logic v2),
│                                  build_post_payload() thêm 'notes' cho CREATE, flag --force-overwrite-notes
├── config.example.php      ← EDIT: 5 key notes_*
└── README.md               ← EDIT: section "Phase J — enriched notes sync" (schema 12, source join, limit policy)

docs/
└── LINGQ_INTEGRATION.md    ← EDIT: mục 1 (schema 12), mục 2 (diff thêm notes), mục 7 hoặc 13 mới
                                  (notes limit verify result), backlog row J → done
```

### Schema bump

```
lingq_cards.csv + lingq_target.csv: 11 cột → 12 cột
Thêm cuối: notes  (escape \n thành literal \n; sort vẫn theo lingq_id asc)
```

Lần đầu chạy `sync.php` sau update code → log WARN "Header mismatch v1(11) → v2(12), rebuilding from server" → atomic rebuild. Đề xuất `sync.php` đọc CSV cũ TRƯỚC khi overwrite để preserve `first_seen` (map by lingq_id).

### UX expected

```
> module\lingq_sync\cron.bat
[1/4] sync.php       → pull, parse notes → lingq_cards.csv (157 rows, schema v2 12 cột)
[2/4] update_local.php
        Loaded: 67 chunks, 12 weak_words, 23 mistakes_log entries
        Built enriched notes: 89/157 rows have content
        Truncated: 3 rows (max 10000 chars)
        Wrote: lingq_target.csv (157 rows, 12 cột)
[3/4] push.php --apply --auto-confirm
        Plan: CREATE=0, UPDATE=82, DELETE=0
          Of which notes-changed=82, fragment-changed=0, hint-changed=0, tags-changed=0
        Apply... 82 OK / 0 fail
[4/4] sync.php       → refresh
```

---

## 5. Ví dụ dữ liệu thật

### Input row vocab_master VOC-20260519-002 (Schüler)

```csv
VOC-20260519-002,Schüler,Substantiv,"der Schüler, -",học sinh,Die Schule muss den Schülern Wissen vermitteln.,Nhà trường phải truyền đạt kiến thức cho học sinh.,Bildung,2026-05-19,1,SRC-001,tutor,B1;DTZ;Bildung;n-Deklination,Dat.Pl. = Schülern (n-Deklination Pl.). m. wort gốc Schüler; Pl. Schüler.
```

### Output target.notes (enriched, render bởi update_local.php)

```
[AI-sync 2026-05-19 | VOC-20260519-002]

## Grammar / Notes
Dat.Pl. = Schülern (n-Deklination Pl.). m. wort gốc Schüler; Pl. Schüler.

## Collocations
- den Schülern Wissen vermitteln (truyền đạt kiến thức cho học sinh)
- Schüler unterrichten (giảng dạy học sinh)
- die Schüler einer Klasse (học sinh của một lớp)

## Weak word
mistake_count: 2 — last 2026-05-15 — rule: n-Deklination Pl.

## Past mistakes
- 2026-05-15 (Grammar): viết "Schülers" thay vì "Schülern" (Dat.Pl. — n-Deklination)

## Cross-ref
- VOC-20260518-028 (vermitteln)
```

→ PATCH này lên LingQ field `notes`. Field `hint` giữ "học sinh" (bedeutung VI, Phase D không đụng).

---

## 6. Acceptance test

| # | Test | Expected |
|---|---|---|
| L0 | Verify notes limit | 5 curl test (250/1k/5k/20k/100k chars) → ghi kết quả vào docs. Bump `notes_max_chars` config. |
| 1 | Render enriched cho Schüler (server empty) | Sau cron: server notes = full markdown 5 section trên |
| 2 | Server có "Học hôm 17/5" (user viết tay) | Notes = `"Học hôm 17/5\n---\n[AI-sync ...] ## Grammar..."` (giữ user text) |
| 3 | Marker cũ ngày khác → REPLACE | User text giữ, marker block cũ thay bằng marker mới |
| 4 | Chạy 2 lần cùng ngày, vocab + chunks + weak + mistakes không đổi | Plan notes-changed=0 (idempotent) |
| 5 | Vocab_master.notes empty + 0 chunks + 0 weak + 0 mistakes | Target.notes = '' → patch xoá block marker (giữ user text nếu có) |
| 6 | Chỉ có 1 nguồn (vd chỉ vocab_master.notes có content) | Render chỉ section Grammar/Notes + marker prefix. KHÔNG render heading rỗng. |
| 7 | Add 1 row vào MISTAKES_LOG mention "Schüler" | Cron tiếp theo → notes-changed=1 cho VOC-20260519-002, plan PATCH |
| 8 | Add 1 chunk mới "Schüler-Lehrer-Verhältnis" | Cron tiếp theo → notes-changed=1, append vào section Collocations |
| 9 | Enriched notes vượt notes_max_chars | Truncate cuối + marker "... (truncated at N chars)" |
| 10 | Schema bump 11→12 lần đầu | sync.php WARN + rebuild. first_seen preserve (đọc CSV cũ trước rebuild). |
| 11 | Phase D regression x8 | TẤT CẢ test Phase D trong README vẫn PASS |
| 12 | POST card mới (CREATE) | Payload include enriched notes ngay từ POST đầu tiên |
| 13 | `notes_enrichment=false` config | Fallback: target.notes chỉ là `[AI-sync ...] <vocab_master.notes plain>` — không join 3 nguồn khác |
| 14 | `--force-overwrite-notes` + STDIN confirm "OVERWRITE-NOTES" | Override user text bỏ qua merge |
| 15 | Round-trip lossy | Chạy cron 2 lần liên tiếp → diff CSV không đổi |

---

## 7. Cấm đụng

- ❌ Sửa `data/03_unified/vocab_master.csv`, `data/chunks_master.csv`, `data/weak_words.csv`, `docs/ai/MISTAKES_LOG.md` — đều read-only.
- ❌ Sửa `data/01_ai_extracted/` archive.
- ❌ Tạo nhiều file PHP mới ngoài `notes_builder.php` (optional, nếu cần tách logic > 300 dòng).
- ❌ Extend `lingq_client.php` — reuse `updateCard()` generic.
- ❌ Đụng `status` / `extended_status` preservation Phase D — vẫn quy tắc PATCH không gửi status.
- ❌ Đụng hints array Phase F — chỉ append `notes` key vào patch sau hints logic chạy.
- ❌ Hardcode `notes_prefix` / `notes_max_chars` — đọc config.
- ❌ Hardcode endpoint URL / token.
- ❌ Bịa endpoint hoặc bịa limit — verify §2 + §5 trước khi code, đính kèm output curl.
- ❌ Override user notes mặc định — chỉ APPEND/REPLACE marker block. Override = `--force-overwrite-notes` + STDIN confirm.
- ❌ Suy diễn collocation/mistake từ AI — chỉ rút từ 3 file CSV/MD đã có thật. Section rỗng = skip heading, không bịa.
- ❌ Auto `git commit` / `git push` — Edit xong báo "edit xong, chờ review Cursor".
- ❌ Composer / vendor. Pure PHP stdlib.
- ❌ `php -l` syntax check — Windows mount treo. Verify bằng `php push.php --dry-run`.

---

## 8. Performance / scale

| Metric | Hiện tại (157 rows) | Dự kiến (3000 rows) |
|---|---|---|
| `update_local.php`: load 3 nguồn (chunks 67 + weak 12 + mistakes 23) | 1 lần đầu run, in-memory | scale tuyến tính, 3 nguồn ~ vài k rows → vẫn nhanh |
| `update_local.php`: build per row | 157 × O(chunks+weak+mistakes) ≈ 157 × 100 = 15k ops | 3000 × 5000 = 15M ops — vẫn < 5s PHP |
| `push.php`: API PATCH calls/run | bằng số rows notes-changed (avg 5-10/ngày sau init) | 20-50/ngày sau init |
| First-run mass PATCH | ~89 PATCH × 1.9s = 2.8 phút | ~2000 × 1.9s = 63 phút |
| Daily steady-state | ~10 PATCH × 1.9s = 20s | ~50 × 1.9s = 95s |

→ KHÔNG cần cron riêng. Reuse `cron.bat` 4-step 10:00 daily.
→ Reuse `cfg['sleep_ms']=1500` + `retry_429_backoff=[5,15,30,60,120]`.
→ Reuse `.ai-locks/lingq_push_running.lock` — KHÔNG tạo lock mới.

---

## 9. Format report (Claude Code paste cuối task)

```
=== LingQ Notes Sync Phase J v3 — implementation report ===

Files edited (line count old → new):
  - module/lingq_sync/sync.php             (<old> → <new>)
  - module/lingq_sync/update_local.php     (<old> → <new>)
  - module/lingq_sync/notes_builder.php    (NEW <line>)   [optional]
  - module/lingq_sync/push.php             (<old> → <new>)
  - module/lingq_sync/config.example.php   (+5 keys)
  - module/lingq_sync/README.md            (section "Phase J")
  - docs/LINGQ_INTEGRATION.md              (schema 12, limit verify log, backlog J done)

Files NOT touched:
  - data/* (all read-only)
  - module/lingq_sync/lingq_client.php
  - module/lingq_sync/cron.bat (reuse 4-step)

Notes limit verify (paste curl output):
  L1 250 chars   → HTTP 200, GET back 250
  L2 1000 chars  → HTTP <code>
  L3 5000 chars  → HTTP <code>
  L4 20000 chars → HTTP <code>
  L5 100000 chars → HTTP <code>
  → config notes_max_chars set to: <N>

Endpoint schema verify:
  GET single → field "notes": <yes/no>, type <string>
  GET list   → results[].notes present: <yes/no>
  PATCH multi (fragment+notes simultaneously): <ok/fail>

Acceptance test status (15 test):
  L0 limit verify:                 ✅/❌
  Test 1  enriched render empty:   ✅/❌
  Test 2  Case D user text append: ✅/❌
  Test 3  Case C replace marker:   ✅/❌
  Test 4  Case B idempotent:       ✅/❌
  Test 5  zero source:             ✅/❌
  Test 6  partial source:          ✅/❌
  Test 7  mistakes_log update:     ✅/❌
  Test 8  chunks update:           ✅/❌
  Test 9  truncate at max:         ✅/❌
  Test 10 schema bump rebuild:     ✅/❌
  Test 11 Phase D regression x8:   ✅/❌
  Test 12 POST CREATE notes:       ✅/❌
  Test 13 enrichment=false fallback: ✅/❌
  Test 14 --force-overwrite-notes: ✅/❌
  Test 15 round-trip lossy:        ✅/❌

Open question / blocker:
  - <vd: notes_max_chars verified = X — đề xuất bump config>
  - <vd: GET list omit notes? — phải fan-out single GET cho sync.php>
  - <vd: MISTAKES_LOG parse: regex hay markdown parser?>

Next action gợi ý:
  - Henry chạy `cron.bat` manual 1 lần, verify Test 1-3 trên LingQ mobile UI
  - Update CLAUDE.md root: schema 16 → 14 (mismatch ngoài scope)
```

---

## Open questions / gaps cho Henry trước handoff

1. **Schema mismatch CLAUDE.md** (16 cột) vs `data/README.md` (14 cột) vs real CSV (14). Prompt dùng 14. Đề xuất Henry fix 1-line CLAUDE.md sau Phase J.
2. **Playbook missing:** `knowledge-os/playbooks/how-i-integrate-external-api.md` không nằm trong workspace mount → Claude Code không đọc được. Cần symlink/copy.
3. **GET list field omission risk:** một số API (Stripe, Hubspot) drop field "lớn" khỏi list view. Test 2 §2 phải verify notes có trong GET list — nếu không → sync.php fan-out N GET single → tăng chi phí scale.
4. **MISTAKES_LOG parser:** file markdown format `## YYYY-MM-DD — Category — rule`. Đề xuất regex line-based đơn giản (split bằng `## ` heading) + extract content tới `## ` tiếp theo. KHÔNG dùng full markdown parser (overkill cho format fixed này).
5. **chunks_master match logic:** chỉ stripos lemma — sẽ match cả `Schülern` khi search `Schüler` (good). Nhưng cũng có thể false positive (vd `Mut` match `Mutter`). Cân nhắc thêm word boundary `\b` regex match. Trade-off: regex chậm hơn stripos. Đề xuất default stripos, flag config `notes_strict_chunk_match` → regex.
6. **Cross-ref discovery:** hiện tại chỉ parse regex `VOC-` trong notes. Có nên 2-way auto: nếu VOC-A reference VOC-B, có hiển thị "referenced by VOC-A" trong notes của VOC-B? — defer Phase J2.

---

## Handoff Claude Code (paste vào terminal)

```
Đọc docs/ai/tasks/LINGQ_NOTES_SYNC_PROMPT.md và làm theo.
```
