# AI Knowledge System — Architecture

> Đi kèm: [`AI_KS_HUB.md`](./AI_KS_HUB.md) (tinh thần) · [`AI_KS_DOMAINS.md`](./AI_KS_DOMAINS.md) (instances) · [`AI_KS_ROADMAP.md`](./AI_KS_ROADMAP.md) (phase).

---

## 1. 4-Layer pattern

Mọi domain (Đức, Dev, SEO, SOP) tổ chức theo cùng 4 layer. Đây là **xương sống** của hệ thống.

```
┌────────────────────────────────────────────────────────────┐
│ LAYER 1 — INPUT  (raw, chưa xử lý)                         │
│   input/{images,audio,text,pdf,transcript,log,paste}/      │
└──────────────────────────┬─────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────┐
│ LAYER 2 — PROCESSING (AI extract + normalize)              │
│   queue/        (file đang xử lý)                          │
│   prompts/      (AI agent prompt cho từng pipeline)        │
│   scripts/      (Python/PHP helper, optional)              │
└──────────────────────────┬─────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────┐
│ LAYER 3 — MEMORY (source of truth)                         │
│   data/         (master CSV/JSON — version controlled)     │
│   docs/         (rules, glossary, decisions, lessons)      │
│   docs/ai/      (PIPELINE, role prompts, AI memory)        │
└──────────────────────────┬─────────────────────────────────┘
                           ↓
┌────────────────────────────────────────────────────────────┐
│ LAYER 4 — OUTPUT (deliverable + reinforcement)             │
│   output/{anki,reports,drills,artifacts}/                  │
│   archive/      (input đã xử lý, để audit lại)             │
└────────────────────────────────────────────────────────────┘
```

### Quy tắc dòng chảy

- **Input → Queue → Memory → Output → Archive.** Không skip step.
- **Memory chỉ append/update qua AI agent có prompt rõ.** Không sửa tay master CSV trừ khi sửa lỗi schema.
- **Archive ≠ delete.** Source file gốc giữ lại để audit / re-process khi đổi prompt.

---

## 2. Folder convention chung

Mọi domain repo có cấu trúc tối thiểu:

```
<domain-repo>/
├── input/
│   ├── images/      # OCR target
│   ├── audio/       # transcribe target
│   ├── text/        # paste / copy / subtitle / transcript
│   ├── pdf/         # đọc / extract
│   └── log/         # chat log, mail log, terminal log
│
├── queue/           # file đang được AI agent xử lý (lock 1-per-task)
│
├── data/            # master CSV/JSON — source of truth
│   ├── *_master.csv
│   └── processed_files.csv
│
├── docs/
│   ├── ai/
│   │   ├── PIPELINE.md      # router cho các vai
│   │   ├── ROLE_PROMPTS.md  # paste-ready opener cho từng vai
│   │   ├── GLOSSARY.md
│   │   ├── DECISIONS.md     # các quyết định lớn (why + when)
│   │   ├── LESSONS.md       # bài học rút ra
│   │   ├── FAILURES.md      # lỗi đã mắc + cách phòng
│   │   ├── INBOX.md         # vai Tiếp nhận ghi vào đây
│   │   ├── EXEC.md          # vai Triển khai ghi vào đây
│   │   └── HELP.md          # vai Help ghi vào đây
│   ├── GUIDE.md             # tổng quan domain
│   └── ...
│
├── output/
│   ├── anki/                # nếu là domain học (Đức)
│   ├── reports/             # cho dev/SEO/SOP
│   ├── drills/              # speaking/writing/listening (Đức)
│   └── artifacts/           # Cowork artifacts HTML
│
├── archive/                 # input đã xử lý, cùng tree với input/
│
├── prompts/                 # AI agent prompts (per pipeline)
├── scripts/                 # helper script (optional, Phase 2+)
└── .ai-locks/               # task lock 1-per-task, TTL 60min

CLAUDE.md                    # root-level: AI working memory + boot order
README.md
```

---

## 3. Role prompts pattern

### Quy tắc vàng (kế thừa Notion Đức, mở rộng)

> Mỗi session chat với Claude → **mở đầu bằng 1 role prompt từ `docs/ai/ROLE_PROMPTS.md`**. Kết thúc session → AI tự update file log của vai đó.

