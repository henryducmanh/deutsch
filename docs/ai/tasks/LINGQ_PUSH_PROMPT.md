# LingQ Push — Phase D v1 (2-way sync, vocab_master làm source of truth)

> **Task ID:** `lingq_push_v1`
> **Người triển khai:** Claude Code (vai Implementer).
> **Stack:** PHP 7.4 local, extends module `module/lingq_sync/` (Phase C đã có).
> **Lock:** đã tạo `.ai-locks/lingq_push_prompt.lock` — Claude Code tạo lock riêng `.ai-locks/lingq_push_impl.lock` cho phase code.
> **Prerequisite:** Phase C đã chạy thành công, `data/lingq_cards.csv` có 2728 rows, cron daily 10:00 đang active.

---

## 1. End-user

**Henry** — lý do làm Phase D: LingQ tạo rác (word-by-word, không cụm), khó học. Quyết định:
- `data/03_unified/vocab_master.csv` = source of truth (B1 DTZ curate thủ công).
- LingQ chỉ là UI học (web + mobile review SRS), không phải nơi sinh data.
- Lần đầu (hôm nay) wipe sạch 2728 entries → push 57 từ trong vocab_master.
- Sau đó hàng ngày auto-sync 2 chiều: status từ LingQ (user review trên mobile), data field khác đè từ vocab_master.

---

## 2. Màn cuối cùng (definition of done)

### Files mới

```
module/lingq_sync/
├── update_local.php           ← MỚI: vocab_master → lingq_target.csv (no API)
├── push.php                   ← MỚI: lingq_target → LingQ server (POST/PATCH/DELETE)
├── lingq_client.php           ← EXTEND: thêm createCard, updateCard, deleteCard
├── cron.bat                   ← REWRITE: orchestrator 4 step (sync→update→push→sync)
└── README.md                  ← UPDATE: thêm section Phase D

data/
└── lingq_target.csv           ← MỚI: desired state, sinh từ vocab_master
```

### UX expected

**Step 1 — Manual generate target (no API):**
```
> C:\php\php74\php.exe module\lingq_sync\update_local.php
[LingQ Update Local] 2026-05-18 17:00:00
Read: data/03_unified/vocab_master.csv (57 rows)
Filter: rows có wort + bedeutung không rỗng → 57 valid
Wrote: data/lingq_target.csv (57 rows)
Done. Exit 0.
```

**Step 2 — Dry-run push:**
```
> C:\php\php74\php.exe module\lingq_sync\push.php
[LingQ Push] 2026-05-18 17:00:30 (--dry-run default)
Read target: data/lingq_target.csv (57 rows)
Read snapshot: data/lingq_cards.csv (2728 rows)

Plan:
  CREATE:   57   (target only — sẽ POST)
  UPDATE:    0   (cả 2 có, field khác → PATCH giữ status)
  DELETE: 2728   ⚠️  100.0% snapshot → sẽ XOÁ hết LingQ hiện tại

⚠️  Threshold abort: DELETE > 80% require --force-delete-all
⚠️  Cần --confirm-delete=2728 để xác nhận số chính xác

Dry run only. Exit 0.
```

**Step 3 — Live first push (manual, 1 lần duy nhất):**
```
> C:\php\php74\php.exe module\lingq_sync\push.php --apply --confirm-delete=2728 --force-delete-all
[LingQ Push] 2026-05-18 17:05:00 (LIVE)
Backup: data/lingq_cards_backup_2026-05-18_170500.csv (2728 rows)

DELETE 2728 entries...
  DELETE pk=633770686 ... HTTP 204 OK (412ms)
  DELETE pk=633770764 ... HTTP 204 OK (388ms)
  ...
  DELETE: 2728 OK / 0 fail

CREATE 57 entries...
  POST term=Entwicklung ... HTTP 201 pk=999900001 (552ms)
  POST term=Einfluss ... HTTP 201 pk=999900002 (471ms)
  ...
  CREATE: 57 OK / 0 fail

Refreshing snapshot...
  → Calling sync.php... 57 rows

Done in 14m 23s. Exit 0.
Log: module/lingq_sync/logs/2026-05-18.log
```

