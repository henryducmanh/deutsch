# DEUTSCH_WEB_VOCAB — Phase 1+2+3 Spec
> Handoff Claude Code. Đọc file này và làm theo từng phase, commit từng phase riêng.
> Tạo lock `.ai-locks/deutsch_web_vocab_impl.lock` trước khi bắt đầu.

---

## 1. End-user
Solo user học DTZ B1 dùng `deutsch.twv.app` (PHP 7.4 / MySQL 5.7 / cPanel shared).
Bộ não dữ liệu ở máy Windows local (`C:\twv_share\app\deutsch`).

---

## 2. Màn cuối cùng (Definition of Done)

| Phase | Done khi... |
|---|---|
| **P1** | Chạy `php module/deutsch_web_sync/push_vocab.php --dry-run` → log "X rows sẽ upsert". Chạy live → bảng `vocab` trên DB online có ≥ 500 rows. |
| **P2** | Mở `https://deutsch.twv.app/lesson/4.30` → panel phải hiện danh sách từ vựng với nghĩa **lấy từ DB** (không phải hardcode JSON). Nếu DB chưa có từ nào cho bài đó → panel vẫn render đúng (empty state "Chưa có từ vựng cho bài này"). |
| **P3** | Click tab "**Neu wort**" → thấy danh sách từ lạ trong bài (chưa có trong DB). Click "Thêm" + điền nghĩa → từ xuất hiện trong bảng `vocab` (curated=0). Chạy `pull_vocab.php` → từ đó append vào `vocab_master.csv`. |

---

## 3. Ví dụ dữ liệu thật

### vocab_master.csv (16 cột, 1926 rows):
```
id,wort,wortart,formen,bedeutung,beispiel,uebersetzung,thema,lerndatum,level,quelle,source_type,tags,notes,parent_id,form_type
VOC-20260518-001,Entwicklung,Substantiv,"die Entwicklung, -en",sự phát triển,...,Technologie,2026-05-18,1,SRC-001,tutor,B1;DTZ;Technologie,,
VOC-20260518-002,Einfluss,Substantiv,"die Einfluss, -üsse",sự ảnh hưởng,...
```

### Lesson JSON hiện tại (`lessons/4.30.json`) — KHÔNG thay đổi format:
```json
{
  "lesson_id": "4.30",
  "vocab": [
    { "w": "Zeitersparnis", "art": "die · Subst.", "m": "tiết kiệm thời gian", "lv": "new", "vocab_id": null },
    { "w": "flexibel", "art": "Adj.", "m": "linh hoạt", "lv": "ok", "vocab_id": null }
  ],
  "aussagen": [...],
  "transcript": [
    { "label": "Aussage 1", "text": "Also ich finde Homeoffice super. Man spart viel Zeit..." }
  ]
}
```

### API examples:
```
GET /api/vocab?words=Zeitersparnis,flexibel,Ablenkung
→ 200 { "vocab": [
    { "w": "Zeitersparnis", "wort_key": "zeitersparnis", "art": "die · Subst.", "bedeutung": "tiết kiệm thời gian", "level": 1, "vocab_id": "VOC-20260518-010" },
    { "w": "flexibel", "wort_key": "flexibel", "art": "Adj.", "bedeutung": "linh hoạt", "level": 2, "vocab_id": "VOC-20260518-020" }
  ]}

POST /api/vocab  (session auth)
body: { "wort": "Arbeitszeitgestaltung", "bedeutung": "sắp xếp thời gian làm việc", "wortart": "Substantiv", "artikel": "die", "source_lesson": "4.30" }
→ 200 { "ok": true, "id": 523, "wort_key": "arbeitszeitgestaltung" }

POST /api/vocab/bulk  (Bearer auth)
body: { "rows": [ { "vocab_id": "VOC-...", "wort": "Entwicklung", "wortart": "Substantiv", "artikel": "die", "bedeutung": "sự phát triển", "niveau": "B1", "level": 1, "thema": "Technologie", "tags": "B1;DTZ" } ] }
→ 200 { "upserted": 48, "skipped": 12 }

GET /api/vocab/new?since=2026-05-30T00:00:00Z  (Bearer auth)
→ 200 { "count": 3, "vocab": [
    { "id": 523, "wort": "Arbeitszeitgestaltung", "wort_key": "arbeitszeitgestaltung", "bedeutung": "sắp xếp thời gian làm việc", "wortart": "Substantiv", "artikel": "die", "source": "4.30", "created_at": "2026-05-30T10:30:00Z" }
  ]}
```

---

## 4. Acceptance Tests

### Phase 1 — push_vocab.php
- [ ] `php push_vocab.php --dry-run` → log "X rows sẽ upsert" + exit 0, KHÔNG ghi DB
- [ ] `php push_vocab.php --limit=5` → upsert đúng 5 rows vào bảng `vocab`
- [ ] Chạy lại lần 2 → 0 upsert mới (idempotent theo `wort_key`)
- [ ] Row trong DB: `wort_key` = lowercase(wort), `curated=1`, `vocab_id` = từ CSV
- [ ] `wc -l vocab_master.csv` vs count DB: số rows khớp (trừ header + rows trùng wort_key)