### Format role prompt

Mỗi role có 1 block paste-ready trong `docs/ai/ROLE_PROMPTS.md`:

```markdown
## 🎭 Role: <tên vai>

**Boot order:** đọc CLAUDE.md → docs/ai/PIPELINE.md → docs/ai/<file-vai>.md.

**Bạn chỉ ghi vào:** docs/ai/<file-vai>.md (KHÔNG đụng file vai khác).

**Quy tắc:**
- <constraint 1>
- <constraint 2>

**Đầu vào tôi sẽ paste:** <kiểu input>
**Đầu ra mong muốn:** <format output>

---

(Paste prompt above khi mở chat mới)
```

### PIPELINE router

`docs/ai/PIPELINE.md` mô tả các vai sẵn cho domain đó. Đầu chat user gõ:

> "đóng vai tiếp nhận" / "đóng vai triển khai" / "đóng vai Audit" / "đóng vai Help"

AI tự pick role prompt tương ứng (đã có sẵn trong user prefs cho dev domain).

---

## 4. Tool mapping chi tiết

### ChatGPT — Planner & Brainstormer

**Khi nào dùng:**
- Brainstorm hướng mới, so sánh option, kiến trúc cao
- Plan multi-domain (cross-cutting)
- Research market / tool comparison
- Soạn prompt cho Claude/Cursor

**Output đi đâu:** copy về `docs/ai/BRAINSTORM_<chủ-đề>.md` (như BRAINSTORM_AI_KNOWLEDGE.md hiện tại).

**KHÔNG dùng cho:** edit file trực tiếp, đụng git, commit.

### Claude Cowork — Daily driver & Cross-domain

**Khi nào dùng:**
- Đọc folder local nhiều file (German Brain pipeline)
- Tạo artifact dashboard (cross-domain status)
- Scheduled task (mỗi ngày scan input/, mỗi tuần audit weak_words)
- Cross-domain query: "tổng hợp task pending all repo"

**Quyền:** đọc/ghi `C:\twv_share\app\deutsch` (và các workspace folder khác user mount).

### Claude Code — Batch processing & In-repo edit

**Khi nào dùng:**
- Batch extract vocab từ 50+ file local
- Refactor module trong repo dev
- Edit file > 5 file trong repo, theo prompt rõ
- Test runner / log audit

**Lưu ý:** theo user prefs — **KHÔNG tự commit/push**, chỉ edit. User review trong Cursor Changes panel.

### Cursor — Code-aware edit & Review

**Khi nào dùng:**
- Edit theo prompt cụ thể, từng file
- Review diff trước commit
- Inline AI khi gõ code

**Commit từ Cursor:** user click commit từ CHANGES panel sau khi review.

### Notion — UI mirror (optional)

**Khi nào cần:**
- Domain có filter phức tạp (vocab Đức 1000+ rows muốn filter theo Thema + Status)
- Cần share view với người khác (gia sư, khách hàng)
- Mobile access nhanh

**KHÔNG dùng cho:** source data primary. Notion = mirror đọc, sửa thì sửa CSV trong repo rồi sync ngược.

### Anki — SRS engine

Chỉ cho domain học (Đức). Export `output/anki/*.csv` rồi import.

---

## 5. Memory file convention

### Master CSV

Mỗi domain có ≥ 1 master CSV trong `data/`. Schema cố định, version trong header comment.

Ví dụ Đức:
```
# schema_version: 2026-05-17_v1
# columns: Wort,Wortart,Formen,Bedeutung,Beispiel,Thema,Level,Status,Source,Last_Seen,Audio,Tags
Wort,Wortart,Formen,Bedeutung,...
unterstützen,Verb,unterstützt|unterstützte|unterstützt,hỗ trợ,...
```

### Markdown rules file

- `DECISIONS.md` — quyết định lớn (architecture, tool chọn, schema change). Format: `## YYYY-MM-DD — <tiêu đề>` + Why + Alternatives considered + Status.
- `LESSONS.md` — bài học rút từ thực hành. Format ngắn.
- `FAILURES.md` — lỗi đã mắc + dấu hiệu nhận biết + cách phòng. **Để Claude/Cursor đọc trước khi sửa code tương tự.**
- `GLOSSARY.md` — thuật ngữ domain (B1, DTZ, Redemittel, Lexa, dự án X codename...).

