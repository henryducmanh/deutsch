# LingQ Phase F — Fix hints format (singular string → plural array of object)

> **Task ID:** `lingq_phase_f_v1`
> **Người triển khai:** Claude Code (Implementer).
> **Stack:** PHP 7.4 + cURL, extends `module/lingq_sync/`.
> **Lock:** `.ai-locks/lingq_phase_f.lock` trước Edit, xoá sau khi xong.
> **Prerequisite:** Phase D đã chạy xong, 57 entries trên LingQ server có `hints=[]`.

---

## 1. End-user

**Henry** — phát hiện UI LingQ không hiển thị nghĩa Việt cho 53/57 từ vừa push (4 từ có VI là legacy hints user hand-edit ngày xưa). Lý do: API field name + format sai. Cần fix + PATCH lại 57 entries.

---

## 2. Màn cuối cùng

### Files thay đổi

```
module/lingq_sync/
├── config.php                ← +1 key: hint_locale = 'vi'
├── lingq_client.php          ← (optional) helper buildHintsArray()
├── push.php                  ← buildPayload() đổi hint → hints array
├── sync.php                  ← parseCardFromApi() đổi hints array → hint string
└── README.md                 ← note format hints
```

KHÔNG đổi schema CSV: `lingq_target.csv` + `lingq_cards.csv` vẫn 1 cột `hint` plain string.

### UX expected

**Sau khi sửa code, chạy:**

```bat
REM 1. Pull lại snapshot với hints parse đúng từ array
C:\php\php74\php.exe module\lingq_sync\sync.php
```
→ `lingq_cards.csv` hiện có hint cho 4 legacy từ (lắp vào, ngừng lại,...), 53 từ POST mới hint vẫn empty.

```bat
REM 2. Plan sẽ trigger 53 PATCH (53 từ có target.hint != snapshot.hint)
C:\php\php74\php.exe module\lingq_sync\push.php
```
→ Plan: `CREATE: 0, UPDATE: 53, DELETE: 0` (4 legacy entries hint khớp target → skip).

```bat
REM 3. Test single trước
C:\php\php74\php.exe module\lingq_sync\push.php --apply --limit=1
```
→ PATCH 1 entry. Mở `lingq.com/.../vocabulary` check entry đó có nghĩa VI hiện ra.

```bat
REM 4. Nếu test OK → apply full 53
C:\php\php74\php.exe module\lingq_sync\push.php --apply
```
→ 53 PATCH HTTP 200, ~2 phút (53 × 1.5s sleep).

```bat
REM 5. Verify
C:\php\php74\php.exe module\lingq_sync\sync.php
python -c "import csv; rows=[r for r in csv.DictReader(open('data/lingq_cards.csv',encoding='utf-8-sig'))]; print(sum(1 for r in rows if r.get('hint','').strip()), '/', len(rows), 'rows có hint')"
```
→ Phải in `57 / 57 rows có hint`.

---

## 3. Ví dụ dữ liệu thật

### API payload mới (POST/PATCH)

**Trước (sai):**
```json
{
  "term": "anpassen",
  "hint": "thích nghi (reflexiv)",
  "fragment": "..."
}
```

**Sau (đúng):**
```json
{
  "term": "anpassen",
  "hints": [
    {"text": "thích nghi (reflexiv)", "locale": "vi"}
  ],
  "fragment": "..."
}
```

### API response (GET) — cần parse

```json
{
  "pk": 815679120,
  "term": "anpassen",
  "hints": [
    {"id": 12345, "text": "thích nghi (reflexiv)", "locale": "vi", "popularity": 1, "is_google_translate": false, "flagged": false}
  ],
  "fragment": "..."
}
```

→ sync.php parse: filter `hints` where `locale=cfg['hint_locale']` (= 'vi'), lấy `text` field đầu tiên. Nếu không có → empty.

### Edge cases

1. **Nhiều hint VI:** chọn cái có `popularity` cao nhất, hoặc concat bằng `; `. Recommend: lấy đầu tiên (đỡ phức tạp), log WARN nếu có > 1.
2. **Hint locale khác (en/de/fr):** ignore, không lưu vào CSV (giữ CSV mono-locale theo `hint_locale`).
3. **hints array rỗng:** CSV `hint` = ''.
4. **API trả về `hints: null` thay vì `[]`:** treat as empty, không crash.

### Diff logic của push.php — KHÔNG đổi nguyên tắc

So sánh `target.hint` (plain string) với `snapshot.hint` (plain string sau parse). Nếu khác → PATCH với `hints=[{text: target.hint, locale: cfg['hint_locale']}]`.

KHÔNG đụng status (như Phase D).

---

## 4. Acceptance tests

