# AI Knowledge System — Roadmap

> 3 phase triển khai. Đi kèm: [`AI_KS_HUB.md`](./AI_KS_HUB.md), [`AI_KS_ARCHITECTURE.md`](./AI_KS_ARCHITECTURE.md), [`AI_KS_DOMAINS.md`](./AI_KS_DOMAINS.md).

---

## Tinh thần roadmap

ChatGPT brainstorm Chat 13, 15:

> "Đừng lao vào vector DB / Qdrant / embedding pipeline ngay."
> "Phase 1: GitHub + Markdown + CSV + Cursor — đã đủ."

→ **Build nhỏ, dùng được ngay, scale khi đau thật.** Không gold-plate trước khi có data thật.

---

## Phase 1 — Foundation (Tuần 1-4)

**Mục tiêu:** Mọi domain đều có skeleton hoạt động được, dùng tay (manual trigger) hàng ngày. **Không cần API riêng**, không cần Qdrant.

### Deliverable

- [ ] **Đức:** Đã có 70%. Hoàn thiện 4 master CSV + chuẩn hóa role prompts trong repo.
- [ ] **Dev (1 trong 2-3 dự án — pick dự án ổn nhất):** Seed `docs/ai/{PIPELINE, ROLE_PROMPTS, GLOSSARY, DECISIONS, LESSONS, FAILURES, INBOX, EXEC, HELP}.md`. Verify với 1 task thật end-to-end.
- [ ] **SEO:** Tạo repo `seo/` với 3 file: `keyword_master.csv`, `cluster_map.md`, `content_calendar.csv`. Không integration tự động.
- [ ] **SOP:** Tạo repo `sop/` với `customer_master.csv`, `hosting_inventory.csv`, `domain_inventory.csv`. Migrate 1-shot từ Sheets hiện có (nếu có).

### Tool ở Phase 1

- ✅ Cowork (daily driver, đọc folder)
- ✅ Claude Code (batch edit, theo prompt)
- ✅ Cursor (review + commit)
- ✅ ChatGPT (brainstorm, plan)
- ⚠️ Notion (chỉ Đức — đã có sẵn)
- ❌ Không Qdrant / không OpenAI API riêng / không n8n

### Định nghĩa "done" Phase 1

User mở Cursor → đọc `CLAUDE.md` + `docs/ai/PIPELINE.md` 1 lần là biết toàn cảnh repo.
AI agent đọc 4-5 file là đủ context để bắt task ngay, không hỏi lại.
Per ngày, ≥ 1 task được hoàn thành theo pipeline có log (INBOX/EXEC).

---

## Phase 2 — Automation (Tháng 2-3)

**Mục tiêu:** Tự động hóa pipeline lặp, batch processing local, Notion mirror cho domain cần share.

### Deliverable

- [ ] **Cowork scheduled tasks** (≥ 3):
  - Hằng ngày 8am: scan `input/` của German Brain → liệt kê file chưa xử lý.
  - Hằng tuần: render artifact "weak_words top 30" + "task pending all domain".
  - Hằng tuần: SOP renewal watcher — list domain/hosting hết hạn 30 ngày tới.
- [ ] **Batch extract pipeline (Đức):** Claude Code đọc `input/text/`, `input/transcript/`, output `output/new_vocab/<date>.csv`. User merge tay vào master.
- [ ] **Notion sync 1-chiều (Đức + SOP):** Script đẩy `data/*_master.csv` lên Notion DB tương ứng. Sync ngược chỉ khi user confirm.
- [ ] **AI Lock global:** `C:\twv_share\.ai-locks/` cho task cross-domain.
- [ ] **2 domain còn lại (Dev project 2, Dev project 3):** Seed như project 1.

### Tool thêm vào

- ✅ Cowork artifact (dashboard)
- ✅ Notion API (sync 1-chiều)
- ⚠️ OpenAI API (chỉ khi batch quá lớn cho Claude Code context)

### Định nghĩa "done" Phase 2

Sáng dậy mở Cowork dashboard → thấy task hôm nay + renewal cần xử lý + từ Đức yếu nhất tuần.
1 buổi học tiếng Đức (input batch ảnh + audio) → Claude Code chạy 5-10 phút → master CSV update.
SEO/SOP có ít nhất 1 vai vận hành tuần đều.

---

## Phase 3 — Semantic (Tháng 4+, optional, threshold-based)

**Mục tiêu:** Cross-domain semantic search + chat interface giống mini NotebookLM.

**Kích hoạt Phase 3 chỉ khi đạt 1 trong các threshold:**

- Master CSV > 5,000 rows (Đức: ~vocab + chunks combined).
- Cross-domain query manual > 3 lần/tuần và mất > 10 phút mỗi lần.
- Cần share knowledge với người khác (đối tác, gia sư) qua chat interface.
- Có ngân sách API riêng ổn định (~$10-30/tháng).

### Deliverable

- [ ] Embedding pipeline: OpenAI text-embedding-3-small → Qdrant (self-host Docker on local hoặc free tier cloud).
- [ ] RAG chat interface đơn giản (Streamlit / Gradio / Cowork artifact với fetch).
- [ ] Watch folder: file thay đổi → trigger re-embed (n8n hoặc cron Windows).
- [ ] Multi-domain query: "tìm chunk Đức liên quan đến chăm sóc trẻ em" hoặc "tìm SOP về hosting fail-over" → trả về cross-domain results.

