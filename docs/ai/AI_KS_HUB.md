# AI Knowledge System — Hub

> **Mục tiêu:** Build một **Personal AI Knowledge OS** dùng chung cho mọi thứ làm hàng ngày — code (PHP projects), học tiếng Đức, SEO, SOP công ty — để Cowork / Claude Code / Cursor / ChatGPT đều truy xuất cùng một bộ nhớ.
>
> **Tinh thần lấy từ:** [`BRAINSTORM_AI_KNOWLEDGE.md`](./BRAINSTORM_AI_KNOWLEDGE.md) (ChatGPT) + [`NOTION_HUB_GERMAN.md`](./NOTION_HUB_GERMAN.md) (Notion second brain hiện có cho Đức).
>
> **Status:** v0 design — chưa scaffold code. Roadmap & next step: xem `AI_KS_ROADMAP.md`.

---

## TL;DR

1. **GitHub repo local = source of truth.** Notion / Sheets / Anki chỉ là UI / output.
2. Mỗi domain (Đức, Dev, SEO, SOP) đều theo cùng một pattern **4 layer**: Input → Processing → Memory → Output.
3. Mỗi domain có **memory file riêng** (CSV master + markdown rules) — AI tool nào cũng đọc chung.
4. Mỗi tool có vai trò rõ: **ChatGPT planner, Claude reader/Cowork, Cursor implementer, Cowork daily-driver**. Không pha trộn vai trò = không bối rối.
5. Triển khai theo 3 phase: bắt đầu **không cần API** (Phase 1), chỉ folder + markdown + CSV + Claude Code đọc trực tiếp.

---

## Nguyên tắc cốt lõi

### 1. GitHub > Notion cho source of truth

ChatGPT brainstorm chốt rất rõ:

```
GitHub = bộ nhớ chuẩn       (version control, diff, AI parsing tốt)
Notion = giao diện quản lý  (filter đẹp, share dễ, nhưng yếu version + bulk)
Anki   = trí nhớ học tập    (SRS)
Vector DB = trí nhớ ngữ nghĩa (phase 2+)
AI tools = nhân viên dùng chung bộ nhớ đó
```

Hệ quả: data động (vocab, keywords, customer list) lưu file CSV/MD trong repo, **không** lưu chính trong Notion. Notion chỉ là mirror đẹp.

### 2. Một vai = một chat (kế thừa từ Notion German hub)

> "Mỗi vai = mỗi chat riêng. Đừng pha trộn (vừa luyện đàm thoại vừa drill grammar trong cùng 1 chat → Claude bối rối)."

Áp dụng cho **mọi domain**, không chỉ Đức:

- **Đức:** Tutor / Vocab Extractor / Speaking Coach / Mistake Auditor / Lesson Planner
- **Dev:** Tiếp nhận / Triển khai / Audit bàn giao / Help (đã có sẵn trong user prefs PIPELINE router)
- **SEO:** Researcher / Writer / Auditor / Cluster Planner
- **SOP:** Customer Onboarder / Incident Recorder / Knowledge Curator

Cùng một file role prompt paste-ready, hết session AI ghi log vào file tương ứng.

### 3. Pattern 4-layer áp dụng đồng nhất

```
INPUT      → folder raw (images/audio/text/pdf/transcript/log)
PROCESSING → AI extract → dedupe → tag → classify (queue/)
MEMORY     → master CSV + markdown rules + index (data/, docs/)
OUTPUT     → Anki / drills / reports / artifacts / commit (output/, archive/)
```

Domain nào cũng làm được (chi tiết: `AI_KS_ARCHITECTURE.md`).

### 4. Tool boundary

| Tool | Vai chính | KHÔNG nên dùng cho |
|---|---|---|
| **ChatGPT** | Brainstorm dài, planner, orchestration | Edit file dài, code commit |
| **Claude Cowork** | Daily driver, đọc folder, schedule, automation chéo domain | Code edit deep |
| **Claude Code** | Batch processing local repo, edit code | Brainstorm flow rộng |
| **Cursor** | Code-aware edit, review diff, commit | Đọc folder lớn không trong repo |
| **Notion** | UI mirror (optional) cho domain cần filter/share | Source of truth, bulk data |
| **Anki** | SRS review | Quản lý dữ liệu chính |

