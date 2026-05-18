# DECISIONS — Quyết định học tập + công cụ

> Quyết định đáng nhớ về phương pháp học, chọn công cụ, cấu trúc data. Append-only.
> Format mỗi entry: ID + date + topic + decision + alternatives + why.

ID convention: `DD-<YYYYMMDD>-<NNN>` (DD = Deutsch Decision).

---

<!-- Append entry mới ở dưới đây. Template:

## DD-YYYYMMDD-001 — <title>

- **Date:** YYYY-MM-DD
- **Topic:** vocab schema / tool choice / learning method / ...
- **Decision:** <1-2 câu>
- **Alternatives considered:** <list ngắn>
- **Why:** <lý do chính>
- **Linked files:** <relative path>
- **Status:** active / superseded by DD-...

-->

## DD-20260518-001 — vocab_master.csv là source of truth, LingQ chỉ là UI học

- **Date:** 2026-05-18
- **Topic:** tool boundary LingQ vs local CSV
- **Decision:** `data/03_unified/vocab_master.csv` (curate thủ công) là source of truth. LingQ web/mobile là review SRS engine, KHÔNG tạo data ở đó nữa.
- **Alternatives considered:**
  - (a) LingQ làm primary, local CSV mirror → loại vì LingQ tạo word-by-word, không có cụm, nhiễu
  - (b) Anki làm primary → loại vì không có context fragment + tags structure tốt
  - (c) Notion làm primary → loại vì không versionable, không AI-parseable
- **Why:** LingQ tạo rác (1 từ nhiều nghĩa, không có cụm, review không học được). Chuyển sang local CSV cho phép curate per tutor session + tag wortart/level/thema/voc-id → traceable.
- **Linked files:** `docs/LINGQ_INTEGRATION.md`, `module/lingq_sync/`
- **Status:** active

## DD-20260518-002 — Pipeline 2-chiều với 3-file local + cron 10:00

- **Date:** 2026-05-18
- **Topic:** sync architecture vocab_master ↔ LingQ
- **Decision:** Tách 3 file local: `vocab_master.csv` (source) → `lingq_target.csv` (desired state, sinh tự động) → `lingq_cards.csv` (server snapshot). Status từ snapshot, field khác từ target khi PATCH.
- **Alternatives considered:**
  - (a) 1 file duy nhất vừa desired vừa snapshot → loại vì sync.php sẽ ghi đè desired state
  - (b) Real-time push mỗi 5 phút → loại vì rate limit + workflow không cần realtime
- **Why:** Tách rõ "ý định" vs "thực tế" để debug + rollback dễ. Cron 10:00 đủ tần suất.
- **Linked files:** `docs/LINGQ_INTEGRATION.md` §1-§3, `module/lingq_sync/cron.bat`
- **Status:** active

## DD-20260518-003 — Hints field LingQ API v2 là plural array of object

- **Date:** 2026-05-18
- **Topic:** API payload format
- **Decision:** Khi POST/PATCH card phải dùng field `hints` (plural) = array of `{text, locale}` objects. KHÔNG dùng `hint` (singular) plain string — server silently ignore.
- **Alternatives considered:** N/A (đây là API spec, discover empiric).
- **Why:** 53/57 entries POST với `hint` plain string → server lưu empty → UI không có MEANING. Fix bằng wrap `[{text, locale:'vi'}]` → 57/57 OK.
- **Linked files:** `docs/ai/tasks/LINGQ_PHASE_F_PROMPT.md`, `module/lingq_sync/lingq_client.php`
- **Status:** active

---

**Last updated:** 2026-05-18 (3 entries — LingQ integration session).