### Phase 2 — vocab panel từ DB
- [ ] Mở `/lesson/4.30` → panel phải hiện ≥ 8 từ nếu đã push vocab (sau P1)
- [ ] Nghĩa trong panel = từ DB (khác JSON fallback nếu có sửa)
- [ ] Nếu DB không có từ nào khớp → panel hiện "Chưa có từ vựng" (không crash)
- [ ] Cycle badge 1→2→3 vẫn hoạt động (word_mark event vẫn POST /track)
- [ ] Tắt JS console error

### Phase 3 — Neu wort tab
- [ ] Click tab "**Neu wort**" → thấy ≥ 1 từ lạ từ lesson text (ví dụ "Arbeitszeitgestaltung" trong 4.30)
- [ ] Ô "Thêm": nhập nghĩa + click "Thêm" → word xuất hiện ngay trong tab "Alle Wörter" (refresh panel)
- [ ] DB `vocab`: row mới có `curated=0`, `source=lesson_id`, `wortart` và `artikel` đã điền nếu user nhập
- [ ] `php pull_vocab.php --dry-run` → thấy từ vừa add trong plan
- [ ] `php pull_vocab.php` → row mới append vào `output/drills/vocab_new_web.csv` (KHÔNG trực tiếp vào vocab_master — user curate thủ công)

### Global
- [ ] KHÔNG break flow đăng nhập / Hören drill / score check / ack event hiện có
- [ ] KHÔNG đụng `module/lingq_sync/`, `data/03_unified/vocab_master.csv` (read-only cho push)

---

## 5. Cấm đụng
- `module/lingq_sync/` — không liên quan
- `data/03_unified/vocab_master.csv` — **read-only** (push_vocab.php chỉ đọc)
- `module/deutsch_web_sync/staging/` — chỉ pull_vocab.php ghi vào `output/drills/vocab_new_web.csv`
- `module/deutsch_web/lib/auth.php`, `lib/db.php` (chỉ add function, KHÔNG xóa existing)
- Bảng `users`, `events` — schema không đổi
- KHÔNG tự `git commit` / `git push` — chỉ Edit files, báo "edit xong, chờ review Cursor"
- KHÔNG tự `--apply` mass operation

---

## 6. Performance / Scale

| Điểm | Spec |
|---|---|
| `vocab` table | ~2000 rows (vocab_master) + web additions. Index trên `wort_key` đủ. |
| `GET /api/vocab?words=...` | ≤ 50 words/request (1 lesson). Dùng `WHERE wort_key IN (?)` 1 query. |
| `POST /api/vocab/bulk` | Batch ≤ 100 rows/request. push_vocab.php chia chunk nếu cần. |
| push_vocab.php | 1926 rows chia chunk 100 = 20 requests. Sleep 0.1s/chunk. |
| pull_vocab.php | `since` timestamp filter. ≤ 200 rows/pull typical. |
| Rate limit | Không có rate limit đặc biệt (1 user, cron 1 lần/ngày). |

---

## 7. Files cần tạo / sửa

### DB Migration (tạo mới)
```
module/deutsch_web/migrations/002_vocab.sql
```

### Server — API (tạo mới)
```
module/deutsch_web/api/vocab.php     — GET /api/vocab?words=...  (session)
                                        POST /api/vocab           (session)
                                        POST /api/vocab/bulk      (Bearer)
                                        GET /api/vocab/new?since= (Bearer)
```

### Server — Router update (sửa)
```
module/deutsch_web/public/index.php  — thêm routes /api/vocab/* vào route_api()
```

### Frontend (sửa)
```
module/deutsch_web/public/assets/drill.js   — Phase 2: load vocab từ API; Phase 3: tab Neu wort
module/deutsch_web/public/assets/drill.css  — style tab Neu wort + add form
```

### Local sync (tạo mới)
```
module/deutsch_web_sync/push_vocab.php    — đọc vocab_master.csv → POST /api/vocab/bulk
module/deutsch_web_sync/pull_vocab.php    — GET /api/vocab/new → ghi output/drills/vocab_new_web.csv
```

### Local sync — config + cron (sửa)
```
module/deutsch_web_sync/config.example.php  — thêm key: vocab_csv, vocab_new_output, last_vocab_pull
module/deutsch_web_sync/config.php          — (user tự copy + điền — KHÔNG commit)
module/deutsch_web_sync/cron.bat            — thêm push_vocab + pull_vocab daily
```

---

## 8. Schema DB — vocab table