### Session log

- `INBOX.md` — vai Tiếp nhận: user paste yêu cầu, AI parse + clarify questions + tạo task spec.
- `EXEC.md` — vai Triển khai: log từng task thực thi, diff, kết quả test.
- `HELP.md` — vai Help: Q&A nhanh.
- (Đức) `SESSIONS_LOG.md` — log buổi học, từ mới, lỗi gặp.
- (Đức) `MISTAKES_LOG.md` — pattern lỗi lặp.

---

## 6. AI Lock pattern (kế thừa user prefs)

```
.ai-locks/
└── <task-id>.lock
```

**Quy tắc:**
- Trước khi Edit: `ls .ai-locks/*.lock` check overlap.
- Tạo lock mới: 1 lock per task, TTL 60 min.
- Cuối task: xóa lock.
- Lock content: task id, file đang đụng, agent name, timestamp.

**Cross-domain:** nếu Cowork đang scan input/ German Brain, Claude Code không được edit đồng thời file `data/vocab_master.csv` — sẽ check qua lock.

---

## 7. Source-of-truth principle (nhắc lại)

```
GitHub repo local  =  primary
Notion             =  mirror UI (optional)
Sheets             =  ad-hoc export, không sync ngược
Anki               =  output, không là source
ChatGPT memory     =  ephemeral, không tin
Cowork artifact    =  view-only render từ source
```

Hệ quả:
- Sửa data: **luôn** sửa file trong repo, không sửa trong Notion/Sheets.
- Mất Notion: rebuild được trong 5 phút từ repo.
- Mất repo: thảm họa → backup git remote (GitHub private) + cron mỗi tuần.

---

## 8. Boot order chuẩn (đầu mỗi session AI)

Theo user prefs (mở rộng cho mọi domain):

```
1. Read CLAUDE.md (root domain repo)
2. Read docs/ai/PIPELINE.md
3. Read docs/ai/{GLOSSARY,DECISIONS,LESSONS,FAILURES}.md
4. Read brief task nếu user paste sau
   (cho task lớn: 7 phần — end-user / màn cuối / ví dụ dữ liệu thật /
    acceptance test / cấm đụng module nào / performance / format report)
   (cho task nhỏ: Goal/Files/Main Work/Test, ≤ 8 dòng)
5. Pick role prompt từ docs/ai/ROLE_PROMPTS.md theo command "đóng vai X"
6. Check .ai-locks/ overlap
7. Begin work
```

Thời gian boot mục tiêu: **≤ 4 phút** đọc.

---

## 9. Cross-domain interaction

Đa số task **không** cross-domain. Khi cross:

| Use case | Cách làm |
|---|---|
| "Tổng hợp task pending tất cả repo" | Cowork artifact đọc `docs/ai/INBOX.md` từ N repo. |
| "Tìm decision cũ về schema migration" | Grep `docs/ai/DECISIONS.md` toàn workspace. |
| "Export weak_words Đức + bad-prompt patterns SOP để brainstorm chéo" | Cowork query đọc 2 master CSV → render artifact. |

Không tạo database cross-domain primary. Cross-domain = **view** ad-hoc.

---

## 10. Schema evolution

Đổi schema master CSV = **decision lớn**:

1. Ghi vào `docs/ai/DECISIONS.md` (when + why + migration plan).
2. Bump `schema_version` trong header CSV.
3. Migration script: 1-shot SQL/Python, archive trong `scripts/migrations/YYYY-MM-DD_*.py`.
4. Re-process input (nếu cần) bằng prompt mới.

**KHÔNG** in-place edit master schema không log decision — đó là cách mất data.

---

**Tham chiếu:**
- 4-layer pattern + tool boundary lấy từ `BRAINSTORM_AI_KNOWLEDGE.md` Chat 1, 2, 5, 10, 14.
- Role prompts pattern lấy từ `NOTION_HUB_GERMAN.md` + user prefs PIPELINE router.
- AI Lock lấy từ user prefs (`.ai-locks/*.lock`).
