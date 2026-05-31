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

## DD-20260527-005 — Thêm biến thể (inflected forms) vào vocab_master với parent_id + form_type

- **Date:** 2026-05-27
- **Topic:** vocab schema — xử lý biến cách tiếng Đức trong LingQ
- **Decision:** Thêm 2 cột vào `vocab_master.csv`: `parent_id` (VOC ID của lemma gốc) và `form_type` (mã biến cách). Biến thể được lưu là row riêng với ID = `{parent_id}-{FORM_CODE}`. Hint của biến thể theo công thức: `{lemma} ({dạng biến cách đầy đủ}) = {nghĩa}` để LingQ hiển thị đúng khi đọc văn bản.
- **Alternatives considered:**
  - (a) Chỉ lưu lemma, tự mark thủ công trong LingQ → loại vì phụ thuộc user nhớ, không tự động hoá được
  - (b) Ghi tất cả forms vào cột `formen` rồi parse → loại vì khó push từng form lên LingQ riêng biệt
  - (c) Push hàng loạt mọi biến cách (4-8 forms/từ) → loại vì bùng nổ cards, loãng review
- **Why:** LingQ nhận diện theo word form, không theo lemma. Khi đọc *Karnevals* mà chỉ có card *Karneval* → LingQ đánh dấu lạ, mất cơ hội học. Lưu biến thể có cấu trúc → push lên LingQ đúng form → vừa học nghĩa vừa học ngữ pháp biến cách trong context thật.
- **form_type codes:** `NOM/GEN/DAT/AKK` × `SG/PL` cho danh từ; `ADJ.NOM/AKK/DAT/GEN` cho tính từ; `KOMP/SUP/PRAET/PERF` cho động từ + tính từ so sánh.
- **Linked files:** `data/README.md` (schema đầy đủ + bảng form_type), `data/03_unified/vocab_master.csv`
- **Status:** active

---

## DD-20260529-006 — deutsch.twv.app: chốt 7 quyết định nền tảng web (mục 12 brief)

- **Date:** 2026-05-29
- **Topic:** kiến trúc nền tảng học online `deutsch.twv.app` (PHP + API ↔ Cowork desktop). Chốt trước khi handoff Claude Code Phase 1.
- **Ref:** `brainstorm/deutsch-web-platform-brief.md` mục 12, `docs/ai/tasks/DEUTSCH_WEB_PHASE1_PROMPT.md`

| # | Câu hỏi | Quyết định | Lý do |
|---|---|---|---|
| 1 | DB web | ~~SQLite~~ → **MySQL, database riêng `apptwv_deutsch`** (cập nhật 2026-05-29 sau khi xem server thật). | Host deutsch.twv.app là **shared cPanel MySQL-first** (MySQL 5.7.44 sẵn, `pdo_mysql` ✓; `pdo_sqlite` KHÔNG xác nhận bật). MySQL = first-class, debug qua phpMyAdmin, cPanel auto-backup, không lo file-lock/extension. **Database riêng** `apptwv_deutsch` + user riêng → vẫn KHÔNG đụng schema/bảng/code mieu (chỉ chung MySQL server, tách DB). Lý do SQLite cũ ("tránh ghép dự án") quá thận trọng khi host đã có MySQL chuẩn. |
| 2 | Auth | Bảng **`users` riêng trong MySQL `apptwv_deutsch`**, single user, password hash trong DB + session PHP. Tái dùng **pattern** session mieu, KHÔNG dùng bảng/DB mieu. | Tách biệt domain. Không phụ thuộc mieu schema. |
| 3 | Audio (~220 MB) | **Phase 1: dùng URL LingQ S3** (đã push, ổn định, 0 cost storage/bandwidth trên twv.app). Lesson JSON chứa `audio.url`. KHÔNG upload 220 MB lên server. | Prototype đã chạy với `s3.amazonaws.com/media.lingq.com`. Host local để Phase sau nếu S3 đổi (có sẵn `audio.local_path` fallback). |
| 4 | Sync tần suất | **Cron 30 phút** (giống pattern `lingq_sync`) + chạy manual được. Pull-based, web KHÔNG push tới Cowork. | Nhất quán cron Windows hiện có. Realtime không cần cho 1 user. |
| 5 | Ack model | **Giữ audit log** — cột `synced_at` (NULL = pending). Ack = set timestamp, KHÔNG xóa row. | Append-only spirit giống vocab_master. Re-pull/debug được. |
| 6 | Vocab panel | **Phase 1: JSON tĩnh** deploy cùng lesson (`lessons/{id}.json`, filter từ vocab_master). DB động (Cowork POST enrich) = **Phase 2**. | Bám brief 6.6 + bảng phase. Tránh over-build. |
| 7 | Gia sư mode | **Solo-only Phase 1.** Defer multi-user/tutor view. | Ngoài scope trước thi DTZ 06/2026. |

- **Nguyên tắc xuyên suốt (giữ nguyên brief mục 11):** `vocab_master.csv` = source of truth (web chỉ queue, Cowork curate→append); LingQ push từ local qua `lingq_sync`; web KHÔNG gọi GitHub API; giải thích thuần Việt + tiếng Đức (DD-20260522-001).
- **Linked files:** `module/deutsch_web/`, `module/deutsch_web_sync/`, `docs/ai/tasks/DEUTSCH_WEB_PHASE1_PROMPT.md`, `module/deutsch_web/lessons/4.29.json`
- **Status:** active

---

## DD-20260531-007 — Phase A Hören JSON Generator: `horen_to_lesson_json.py` live

- **Date:** 2026-05-31
- **Topic:** pipeline tự động generate lesson JSON cho series Hören 4.x lên `deutsch.twv.app`, không phải làm tay từng bài.
- **Ref:** `docs/ai/tasks/HOREN_LESSON_JSON_PHASE_A_PROMPT.md`
- **Decision:** build `module/scan_extract/horen_to_lesson_json.py` (CLI `--dry-run`/`--apply`/`--id`/`--force`/`--series`) + `cron_generate_lessons.bat` (push LingQ → sync csv → gen JSON). Schema `deutsch_web_lesson_v1` canonical theo `lessons/4.31.json`. Audio + LingQ meta lookup priority: `url.md` → `data/lingq_lessons.csv` → null.
- **Kết quả:** 27 bài generate (4.2–4.28), 3 skip (4.29–4.31 đã có, KHÔNG overwrite). 0 errors. Tất cả JSON valid, aussagen a–f × 3.
- **Phát hiện nguồn (empiric):**
  - Transcript marker 3 dạng: `Aussage N` / `Nummer N` / `Nr. N` → regex gom cả ba, normalize label về `Aussage N`.
  - Scrape `_questions.md` đôi khi bỏ sót option ở Aussage đầu (vd 4.4 thiếu `f` ở Aussage 1+2). Vì a–f là ngân hàng câu chung → parser gom **union** options, áp đủ a–f cho mọi Aussage.
  - 4.18–4.28 có `lesson_id` nhưng `audio_url` rỗng trong csv → `audio.host="none"` (chờ LingQ push audio đợt sau).
  - Windows console cp1252 không encode `→`/`Ö` → `sys.stdout.reconfigure(encoding="utf-8")`.
- **Linked files:** `module/scan_extract/horen_to_lesson_json.py`, `module/scan_extract/cron_generate_lessons.bat`, `module/deutsch_web/lessons/4.2.json`–`4.28.json`
- **Status:** active

---

**Last updated:** 2026-05-31 (7 entries — thêm DD-20260531-007 Phase A Hören JSON Generator).
