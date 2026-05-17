# AI Knowledge System — Domains

> 4 domain instances cụ thể của Knowledge OS. Xem kiến trúc chung: [`AI_KS_ARCHITECTURE.md`](./AI_KS_ARCHITECTURE.md).

---

## 0. Template chung (mọi domain)

Khi tạo domain mới, copy skeleton này rồi tùy biến:

```
<domain>/
├── CLAUDE.md                   # AI working memory + boot order
├── README.md
├── docs/
│   ├── GUIDE.md                # tổng quan domain
│   └── ai/
│       ├── PIPELINE.md         # router cho các vai của domain này
│       ├── ROLE_PROMPTS.md     # paste-ready opener
│       ├── GLOSSARY.md
│       ├── DECISIONS.md
│       ├── LESSONS.md
│       ├── FAILURES.md
│       ├── INBOX.md
│       ├── EXEC.md
│       └── HELP.md
├── data/                       # master CSV/JSON
├── input/                      # raw
├── queue/                      # processing
├── output/                     # deliverables
├── archive/                    # đã xử lý
├── prompts/                    # AI agent prompts per pipeline
├── .ai-locks/                  # task lock
└── scripts/                    # helper (Phase 2+)
```

**4 file bắt buộc đầu tiên khi seed domain mới:**

1. `CLAUDE.md` — boot order + 1-paragraph context.
2. `docs/ai/PIPELINE.md` — list các vai.
3. `docs/ai/ROLE_PROMPTS.md` — ≥ 1 paste-ready prompt.
4. `data/<thực-thể>_master.csv` — schema cứng, header comment có `schema_version`.

---

## 1. Domain: Tiếng Đức (German Brain)

**Repo:** `C:\twv_share\app\deutsch` (workspace hiện tại). **Status:** active, 70% nền.

### Mục tiêu sống còn

DTZ B1 — chuyển từ **passive recognition** sang **active production**.
Vấn đề user (theo BRAINSTORM Chat 6, 7): học nhiều → quên nhiều → không retrieve được lúc nói/viết. Giải pháp: chunk learning + multi-context repetition.

### Master files

| File | Schema | Mục đích |
|---|---|---|
| `data/vocab_master.csv` | Wort, Wortart, Formen, Bedeutung, Cụm cố định, Đồng nghĩa, Trái nghĩa, Họ từ, Cấu tạo từ, Câu ví dụ Đức, Nghĩa câu Việt, Chủ đề, Trình độ, Status (1-4 LingQ-style), Source, Last_Seen, Audio_Note | Từ vựng đơn |
| `data/chunks_master.csv` | Chunk Đức, Nghĩa Việt, Dùng khi nào, Chủ đề DTZ, Ví dụ, Level, Status, Source, Last_Seen | Cụm/Redemittel — **quan trọng hơn từ đơn cho DTZ** |
| `data/weak_words.csv` | Wort, Times_Missed, Last_Missed, Reason | Từ hay quên (cho focus drill) |
| `data/processed_files.csv` | filepath, processed_at, agent, output_files | Tránh re-process |
| `data/sources_master.csv` | Source_ID, Type (Tutor/Netflix/DTZ/Book/Podcast), Title, Date, Topic | Index input |

### Vai (roles)

| Vai | File log | Khi nào dùng |
|---|---|---|
| Tutor | `docs/ai/SESSION_<date>.md` | Luyện đàm thoại / drill / sửa lỗi (1-on-1 chat) |
| Vocab Extractor | `output/new_vocab/<date>_<source>.csv` | Bóc từ/chunk từ input batch |
| Speaking Coach | `output/drills/speaking_<topic>.md` | Tạo speaking prompt + audio + roleplay |
| Listening Coach | `output/drills/listening_<topic>.md` | Tạo bài nghe có chunk yếu |
| Mistake Auditor | `docs/ai/MISTAKES_LOG.md` | Đọc tutor_notes, phân loại lỗi, đề xuất drill |
| Lesson Planner | `tutor/lesson_plans/<date>.md` | Tạo giáo án 60-90 phút cho gia sư trước buổi |
| Homework Generator | `tutor/homework/<date>.md` | Sau buổi học, tạo bài tập đa kênh |

