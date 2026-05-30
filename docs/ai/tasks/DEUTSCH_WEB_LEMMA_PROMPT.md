# DEUTSCH_WEB_LEMMA — Từ gốc + biến thể (Lemma & Inflected Forms)
> Handoff Claude Code. Đọc file này và làm theo. Lock: `.ai-locks/deutsch_web_lemma.lock`
> Đọc thêm: `docs/ai/DECISIONS.md` §DD-20260527-005 (schema parent_id/form_type đã thiết kế)

---

## 1. End-user / Bài toán

Người học tiếng Đức gặp "Fähigkeiten" trong bài Hören. Từ này là biến thể NOM.PL của
lemma "Fähigkeit". Hệ thống hiện tại không nhận ra mối liên hệ đó.

**Kết quả mong muốn sau feature này:**
- "Nền vàng" highlight **Fähigkeiten** (dạng nhạt hơn) và link về lemma Fähigkeit ở panel
- Panel "Alle Wörter" hiện: `Fähigkeit · die Subst. · khả năng` + badge con `Fähigkeiten (NOM.PL)`
- Tab "Neu wort": nếu lemma đã có trong DB → hiện `Fähigkeiten → Fähigkeit ?` thay vì "+ Queue" đơn thuần
- Khi queue biến thể chưa có → DB lưu với `parent_id` trỏ về lemma

---

## 2. Màn cuối cùng (Definition of Done)

| Test | Pass khi |
|---|---|
| Mở `/lesson/4.31` → bật "Nền vàng" | "Fähigkeiten" trong đề được highlight màu nhạt (form-mark) |
| Click "Fähigkeiten" trong đề | Panel scroll đến "Fähigkeit", badge NOM.PL hiện dưới lemma |
| Tab "Neu wort" | Từ có lemma đã biết hiện nhóm riêng "Biến thể đã biết" với link về lemma |
| DB vocab | Row biến thể có `parent_id = id_cua_lemma`, `form_type = 'NOM.PL'` |
| `GET /api/vocab/forms?words=Fähigkeiten,automatisiert` | Trả `[{form, lemma_key, art, bedeutung, form_type}]` |

---

## 3. Ví dụ dữ liệu thật

### vocab_master.csv đã có 2 cột cuối:
```
id,wort,...,parent_id,form_type
VOC-20260518-010,Fähigkeit,...,,          ← lemma (parent_id rỗng)
VOC-20260518-010-NOM.PL,Fähigkeiten,...,VOC-20260518-010,NOM.PL  ← biến thể
```

### DB vocab table cần thêm 2 cột (migration 003):
```sql
ALTER TABLE vocab ADD COLUMN parent_id INT NULL DEFAULT NULL;
ALTER TABLE vocab ADD COLUMN form_type VARCHAR(20) NULL DEFAULT NULL;
ALTER TABLE vocab ADD CONSTRAINT fk_vocab_parent FOREIGN KEY (parent_id)
  REFERENCES vocab(id) ON DELETE SET NULL;
ALTER TABLE vocab ADD INDEX idx_parent (parent_id);
```

### API response:
```json
GET /api/vocab/forms?words=Fähigkeiten,automatisiert,höheren

{
  "forms": [
    {
      "form": "Fähigkeiten",
      "form_key": "fähigkeiten",
      "form_type": "NOM.PL",
      "lemma": "Fähigkeit",
      "lemma_key": "fähigkeit",
      "lemma_id": 45,
      "art": "die · Subst.",
      "bedeutung": "khả năng, năng lực"
    },
    {
      "form": "automatisiert",
      "form_key": "automatisiert",
      "form_type": "PART.II",
      "lemma": "automatisieren",
      "lemma_key": "automatisieren",
      "lemma_id": 123,
      "art": "Verb",
      "bedeutung": "tự động hóa"
    }
  ],
  "unknown": ["höheren"]
}
```

---

## 4. DB Migration (003_lemma.sql)

