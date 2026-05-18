# LESSONS — Bài học rút ra từ quá trình học Đức

> Insight về cách mình học hiệu quả nhất (vs lãng phí thời gian). Append-only.
> Format mỗi entry: date + context + lesson + apply-where.

---

<!-- Append entry mới ở dưới đây. Template:

## YYYY-MM-DD — <title ngắn>

- **Context:** <chuyện gì xảy ra — vd "học Konjunktiv II 2 tuần nhưng vẫn nhầm với Konjunktiv I">
- **Lesson:** <bài học rút ra — 1-2 câu>
- **Apply where:** <vai nào / lúc nào dùng — vd "Lesson Planner: tránh dồn 2 Konjunktiv trong cùng tuần">
- **Evidence:** <link MISTAKES_LOG entry hoặc SESSION file>

-->

## 2026-05-18 — Tool tự tạo data → ô nhiễm. Source of truth phải là nơi mình curate tay.

- **Context:** Dùng LingQ 4 năm (2022-2026), tích lũy 2728 "LingQs" word-by-word khi đọc, nhưng review SRS không hiệu quả vì 1 từ có nhiều nghĩa, không có cụm, định nghĩa nhiễu nhau. Kết quả: 2728 từ "đã chạm" nhưng B1 vẫn vật lộn.
- **Lesson:** Tool tự bóc vocab khi đọc (LingQ, Readlang, Beelinguapp...) phù hợp **exposure layer**, không phù hợp **memory layer**. Memory layer phải là CSV mình curate per session (chọn nghĩa chính, thêm cụm, tag chủ đề DTZ).
- **Apply where:**
  - Vai **Vocab Extractor**: từ giờ trở đi không trust LingQ data. Chỉ bóc từ tutor slide / video / podcast với context rõ ràng.
  - Vai **Lesson Planner**: cấu trúc tuần phải có 1 buổi "curate vocab" để chuyển expose → memorize.
- **Evidence:** `docs/ai/DECISIONS.md` DD-20260518-001, `module/lingq_sync/` (đã build pipeline 1-chiều ngược: vocab_master làm chủ).

## 2026-05-18 — Trước khi mass operate trên API external, ALWAYS test --limit=1 trên 1 entry thật.

- **Context:** Build module `lingq_sync` Phase D push 57 từ + delete 2728. POST plain string `hint` field thành công HTTP 201 nhưng UI không hiển thị MEANING. Mãi sau Phase F mới phát hiện API expect `hints` array. Nếu test 1 entry rồi check UI ngay → biết ngay, đỡ phải PATCH lại 57 entries.
- **Lesson:** Test single + manual UI verify TRƯỚC mass operation. HTTP 201/200 không có nghĩa là data đã lưu đúng — chỉ là API accept request.
- **Apply where:** Mọi handoff Claude Code có gọi POST/PATCH/PUT external API: prompt PHẢI có step "test --limit=1 + manual UI verify" trước step apply mass.
- **Evidence:** `docs/ai/tasks/LINGQ_PHASE_F_PROMPT.md` mục 4 (acceptance test), session log 2026-05-18 17:00-19:30.

---

**Last updated:** 2026-05-18 (2 entries — LingQ integration session).