### Notion mirror

Hiện đã có (xem `NOTION_HUB_GERMAN.md`). Dùng cho:
- Filter/share view với gia sư.
- Truy cập mobile khi đi xe bus / cafe.

**Source of truth vẫn là CSV trong repo.** Notion auto-sync hoặc manual push từ repo theo Phase 2.

### Tutor Learning Manager (BRAINSTORM Chat 12)

Workflow per buổi học:
```
Trước: AI đọc weak_words + chunks → tạo lesson_plan_YYYY-MM-DD.md cho gia sư
Trong: user/gia sư ghi nhanh vào input/tutor_notes/
Sau: AI đọc tutor_notes → bóc từ/chunk mới → update master CSV
     → tạo homework (speaking + writing + listening + Anki) → archive notes
```

### Pipeline cụ thể

Xem `AI_KS_PILOT_DEUTSCH.md` cho roadmap triển khai từng tuần.

---

## 2. Domain: Dev / PHP web app (2-3 dự án khách)

**Repo:** mỗi dự án 1 repo riêng. **Status:** đang chạy thật cho khách.

### Đặc thù

User là solo dev với pattern đã rõ trong prefs:
- PHP 7.4 + Bootstrap 4.5 + jQuery 3.4.1 + Lexa v1 + DataTables 1.10.20
- AI tool không tự commit/push, user review trong Cursor
- DB schema khai báo ở `module/hethong/db_tools/php/schema_definitions.php` rồi click apply qua admin/db_tools.php — **KHÔNG SQL DDL tay**
- Mỗi project có CLAUDE.md riêng (có thể override stack)

### Master files (per project)

| File | Mục đích |
|---|---|
| `docs/ai/PIPELINE.md` | Router 4 vai: Tiếp nhận / Triển khai / Audit / Help |
| `docs/ai/GLOSSARY.md` | Codename module, từ viết tắt khách dùng, business term |
| `docs/ai/DECISIONS.md` | "Tại sao chọn Lexa thay BS5", "Tại sao auth riêng module" |
| `docs/ai/LESSONS.md` | "DataTables server-side phải dedupe ở SQL", "session timeout phải +CSRF" |
| `docs/ai/FAILURES.md` | Lỗi đã mắc + dấu hiệu nhận diện — **đọc trước khi sửa code tương tự** |
| `docs/ai/INBOX.md` | Vai Tiếp nhận: yêu cầu khách → spec |
| `docs/ai/EXEC.md` | Vai Triển khai: log từng task, diff, test result |
| `docs/ai/HELP.md` | Q&A nhanh trong session |
| `module/hethong/db_tools/php/schema_definitions.php` | **DB schema source of truth** — không SQL tay |

### Vai (đã có trong user prefs — chuẩn hóa lại)

| Vai | Trigger | Ghi vào |
|---|---|---|
| 🟦 Tiếp nhận | "đóng vai tiếp nhận" | INBOX.md (yêu cầu → 7-part spec hoặc Goal/Files/Main/Test) |
| 🟩 Triển khai | "đóng vai người triển khai" | EXEC.md (per task: lock → edit → diff → wait review) |
| 🟧 Audit bàn giao | "đóng vai Audit bàn giao" | (review-only, ghi recommendation vào EXEC.md) |
| 🟨 Help | "đóng vai Help" | HELP.md |

### Git workflow (kế thừa prefs)

- Default: commit thẳng main.
- Branch khi: DB schema / auth-payment / >5 file core.
- Project có staging+prod: làm trên `staging`, user merge sang `main` khi nghiệm thu.
- **AI tuyệt đối không `git commit` / `git push`** — chỉ edit, báo "edit xong, chờ review Cursor".