```sql
-- 003_lemma.sql — Thêm parent_id + form_type cho quan hệ lemma→biến thể.
-- Idempotent: dùng IF NOT EXISTS / IF COLUMN NOT EXISTS (MySQL 5.7 dùng INFORMATION_SCHEMA check).

-- Thêm cột parent_id (FK tự tham chiếu vocab.id)
ALTER TABLE vocab ADD COLUMN IF NOT EXISTS
  parent_id INT NULL DEFAULT NULL COMMENT 'FK → vocab.id của lemma (NULL = chính là lemma)';

ALTER TABLE vocab ADD COLUMN IF NOT EXISTS
  form_type VARCHAR(20) NULL DEFAULT NULL
  COMMENT 'Mã biến cách: NOM.SG/NOM.PL/GEN.SG/GEN.PL/DAT.SG/DAT.PL/AKK.SG/AKK.PL (Subst.); PRAET/PERF/KONJ (Verb); KOMP/SUP (Adj./Adv.)';

-- Index để tìm nhanh tất cả biến thể của 1 lemma
ALTER TABLE vocab ADD INDEX IF NOT EXISTS idx_parent_id (parent_id);
```

> **Note MySQL 5.7**: `ADD COLUMN IF NOT EXISTS` và `ADD INDEX IF NOT EXISTS` không hỗ trợ trực tiếp.
> Dùng stored procedure hoặc kiểm tra INFORMATION_SCHEMA trước khi ALTER. Viết idempotent-safe.

---

## 5. API mới: GET /api/vocab/forms

**File:** `module/deutsch_web/api/vocab.php` — thêm function `api_vocab_forms()`
**Route (index.php):** `GET /api/vocab/forms` → `api_vocab_forms()`
**Auth:** session hoặc Bearer (như api_vocab_guard)

Logic:
```
1. Parse ?words=w1,w2,... (≤ 100 words, lowercase = wort_key)
2. Query 1: SELECT id, wort, wort_key, form_type, parent_id, wortart, artikel, bedeutung
            FROM vocab WHERE wort_key IN (?) AND parent_id IS NOT NULL
   → tìm các từ đã là biến thể đã biết

3. Với những wort_key chưa tìm thấy ở Query 1:
   → Trả vào "unknown" list (frontend xử lý)

4. Với những wort_key tìm thấy (có parent_id):
   → Query 2: lấy thông tin lemma: SELECT id,wort,wort_key,wortart,artikel,bedeutung
              FROM vocab WHERE id IN (?) [parent_id list]

5. Build response:
   forms: [{form, form_key, form_type, lemma, lemma_key, lemma_id, art, bedeutung}]
   unknown: [wort_key không match]
```

---

## 6. push_vocab.php — Cập nhật để push parent_id + form_type

`vocab_master.csv` đã có cột `parent_id` và `form_type`.

Khi push:
- Nếu row có `parent_id` (vd "VOC-20260518-010") → tìm `vocab.id` ứng với `vocab_id = 'VOC-20260518-010'`
  → set `parent_id = <db_id>` trong INSERT/UPDATE
- Nếu `parent_id` rỗng → set NULL

Thứ tự push: **lemma trước, biến thể sau** (tránh FK constraint fail).
- Lần 1: push tất cả rows có `parent_id` rỗng (lemma)
- Lần 2: push tất cả rows có `parent_id` (biến thể)

---

## 7. drill.js — Phase 4: Form Recognition

### 7.1 loadVocabFromDB() — thêm bước 3: fetch forms

```
Sau khi fetch DB vocab + queued words:
  - Lấy tất cả tokens từ lesson text (options + transcript)
    mà CHƯA có trong knownKeys
  - Gọi GET /api/vocab/forms?words=<unmatched_tokens>
  - Kết quả: build formMap {form_key → {lemma_key, lemma_w, form_type, art, bedeutung}}
  - Lưu formMap ở module scope
```

### 7.2 injectMarks() — 2 loại highlight

```
Pass 1 (như cũ): exact match vocabData → <span class="vocab-mark" data-word="...">
Pass 2 (mới): form match formMap → <span class="vocab-form-mark" data-form="..."
              data-lemma="..." data-ftype="...">
```

CSS đề xuất:
- `vocab-mark`: nền cam (như hiện tại)
- `vocab-form-mark`: nền cam nhạt + chấm gạch dưới màu cam

### 7.3 selectWord() — khi click form-mark

```
Click vocab-form-mark → selectWord(lemma_key) + hiện tooltip nhỏ "Fähigkeiten [NOM.PL]"
```