### Tool thêm vào

- ✅ Qdrant (Docker local) / hoặc Chroma
- ✅ OpenAI API (embedding + chat) hoặc Claude API
- ⚠️ n8n (chỉ khi cần workflow phức tạp ngoài cron)

### Định nghĩa "done" Phase 3

Hỏi 1 câu semantic (vd: "từ Đức nào nói về 'hỗ trợ gia đình'") → trả về top 5 chunks + ví dụ, < 3 giây.
Cross-domain: "tôi đã quyết định gì về authentication trong các dự án PHP" → trả về DECISIONS từ N repo.

---

## Quick wins tuần này (Phase 1 đầu tiên)

Ngắn, làm được trong 2-3 tối:

### Tối 1 — Seed 1 domain Dev

1. Chọn dự án PHP đang ổn nhất.
2. Tạo trong repo đó:
   ```
   docs/ai/{PIPELINE.md, ROLE_PROMPTS.md, GLOSSARY.md, DECISIONS.md, LESSONS.md, FAILURES.md}
   docs/ai/{INBOX.md, EXEC.md, HELP.md} (rỗng, ready ghi)
   .ai-locks/.gitkeep
   ```
3. Trong `CLAUDE.md` thêm boot order như user prefs.
4. Vào Cursor mở 1 task thật → "đóng vai tiếp nhận" → end-to-end pipeline.

### Tối 2 — Đức: gom 4 master CSV vào repo

1. Repo deutsch (workspace hiện tại) đã có `data/` rồi (theo BRAINSTORM Chat 14).
2. Bổ sung:
   - `data/chunks_master.csv` (header theo `AI_KS_DOMAINS.md` §1)
   - `data/weak_words.csv` (rỗng, ready append)
   - `data/sources_master.csv`
3. Migrate ≥ 50 từ từ Notion sang `vocab_master.csv` (manual export Notion CSV → import).
4. Verify Cursor có thể tự đọc 4 CSV này khi user hỏi "từ nào yếu nhất chủ đề Familie".

### Tối 3 — SOP scaffold

1. Tạo repo `C:\twv_share\app\sop`.
2. Init: `customer_master.csv` + `hosting_inventory.csv` + `domain_inventory.csv` (header schema theo `AI_KS_DOMAINS.md` §4).
3. Manual nhập 1 customer + 1 hosting + 1 domain để test.
4. Mở Cowork → ask "liệt kê hosting sắp hết hạn 60 ngày" → verify đọc được.

### Cuối tuần — Review

- 3 domain (Dev, Đức, SOP) có working skeleton.
- Pattern thực sự dùng được hay cần tinh chỉnh? → ghi `AI_KS_HUB.md` Open questions.

---

## Pitfalls cần tránh

Lessons learned từ user prefs + brainstorm ChatGPT:

| Pitfall | Hậu quả | Cách tránh |
|---|---|---|
| **Lưu primary trong Notion** | Mất version, AI parse yếu, lock-in | GitHub primary, Notion = mirror |
| **Bóc hết vào 1 mega-repo** | AI index chậm, conflict role | Multi-repo per domain |
| **Một chat làm nhiều vai** | AI bối rối, log lẫn lộn | 1 chat = 1 vai, paste role prompt đầu |
| **Skip role prompt** | Boot order sai, AI guess | Luôn paste role prompt đầu chat |
| **Edit master CSV tay không log** | Mất audit, conflict với pipeline | Chỉ AI agent append/update qua prompt |
| **Tự commit từ Claude Code** | Mất control, miss review | AI báo "edit xong" → user review Cursor → commit |
| **Lên Qdrant sớm** | Phí + maintenance, chưa cần | Đợi threshold Phase 3 |
| **Bỏ archive/** | Không re-process được khi đổi prompt | Luôn archive input |
| **Schema change in-place** | Migration đau, mất data cũ | Bump schema_version + script migration |
| **AI Lock TTL quá dài** | Lock chết → block task khác | TTL 60min, cleanup script |

---

## Decision log cho roadmap

(Sẽ chuyển sang `docs/ai/DECISIONS.md` chính khi xúc tiến)

| Date | Decision | Why | Alternative considered |
|---|---|---|---|
| 2026-05-17 | Multi-repo per domain | AI index nhanh, tách quyền | Mono-repo `knowledge-os/` (loại: AI index chậm trên 4 domain mix) |
| 2026-05-17 | GitHub > Notion làm SOT | Version control + AI parse | Notion-first (loại: lock-in, yếu bulk data) |
| 2026-05-17 | Phase 1 không API | Chưa cần, tốn phí | Bắt đầu Qdrant ngay (loại: gold-plate) |
| 2026-05-17 | Pilot domain đầu: Đức | Đã có 70% nền | Bắt đầu Dev (loại: dev đã có pattern user prefs, ít cần "design"; Đức cần tutor manager + LingQ sync — đáng đầu tư) |

---

## Cập nhật roadmap

Sau mỗi phase, review:
1. Đạt definition of done chưa?
2. Pattern nào dùng được, pattern nào cần đổi?
3. Domain nào đang block (chưa khởi động được)?
4. Phase tiếp có cần thay đổi gì?

Ghi review vào `docs/ai/LESSONS.md` của domain liên quan + cập nhật roadmap này.