**Step 4 — Daily auto (cron 10:00 đã đăng ký):**
```
cron.bat (mới, 4 step liên tiếp) chạy daily 10:00, log ra cron_<date>.log:
  10:00:00  sync.php          → snapshot mới (lấy status user vừa review đêm qua)
  10:00:30  update_local.php  → regenerate target từ vocab_master mới nhất
  10:00:31  push.php --apply --auto-confirm  → apply diff
  10:05:00  sync.php          → post-push refresh
```

`--auto-confirm` trong cron mode: skip interactive prompt, NHƯNG vẫn:
- Abort nếu DELETE > 20% (threshold cron, thấp hơn manual 80%)
- Log WARNING + abort, không tự xoá hàng loạt khi vocab_master accidentally empty

---

## 3. Ví dụ dữ liệu thật

### `data/lingq_target.csv` schema (mới)

Match 11 cột với `lingq_cards.csv` để dễ diff:

```csv
lingq_id,term,fragment,hint,status,extended_status,tags,importance,last_studied_correct,first_seen,last_synced
,Entwicklung,"Technologische Entwicklungen verändern die Welt.","sự phát triển",1,0,"wortart:Substantiv;level:B1;thema:Technologie;voc:VOC-20260518-001",0,,2026-05-18,
,Einfluss,"Diese Erfindung hat großen Einfluss auf die Gesellschaft.","sự ảnh hưởng",1,0,"wortart:Substantiv;level:B1;thema:Technologie;voc:VOC-20260518-002",0,,2026-05-18,
```

- `lingq_id` rỗng = chưa có trên server (sẽ POST).
- `status=1` cho mọi row mới (theo design: status từ snapshot khi PATCH, từ target khi POST).
- `tags` concat bằng `;`, format `key:value`. Khi POST sẽ split + push lên LingQ.
- `voc:VOC-XXX` để tracking row vocab_master nguồn — cho debug 2-way mapping.

### LingQ API call examples

**POST tạo card mới:**
```
POST /api/v2/de/cards/
Authorization: Token <key>
Content-Type: application/json

{
  "term": "entwicklung",
  "fragment": "Technologische Entwicklungen verändern die Welt.",
  "hint": "sự phát triển",
  "status": 1,
  "extended_status": 0,
  "tags": ["wortart:Substantiv", "level:B1", "thema:Technologie", "voc:VOC-20260518-001"]
}
→ 201 Created
{"pk": 999900001, "term": "entwicklung", ...}
```

**PATCH update hint/fragment (KHÔNG đụng status):**
```
PATCH /api/v2/de/cards/999900001/
{
  "fragment": "Die technologische Entwicklung ist rasant.",
  "hint": "sự phát triển; sự phát đạt",
  "tags": ["wortart:Substantiv", "level:B1", "thema:Technologie;Wirtschaft", "voc:VOC-20260518-001"]
}
→ 200 OK
```

**DELETE:**
```
DELETE /api/v2/de/cards/633770686/
→ 204 No Content
```

### Diff matching algorithm

Key match: `term_lower = strtolower(trim(term))`.

| Trong target? | Trong snapshot? | Action |
|---|---|---|
| ✓ | ✗ | **CREATE** — POST với status=1 từ target |
| ✓ | ✓ | **UPDATE nếu khác** — PATCH chỉ field thay đổi (fragment/hint/tags), KHÔNG đụng status |
| ✗ | ✓ | **DELETE** — DELETE bằng `lingq_id` từ snapshot |
| ✗ | ✗ | (không xảy ra) |

UPDATE comparison: chỉ trigger PATCH khi 1 trong (fragment, hint, tags_normalized) khác. Status không bao giờ đè.

---

## 4. Acceptance tests

1. **Generate target (no API):**
   ```
   C:\php\php74\php.exe module\lingq_sync\update_local.php
   ```
   → exit 0, `data/lingq_target.csv` có 57 rows, 11 cột, tags đúng format.

2. **Dry-run push lần 1:**
   ```
   C:\php\php74\php.exe module\lingq_sync\push.php
   ```
   → in plan `CREATE: 57, UPDATE: 0, DELETE: 2728`, abort vì threshold + chưa có flag.

3. **Test POST 1 card (single-card mode để verify LingQ API trước khi mass push):**
   ```
   C:\php\php74\php.exe module\lingq_sync\push.php --apply --limit=1 --skip-delete
   ```
   → POST 1 từ đầu trong target, check trên `lingq.com/learn/de/web/vocabulary` UI có thấy không. Verify mapping: term/fragment/hint/tags hiện đúng.