### 5. Không scale sớm

Phase 1 **không** dựng Qdrant / embedding / API. ChatGPT brainstorm nhấn:

> "Đừng lao vào vector DB / Qdrant / embedding pipeline ngay."

→ Chỉ folder + markdown + CSV + Claude Code đọc trực tiếp là **đã đủ** cho 6-12 tháng đầu.

---

## Domain hiện tại (4)

| Domain | Folder gốc | Status | Spoke chi tiết |
|---|---|---|---|
| **Tiếng Đức** | `C:\twv_share\app\deutsch` (workspace hiện tại) | Có Notion + GitHub repo, sẵn 70% nền | `AI_KS_PILOT_DEUTSCH.md` |
| **Dev / PHP web app** | 2-3 repo riêng (user đã có PIPELINE / INBOX / EXEC theo prefs) | Có CLAUDE.md per project | `AI_KS_DOMAINS.md` §Dev |
| **SEO** | TBD (sẽ tạo `seo/` repo riêng nếu xúc tiến) | Chưa có | `AI_KS_DOMAINS.md` §SEO |
| **SOP công ty** | TBD (sẽ tạo `sop/` repo riêng) | Chưa có | `AI_KS_DOMAINS.md` §SOP |

---

## File trong bộ này

- [`AI_KS_HUB.md`](./AI_KS_HUB.md) — file này. Tinh thần + nav.
- [`AI_KS_ARCHITECTURE.md`](./AI_KS_ARCHITECTURE.md) — 4 layers, tool mapping, role prompts pattern, session/mistakes log, AI Lock.
- [`AI_KS_DOMAINS.md`](./AI_KS_DOMAINS.md) — domain template + 4 instances cụ thể.
- [`AI_KS_ROADMAP.md`](./AI_KS_ROADMAP.md) — 3 phase triển khai + quick wins tuần này + pitfalls.
- [`AI_KS_PILOT_DEUTSCH.md`](./AI_KS_PILOT_DEUTSCH.md) — pilot chi tiết cho domain Đức (workspace hiện tại).

Doc nền (input cho synthesis):

- [`BRAINSTORM_AI_KNOWLEDGE.md`](./BRAINSTORM_AI_KNOWLEDGE.md) — ChatGPT brainstorm (14 chat blocks, 2925 dòng).
- [`NOTION_HUB_GERMAN.md`](./NOTION_HUB_GERMAN.md) — Mirror Notion hub Đức.

---

## Open questions / cần user quyết

Sẽ giải quyết khi đến lúc, không block Phase 1:

1. **Multi-repo vs mono-repo?** 1 repo per domain (deutsch, dev-projects, seo, sop) hay 1 mega-repo `knowledge-os/` chứa tất cả với subfolder? *(Khuyến nghị: multi-repo để tách quyền + tốc độ AI index)*.
2. **Notion mirror cho domain nào?** Đức đã có. Dev / SEO / SOP có cần Notion UI hay chỉ markdown trong repo là đủ? *(Khuyến nghị: chỉ Đức + SOP — vì cần share/filter)*.
3. **Cowork artifact cho dashboard?** Có nên build `knowledge_os_dashboard.html` artifact tổng hợp weak_words, open tasks, recent decisions từ tất cả domain? *(Phase 2)*.
4. **Khi nào lên Qdrant/RAG?** Threshold: khi master CSV > 5k rows hoặc khi cross-domain search bắt đầu cần thiết.

---

## Next action (Phase 1, tuần này)

Xem `AI_KS_ROADMAP.md` §"Quick wins tuần này". Tóm tắt:

1. Tạo skeleton folder cho 1 domain mới làm (gợi ý: Dev — vì đang chạy 2-3 dự án).
2. Copy template `docs/ai/PIPELINE.md` từ user prefs vào repo dev.
3. Cấu hình `.ai-locks/` per repo.
4. Sau 1 tuần review: pattern có dùng được không, có cần điều chỉnh không.

---

**Cập nhật doc này khi:** thêm/bớt domain, đổi nguyên tắc cốt lõi, hoặc kết thúc một phase roadmap.