```sql
-- migrations/002_vocab.sql
CREATE TABLE IF NOT EXISTS vocab (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  vocab_id    VARCHAR(64)  NULL,
  wort        VARCHAR(200) NOT NULL,
  wort_key    VARCHAR(200) NOT NULL,
  wortart     VARCHAR(50)  NULL,
  artikel     VARCHAR(10)  NULL,
  bedeutung   TEXT         NULL,
  niveau      VARCHAR(10)  NULL DEFAULT 'B1',
  level       TINYINT      NOT NULL DEFAULT 1,
  thema       VARCHAR(100) NULL,
  tags        VARCHAR(500) NULL,
  source      VARCHAR(200) NULL,
  curated     TINYINT      NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wort_key (wort_key),
  INDEX idx_vocab_id (vocab_id),
  INDEX idx_curated_created (curated, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Mapping từ vocab_master.csv:**
| CSV column | DB column | Note |
|---|---|---|
| `id` | `vocab_id` | vd "VOC-20260518-001" |
| `wort` | `wort` + lowercase → `wort_key` | |
| `wortart` | `wortart` | |
| `formen` | parse → `artikel` | extract "der/die/das" từ đầu `formen` field |
| `bedeutung` | `bedeutung` | |
| `thema` | `thema` | |
| `level` | `level` | int 1-4 (CSV có thể là string) |
| `tags` | `tags` | |
| (const) | `curated=1` | tất cả rows từ vocab_master |

---

## 9. drill.js — Phase 2 logic

```
Page load:
  1. Lấy list words từ LESSON.vocab (field `w`)
  2. fetch('/api/vocab?words=' + encodeURIComponent(words.join(',')))
  3. Nếu thành công: merge kết quả vào wordData (DB overrides JSON)
  4. Render panel với data đã merge
  5. Nếu fetch fail (offline/401): fallback sang LESSON.vocab gốc (có sẵn trong JSON)
```

---

## 10. drill.js — Phase 3 logic: tab "Neu wort"

```
Sau khi fetch vocab từ DB (Phase 2):
  1. Parse all text tokens từ:
     - LESSON.aussagen[*].options[*].text
     - LESSON.transcript[*].text
  2. Lọc "candidate unknown words":
     - Token bắt đầu chữ hoa (German Substantiv) HOẶC length > 6
     - Không có trong known_words (wort_key set từ DB response)
     - Không phải stop-words (list dưới)
     - Unique, sort alpha
  3. Tab "Neu wort" hiện list này
  4. Mỗi item: [Wort] [input: Nghĩa] [Wortart dropdown: Subst./Verb/Adj.] [Thêm]
  5. Click "Thêm": POST /api/vocab → thêm vào DB → item chuyển sang "Alle Wörter" tab

German stop-words tối thiểu (mở rộng sau):
  die, der, das, ein, eine, ist, sind, hat, haben, wird, werden, und, oder, aber,
  mit, für, auf, in, an, bei, von, zu, aus, über, unter, nach, vor, durch, um,
  ich, du, er, sie, es, wir, ihr, sie, Sie, mich, mir, sich, uns, euch,
  nicht, auch, noch, schon, sehr, so, wie, dass, wenn, weil, ob, als,
  mehr, viel, gut, groß, klein, alt, neu, man, kann, muss, darf, soll
```

---

## 11. push_vocab.php — skeleton logic

```
1. Đọc config.php (api_base, api_key, vocab_csv)
2. Parse vocab_master.csv (skip header + comment rows)
3. Map mỗi row → API payload object (xem bảng mapping §8)
4. Chia chunk 100 rows
5. POST /api/vocab/bulk (Bearer) mỗi chunk
   - --dry-run: chỉ log plan, KHÔNG POST
   - --limit=N: chỉ process N rows đầu
6. Log: upserted / skipped / errors
7. Ghi state/last_push.json (timestamp)
```

---

## 12. pull_vocab.php — skeleton logic

```
1. Đọc config.php (api_base, api_key, vocab_new_output)
2. Đọc state/last_vocab_pull.json → since timestamp (default 1970)
3. GET /api/vocab/new?since=<since> (Bearer)
4. Nếu count=0 → log "Không có từ mới" → exit 0
5. Append vào output/drills/vocab_new_web.csv:
   header: web_id,wort,wort_key,bedeutung,wortart,artikel,source_lesson,created_at
6. Ghi state/last_vocab_pull.json = max(created_at) vừa pull
7. --dry-run: log plan, KHÔNG ghi file
```

Output file `vocab_new_web.csv` là **staging** — user review trong Cowork rồi mới import vào `vocab_master.csv`.

---

## Format Report Claude Code in cuối

```
=== DEUTSCH_WEB_VOCAB DONE ===
Phase 1 push_vocab.php:
  - Files tạo: module/deutsch_web_sync/push_vocab.php
  - Migration: module/deutsch_web/migrations/002_vocab.sql
  - Test: php push_vocab.php --limit=5 → ...

Phase 2 vocab panel từ DB:
  - Files sửa: drill.js, drill.css, index.php (routes)
  - Files tạo: api/vocab.php
  - Test: curl /api/vocab?words=Zeitersparnis → ...

Phase 3 Neu wort tab:
  - Files sửa: drill.js, drill.css
  - Files tạo: deutsch_web_sync/pull_vocab.php
  - Test: click tab → thấy từ lạ → add → check DB

Cần user làm sau khi review Cursor:
  1. git commit + push
  2. Server: git pull + rsync
  3. Server: php scripts/migrate.php (tạo bảng vocab)
  4. Local: cập nhật config.php (thêm vocab_csv path)
  5. Local: php push_vocab.php --dry-run → review → live
  6. Xóa debug_auth.php trên server nếu chưa xóa
```