4. **Live first push full (chỉ chạy khi test #3 OK):**
   ```
   C:\php\php74\php.exe module\lingq_sync\push.php --apply --confirm-delete=2728 --force-delete-all
   ```
   → exit 0, backup file tồn tại, sau khi xong LingQ UI chỉ còn 57 từ.

5. **Idempotent run #2:**
   ```
   C:\php\php74\php.exe module\lingq_sync\push.php --apply
   ```
   → `CREATE: 0, UPDATE: 0, DELETE: 0`.

6. **vocab_master thay đổi → re-sync:**
   - Thêm 1 row vào `vocab_master.csv` (e.g. `Beispielwort`).
   - Chạy `update_local.php` → target có 58 rows.
   - Chạy `push.php` dry-run → plan `CREATE: 1, UPDATE: 0, DELETE: 0`.
   - Apply → 1 từ mới xuất hiện trên LingQ UI.

7. **Status preservation:**
   - Trên LingQ UI nâng status từ 1 → 3 cho từ "Entwicklung".
   - Chạy cron orchestrator (hoặc 4 step manual).
   - `lingq_cards.csv` row Entwicklung có `status=3`.
   - `lingq_target.csv` row Entwicklung có `status=1` (vẫn từ vocab_master).
   - Sau push: API call PATCH (nếu fragment/hint không đổi thì skip), status trên server **giữ nguyên 3**.

8. **Safety threshold (auto-confirm mode):**
   - Đổi `vocab_master.csv` còn 5 rows (giả lập accident).
   - Chạy `push.php --apply --auto-confirm`.
   - → Abort với error "DELETE 52/57 = 91% > 20% threshold (cron mode)". Exit 1.

9. **Cron orchestrator end-to-end:**
   ```
   schtasks /run /tn "LingQ Sync Daily"
   ```
   → log `cron_2026-05-18.log` có 4 phase, exit 0.

---

## 5. Cấm đụng

- ❌ Xoá / sửa `data/03_unified/vocab_master.csv` — read-only ở phase này.
- ❌ Xoá `data/lingq_cards.csv` (Phase C output) — chỉ `sync.php` ghi.
- ❌ Tự ý chạy `push.php --apply` mà không có flag confirm/force khi DELETE > threshold.
- ❌ Hard-code threshold/limit trong code — đưa lên config (`config.php` thêm key `push_thresholds`).
- ❌ `git commit / push` — Edit xong báo "edit xong, chờ review Cursor".
- ❌ Bịa LingQ API endpoint nếu chưa test thật. Nếu API trả 400/422 khi POST → ghi log INFO ra response body, không retry vô tận.
- ❌ Đè status từ target khi PATCH — luôn dùng status từ snapshot. Code phải có comment giải thích.
- ❌ Xoá `data/lingq_cards_backup_*.csv` — giữ tất cả backup để recovery (retention không thuộc scope này).

---

## 6. Performance / scale

- **Lần đầu:** 2728 DELETE + 57 POST = 2785 request × ~500ms avg = ~24 phút. Acceptable cho 1-time operation.
- **Daily incremental:** thường 0-5 PATCH + 0-2 CREATE/DELETE = vài giây.
- **Rate limit guard:** `sleep_ms=500` giữa request. Cấu hình trong `config.php`.
- **Retry policy:** 3 lần, backoff 1s/3s/9s cho `5xx`. `4xx` fail nhanh, log response body.
- **Atomic CSV write:** giữ pattern Phase C (.tmp → rename).
- **Backup before destructive op:** copy `lingq_cards.csv` → `lingq_cards_backup_YYYY-MM-DD_HHMMSS.csv` trước khi bắt đầu DELETE phase.

**Threshold config (`config.php` thêm):**
```php
'push_thresholds' => [
    'manual_max_delete_pct' => 80,    // manual mode cần --force-delete-all nếu > 80%
    'auto_max_delete_pct'   => 20,    // cron mode abort nếu > 20%
    'auto_max_delete_abs'   => 50,    // OR > 50 entries absolute (whichever first)
],
```

---

## 7. Format report (Claude Code in ra cuối session)

```
✅ LingQ Push module v1 (Phase D) — done

Files created:
- module/lingq_sync/update_local.php          (XX dòng)
- module/lingq_sync/push.php                  (XXX dòng)
- module/lingq_sync/lingq_client.php          (+XX dòng cho create/update/delete)
- module/lingq_sync/cron.bat                  (REWRITE — orchestrator 4 step)
- module/lingq_sync/README.md                 (+section Phase D)
- data/lingq_target.csv                       (header only, populate khi user chạy update_local)

Verified by reading code:
- [x] Diff logic: status từ snapshot khi PATCH, từ target khi POST
- [x] Backup .csv trước DELETE phase
- [x] Threshold 80% manual / 20% cron với --force-delete-all override
- [x] --dry-run default, require --apply để chạy thật
- [x] --confirm-delete=N require exact number match
- [x] Atomic CSV write (.tmp → rename)
- [x] Rate limit 500ms, retry 3x backoff
- [x] Cron orchestrator chạy 4 step tuần tự, abort nếu step trước fail
- [ ] Live API POST/PATCH/DELETE — chưa test, cần user chạy step #3 trong acceptance test

To activate (cho Henry chạy):
1. Test single POST trước:
   C:\php\php74\php.exe module\lingq_sync\update_local.php
   C:\php\php74\php.exe module\lingq_sync\push.php --apply --limit=1 --skip-delete
   → Check lingq.com vocabulary có thấy từ mới + mapping đúng?

2. Nếu OK → first wipe + push full:
   C:\php\php74\php.exe module\lingq_sync\push.php --apply --confirm-delete=2728 --force-delete-all

3. Verify trên LingQ UI: chỉ còn 57 từ vocab_master.

4. Cron đã có sẵn ("LingQ Sync Daily" daily 10:00), cron.bat đã update thành orchestrator,
   không cần register lại schtasks.

Lock cleared: .ai-locks/lingq_push_impl.lock removed.
Pending: Cursor diff review.
```

---

## Phụ lục — note kỹ thuật

### Tags format

LingQ API tags là JSON array of strings. CSV concat dùng `;`:
```
"wortart:Substantiv;level:B1;thema:Technologie;voc:VOC-20260518-001"
```

Khi POST/PATCH: `explode(';', $tags)` → array → JSON.
Khi compare snapshot: normalize both về set, compare set equality.

### Hint language

LingQ API `hint` field có thể là plain string HOẶC object `{language, text}` tuỳ API version. Test khi POST đầu tiên xem response trả về format gì:
- Nếu plain string OK → giữ plain.
- Nếu yêu cầu object → wrap `{"language": "vi", "text": $bedeutung}`.

Log response body của POST đầu tiên để debug.

### Fragment có cần lesson_id?

Community forum nói LingQ v2 POST cards đôi khi yêu cầu `context.lesson` hoặc `fragment` gắn lesson_id. Test:
- Lần đầu thử `fragment` plain text (= beispiel từ vocab_master).
- Nếu HTTP 400 với message về lesson → fallback: omit fragment, chỉ POST term + hint + tags. Log WARNING cho user.
- Đừng auto-create dummy lesson (out of scope).

### Idempotency & race

Nếu user chạy cron 10:00 và đồng thời chạy `update_local.php` manual:
- Cron orchestrator chạy `update_local.php` ở step 2 → có lock file để tránh race.
- Lock: `.ai-locks/lingq_push_running.lock` (tạo khi push.php start, xoá khi exit).
- Nếu lock tồn tại + age < 30 phút → push.php exit với "Another push in progress".

### Recovery

Nếu push.php fail giữa chừng:
- `lingq_cards.csv` không bị corrupt (atomic write chỉ ở update_local/sync).
- Backup `lingq_cards_backup_*.csv` luôn có trước DELETE phase.
- Chạy lại push.php (idempotent) sẽ pick up từ state hiện tại của server (sync trước khi diff).

### Logging

Log per request:
```
[2026-05-18 17:05:01] INFO  DELETE pk=633770686 HTTP 204 412ms
[2026-05-18 17:05:02] INFO  DELETE pk=633770764 HTTP 204 388ms
[2026-05-18 17:05:03] WARN  DELETE pk=633770801 HTTP 404 (already deleted?) — skip
[2026-05-18 17:05:04] ERROR POST term=Beispiel HTTP 422 body={"detail":"lesson required"} — skip row, continue
```

Summary cuối log:
```
[2026-05-18 17:18:24] SUMMARY DELETE: 2728 OK / 0 fail. CREATE: 56 OK / 1 fail (Beispiel). UPDATE: 0.
```