1. **Sync parse hints array đúng:**
   - Run `sync.php`, sample 5 row trong `lingq_cards.csv`, verify 4 legacy từ (anpassen/einsetzen/entscheidend/Entwicklung/...) có `hint` non-empty với text VI.
2. **Target vẫn đúng:**
   - Run `update_local.php`, `lingq_target.csv` vẫn 57 row, hint VI từ vocab_master.
3. **Dry-run push diff:**
   - Run `push.php`, plan show `UPDATE: 53` (4 từ legacy + 1 từ test = 5 entries có hint match — số có thể vary chút).
4. **Single PATCH:**
   - Run `push.php --apply --limit=1`, mở LingQ UI verify nghĩa VI xuất hiện cho từ đó.
5. **Full apply:**
   - Run `push.php --apply`, log show `UPDATE: 53 OK / 0 fail`.
6. **Verify final state:**
   - Run `sync.php`, count `lingq_cards.csv` rows có hint non-empty = 57/57.
   - Mở LingQ UI scroll qua 57 từ, mọi từ có MEANING (VI).
7. **Idempotent:**
   - Run `push.php --apply` lại → `UPDATE: 0`. Stable state.

---

## 5. Cấm đụng

- ❌ Sửa schema CSV (cột `hint` vẫn plain string trong CSV; chỉ thay đổi format khi gọi/parse API).
- ❌ Đổi diff logic (vẫn `target.hint != snapshot.hint` trigger PATCH).
- ❌ Đụng `data/03_unified/vocab_master.csv`.
- ❌ Hard-code locale 'vi' — đọc từ `config.php` key `hint_locale`.
- ❌ Mass POST mới — phase này CHỈ PATCH 53-57 entries đã có.
- ❌ `git commit / push`.

---

## 6. Performance / scale

- 53 PATCH × 1.5s sleep + 0.3s response = ~95s. Acceptable.
- Test single trước (step #4 trong acceptance) để verify endpoint chấp nhận format mới — đừng skip.
- Nếu PATCH với `hints` array fail HTTP 400 (server có thể yêu cầu endpoint riêng `/api/v2/{lang}/cards/{pk}/hints/`):
  - Log response body chi tiết.
  - Fall back try: `POST /api/v2/de/cards/{pk}/hints/` với body `{"text": "...", "locale": "vi"}`.
  - Document trong code comment `// LingQ v2 quirk: ...`

---

## 7. Format report

```
✅ LingQ Phase F — done

Files modified:
- module/lingq_sync/config.php           (+1 key)
- module/lingq_sync/push.php             (XX dòng buildPayload + diff)
- module/lingq_sync/sync.php             (XX dòng parseCardFromApi)
- module/lingq_sync/lingq_client.php     (optional helper)
- module/lingq_sync/README.md            (note hints format)

Verified:
- [x] hints format = array of {text, locale}, locale từ config
- [x] sync.php parse hints array → plain string CSV
- [x] push.php build payload với hints array
- [x] Diff logic không đổi
- [ ] Live test single PATCH — cần user chạy step #4

To run (cho Henry):
1. C:\php\php74\php.exe module\lingq_sync\sync.php
2. C:\php\php74\php.exe module\lingq_sync\push.php
3. C:\php\php74\php.exe module\lingq_sync\push.php --apply --limit=1
   → mở LingQ UI verify nghĩa VI xuất hiện
4. Nếu OK: C:\php\php74\php.exe module\lingq_sync\push.php --apply
5. Verify: 57/57 rows có hint

Lock cleared: .ai-locks/lingq_phase_f.lock
Pending: Cursor diff review.
```

---

## Phụ lục — fallback endpoint

Nếu PATCH inline `hints` fail, LingQ API có thể yêu cầu endpoint riêng cho hints:

```
GET    /api/v2/{lang}/cards/{pk}/hints/        → list hints
POST   /api/v2/{lang}/cards/{pk}/hints/        → tạo hint mới {text, locale}
DELETE /api/v2/{lang}/cards/{pk}/hints/{id}/   → xoá hint
```

Strategy fallback (chỉ implement nếu cần):
1. PATCH card với inline `hints` array.
2. Nếu 400/422 → DELETE old hints, POST hints mới qua endpoint riêng.

Document quyết định trong code comment, không over-engineer nếu inline đã work.

---

## Handoff dòng paste

```
Đọc docs/ai/tasks/LINGQ_PHASE_F_PROMPT.md và làm Phase F. Tạo lock .ai-locks/lingq_phase_f.lock trước Edit. Test single PATCH bằng --limit=1 trước khi commit code logic (verify với user trước khi PATCH mass). KHÔNG tự chạy --apply mass. Báo "edit xong, chờ review Cursor".
```
