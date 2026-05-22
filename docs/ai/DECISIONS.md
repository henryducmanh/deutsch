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

## DD-20260522-001 — Giải thích KHÔNG dùng tiếng Anh, chỉ thuần Việt + tiếng Đức

- **Date:** 2026-05-22
- **Topic:** style giải thích bài học (mọi vai tutor: Tutor, Vocab Extractor, Mistake Auditor, Speaking/Listening Coach, Lesson Planner, Homework Generator, vai "kiểm tra/phản biện")
- **Decision:** Khi giải thích cho user, **CẤM** chèn thuật ngữ tiếng Anh (vd "thesis", "main message", "paraphrase", "distractor", "trap", "detail", "Hauptaussage" thì OK vì là tiếng Đức). Chỉ dùng **thuần Việt** hoặc **nguyên gốc tiếng Đức**. Trích nguyên văn tiếng Đức trong dấu nháy được.
- **Alternatives considered:**
  - (a) Mix Anh-Việt-Đức như thường lệ → loại: user phải tra thêm 1 tầng nghĩa khi đọc, mất thời gian học.
  - (b) Chỉ tiếng Việt thuần, dịch luôn cả thuật ngữ tiếng Đức → loại: mất evidence gốc, không học được vocab/grammar Đức.
- **Why:** user đang học tiếng Đức (DTZ B1), KHÔNG học tiếng Anh. Mỗi từ Anh xen vào = noise + cognitive load thừa. Tiếng Việt giải thích + tiếng Đức làm evidence → tối ưu cho việc học.
- **Mapping ví dụ:**
  - "thesis / main message" → "luận điểm chính" / "ý chính" / "Hauptaussage" (Đức)
  - "paraphrase" → "diễn đạt lại" / "viết lại bằng từ khác"
  - "distractor" → "đáp án nhiễu" / "câu bẫy"
  - "trap" → "bẫy"
  - "detail" → "ý phụ" / "chi tiết hỗ trợ" / "Begründung" (Đức)
  - "match" → "khớp với" / "ứng với"
  - "support / supporting argument" → "luận cứ hỗ trợ"
- **Linked files:** `CLAUDE.md` (Style section), `docs/ai/ROLE_PROMPTS.md` (mọi vai tutor)
- **Status:** active

---

**Last updated:** 2026-05-22 (4 entries — thêm rule style giải thích thuần Việt + Đức).