### Pipeline thực tế

```
User: "đóng vai tiếp nhận: khách Y muốn thêm export Excel cho báo cáo Z"
→ AI: parse → 7-part spec → ghi INBOX.md → 1-dòng paste cho Claude Code

User → Claude Code: "Đọc docs/ai/tasks/Z_PROMPT.md và làm theo"
→ Claude Code: check .ai-locks/ → tạo lock → edit theo prompt → report xong
→ User review trong Cursor → commit
```

### Cấm (kế thừa prefs)

- Bịa schema. Khai báo trong `schema_definitions.php` trước, không guess.
- SQL concat từ user input.
- Sửa `vendor/`.
- `php -l` local trên Windows (treo).
- Tự mở rộng scope ngoài báo giá.
- Suy diễn nghiệp vụ phụ (hỏi user khi mơ hồ).
- Che bug bằng fix expected output — recon code prod trước khi sửa test.

---

## 3. Domain: SEO

**Repo (đề xuất):** `C:\twv_share\app\seo` (tạo mới khi xúc tiến).

### Mục tiêu

ChatGPT brainstorm Chat 5 đề cập: "Bạn làm SEO: AI nhớ keyword, cluster, bài đã viết, kế hoạch tháng."

### Master files

| File | Schema gợi ý | Mục đích |
|---|---|---|
| `data/keyword_master.csv` | Keyword, Cluster, Search_Volume, KD, Intent (Info/Trans/Nav/Comm), Lang, Country, Status (idea/draft/published/archived), Last_Updated | Kho keyword |
| `data/cluster_map.md` | Markdown tree theo cluster → parent topic → child keyword | View cluster |
| `data/content_calendar.csv` | Plan_Date, Cluster, Title, Slug, Type (post/page/cluster-page), Status, Assigned_Keyword, URL | Lịch publish |
| `data/published_history.csv` | URL, Title, Published_Date, Primary_Keyword, Word_Count, Backlink_Status, Updated_At | History |
| `data/competitor_serp.csv` | Keyword, Position_1, Position_2, ..., Snapshot_Date | SERP snapshot (manual) |

### Vai

| Vai | File log | Ghi gì |
|---|---|---|
| Researcher | `docs/ai/research/<keyword>.md` | Phân tích intent, related keyword, gap |
| Cluster Planner | `data/cluster_map.md` | Gom keyword thành cluster, ưu tiên |
| Writer (drafter) | `content/<slug>.md` | Draft bài |
| Auditor | `docs/ai/audits/<url>.md` | Audit bài đã đăng (on-page, internal link) |
| Pattern Curator | `docs/ai/LESSONS.md` | "Cluster X title pattern này CTR cao", "Internal link strategy cho cluster Y" |

### Tool integration (Phase 2+)

- Google Search Console export → `input/gsc/<date>.csv` → AI bóc winner/loser.
- Ahrefs/Semrush export → `input/serp/<date>.csv`.

### Phase 1 minimum

Chỉ 3 file:
- `data/keyword_master.csv` (1 schema)
- `data/cluster_map.md`
- `data/content_calendar.csv`

Đủ để Cursor đọc, đề xuất nội dung viết tiếp.

---

## 4. Domain: SOP công ty

**Repo (đề xuất):** `C:\twv_share\app\sop` (tạo mới khi xúc tiến).

### Mục tiêu

ChatGPT brainstorm Chat 5: "AI nhớ SOP, khách hàng, hosting, domain, mail, quy trình xử lý."
Ví dụ user hỏi: *"VAT domain bên PA là 8% hay 10%"* → AI retrieve được từ mail/docs/luật.

### Master files