### 7.4 renderVocab() — hiện biến thể dưới lemma

```
Với mỗi lemma trong vocabData có biến thể đã biết (formMap):
  → Render dòng nhỏ dưới nghĩa: "↳ Fähigkeiten (NOM.PL), Fähigkeit (NOM.SG)"
```

### 7.5 collectCandidates() (tab Neu wort) — phân nhóm

Tách 2 nhóm:
- **"Từ gốc mới"** (lemma chưa biết, form_key không có trong formMap)
- **"Biến thể đã biết"** (form_key có trong formMap → lemma đã có) — hiện nhỏ hơn, không cần "+ Queue"
  hoặc hiện "+ Queue biến thể" để thêm form vào DB với parent_id

---

## 8. Auto-translate task — Nhận diện lemma/form_type

Cập nhật scheduled task `deutsch-vocab-auto-translate` (SKILL.md):

Khi dịch từ mới:
```
Với mỗi từ cần dịch:
  1. Xác định lemma (từ gốc): vd "Fähigkeiten" → lemma "Fähigkeit"
  2. Kiểm tra vocab_master.csv: lemma đó đã có chưa?
     - Nếu có → ghi parent_id = id_lemma_trong_csv, form_type = mã biến cách
     - Nếu chưa → tạo row lemma mới trước, rồi tạo row biến thể với parent_id
  3. form_type codes (theo DD-20260527-005):
     Substantiv: NOM.SG / NOM.PL / GEN.SG / GEN.PL / DAT.SG / DAT.PL / AKK.SG / AKK.PL
     Verb: PRAET / PERF / KONJ.II / PART.II / INF
     Adjektiv/Adverb: KOMP / SUP / ADJ.NOM / ADJ.AKK / ADJ.DAT / ADJ.GEN
```

---

## 9. Acceptance Tests

- [ ] `GET /api/vocab/forms?words=Fähigkeiten` → trả lemma "Fähigkeit" với form_type "NOM.PL"
- [ ] Mở lesson → bật "Nền vàng" → "Fähigkeiten" highlight màu nhạt cam
- [ ] Click "Fähigkeiten" → panel scroll đến "Fähigkeit", dòng phụ "↳ Fähigkeiten (NOM.PL)"
- [ ] Tab "Neu wort" → nhóm "Biến thể đã biết" tách riêng khỏi "Từ gốc mới"
- [ ] `php push_vocab.php --limit=5` trên row có parent_id → DB row có parent_id đúng
- [ ] KHÔNG break drill flow, KHÔNG break push_vocab/pull_vocab hiện có

---

## 10. Cấm đụng

- `vocab_master.csv` — read-only (push_vocab.php chỉ đọc)
- `module/lingq_sync/`
- Bảng `users`, `events`
- KHÔNG tự commit/push

---

## 11. Files cần tạo / sửa

```
module/deutsch_web/migrations/003_lemma.sql     ← TẠO MỚI
module/deutsch_web/api/vocab.php                ← THÊM api_vocab_forms()
module/deutsch_web/public/index.php             ← THÊM route GET /api/vocab/forms
module/deutsch_web/public/assets/drill.js       ← THÊM formMap, vocab-form-mark, phân nhóm Neu wort
module/deutsch_web/public/assets/drill.css      ← THÊM .vocab-form-mark + tooltip
module/deutsch_web_sync/push_vocab.php          ← CẬP NHẬT push parent_id (2-pass)
C:\Users\henry\Documents\Claude\Scheduled\
  deutsch-vocab-auto-translate\SKILL.md         ← CẬP NHẬT nhận diện lemma/form_type
```

---

## 12. Format Report

```
=== DEUTSCH_WEB_LEMMA DONE ===
Migration 003: ...
API /api/vocab/forms: test curl → ...
drill.js vocab-form-mark: ...
push_vocab.php 2-pass: ...
scheduled task updated: ...

Cần user sau review Cursor:
1. commit + push
2. server: git pull + rsync + php scripts/migrate.php
3. push_vocab.php --limit=10 (kiểm tra parent_id push đúng)
4. Ctrl+Shift+R bài 4.31 → test form highlight
```