| File | Schema gợi ý | Mục đích |
|---|---|---|
| `data/customer_master.csv` | Customer_ID, Name, Contact, Industry, Active_Projects, Hosting_Provider, Notes_URL, Last_Touch | Khách hàng |
| `data/hosting_inventory.csv` | Host_ID, Provider, Domain, IP, Plan, Renewal_Date, Owner, Customer_ID | Hosting |
| `data/domain_inventory.csv` | Domain, Registrar, Renewal_Date, DNS_Provider, Customer_ID, SSL_Expiry | Domain |
| `data/mail_protocols.md` | Bảng SMTP/IMAP per provider, app password notes | Mail config |
| `data/incidents_log.csv` | Date, Customer, Severity, Symptom, Root_Cause, Resolution, Time_Spent | Incident history |
| `data/sop_index.md` | Index các SOP markdown | Nav |
| `sop/<process-name>.md` | Step-by-step SOP per process | SOP files |

### Vai

| Vai | File log | Khi nào |
|---|---|---|
| Customer Onboarder | `sop/onboarding/<customer>.md` | Khách mới: cấu hình host/domain/mail |
| Incident Recorder | `data/incidents_log.csv` + `sop/incidents/<id>.md` | Có sự cố |
| Knowledge Curator | `data/sop_index.md` | Re-organize SOP, dedupe |
| Renewal Watcher | (Cowork scheduled task) | Mỗi tuần: list domain/hosting sắp hết hạn 30 ngày |
| Audit | `docs/ai/audits/<area>.md` | Audit định kỳ (security, backup, license) |

### Cowork integration mạnh nhất ở đây

- **Renewal watcher artifact:** `mcp__cowork__create_artifact` với 1 HTML page list domain/hosting renewal đỏ. Reload mỗi lần mở.
- **Scheduled task:** mỗi sáng 8am, Cowork chạy script bóc mail từ Google Workspace/Outlook (qua MCP), gắn vào `data/incidents_log.csv` nếu là incident.

### Phase 1 minimum

- `data/customer_master.csv`
- `data/hosting_inventory.csv`
- `data/domain_inventory.csv`
- `sop/onboarding/_template.md`

Khởi tạo từ file Excel hiện có (nếu user đang track ở Sheets) → 1-shot migration.

---

## 5. Cross-domain pattern (tham khảo, Phase 2+)

Một số use case cần data từ nhiều domain:

| Use case | Domain liên quan | Cách làm |
|---|---|---|
| "Tổng hợp task pending tuần này" | Dev × N projects + SEO + SOP | Cowork artifact grep `docs/ai/INBOX.md` |
| "Bài học cross-domain" | All | Grep `docs/ai/LESSONS.md` toàn workspace |
| "Tôi học từ 'Verlängerung' rồi → có dùng được cho onboarding khách Đức?" | Đức × SOP | Cowork query manual |
| "Khi vào tuần SEO lớn, AI Lock dev project có conflict?" | Dev × SEO × Lock | Lock file global tại `C:\twv_share\.ai-locks/` (Phase 2) |

---

## 6. Khi nào tách / gộp domain

**Tạo domain mới khi:**
- Có ≥ 1 master entity riêng (không fit domain hiện có)
- Có ≥ 2 vai chuyên biệt
- Có flow input/output đặc thù

**KHÔNG tạo domain riêng cho:**
- 1 task ad-hoc (xử trong domain gần nhất)
- Brainstorm tạm (→ `docs/ai/BRAINSTORM_*.md` trong domain liên quan)

**Gộp 2 domain khi:**
- Master file overlap > 50%
- Vai trùng nhau gần hết

---

**Tham chiếu:**
- Domain Đức lấy từ `BRAINSTORM_AI_KNOWLEDGE.md` Chat 6, 8, 11, 12 + `NOTION_HUB_GERMAN.md`.
- Domain Dev lấy từ user prefs (PHP 7.4 stack, PIPELINE router, git workflow).
- Domain SEO + SOP lấy từ `BRAINSTORM_AI_KNOWLEDGE.md` Chat 3, 5, 10.
