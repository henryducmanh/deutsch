# deutsch.twv.app — Brief brainstorm (PHP online + API ↔ Cowork desktop)

> **Tạo:** 2026-05-29 (Cursor)  
> **Mục đích:** Cowork phân tích → chốt quyết định → sinh handoff Claude Code (`docs/ai/tasks/DEUTSCH_WEB_PHASE1_PROMPT.md`)  
> **Triển khai:** bám stack hiện tại (Cowork desktop + Claude Code + CSV GitHub + `lingq_sync`)  
> **Tham chiếu:** `output/drills/horen_test_4.29-4.31.html`, `module/lingq_sync/`, `docs/ai/DECISIONS.md` DD-20260518-001

---

## 1. Bối cảnh / vấn đề

Repo `deutsch` hiện chạy tốt ở **local** (`C:\twv_share\app\deutsch`):

| Thành phần | Hiện trạng |
|---|---|
| Não dữ liệu | `vocab_master.csv`, `weak_words.csv`, `chunks_master.csv` |
| LingQ pipeline | `module/lingq_sync/` + cron Windows |
| Bài Hören | 344 folder (`input/html/deutsch-vorbereitung/horen/`), ~220 MB MP3 |
| Drill UI | Prototype `output/drills/horen_test_4.29-4.31.html` — layout Cowork đã chốt |
| AI xử lý chính | Cowork desktop (giáo án gia sư, audit, push LingQ) |

**Thiếu:**

- Giao diện học **online mọi nơi** (laptop + mobile)
- Ghi nhận hành vi học realtime (điểm bài, từ click, session)
- Cầu nối ngược về Cowork/local/GitHub/LingQ **không phá** stack hiện tại

**Không muốn:**

- Viết lại German Brain trên web
- Biến Notion/LingQ thành source of truth
- Copy nguyên spec `brainstorm/dtz-training-os/` (ChatGPT brainstorm — chỉ tham khảo ý tưởng)

---

## 2. Tầm nhìn (1 câu)

**`https://deutsch.twv.app`** = lớp **tương tác học cá nhân online** (login, drill Hören/Lesen, click từ không biết, lưu tiến độ).

**`https://deutsch.twv.app/api/`** = cầu dữ liệu cho **Cowork desktop** pull xuống local → xử lý AI → append CSV → push LingQ → đồng bộ lại panel vocab bài học.

---

## 3. Sơ đồ kiến trúc

```text
                    ┌─────────────────────────┐
                    │   Cowork Desktop (AI)   │
                    │  giáo án · audit · LingQ │
                    └───────────┬─────────────┘
                                │ cron / lịch
                    GET/POST  deutsch.twv.app/api/
                                │
┌───────────────┐     ┌─────────▼─────────┐     ┌──────────────┐
│ GitHub repo   │◄───►│  deutsch.twv.app  │◄───►│   LingQ      │
│ CSV/MD truth  │     │  PHP + login + DB   │     │  review SRS  │
└───────────────┘     └─────────▲─────────┘     └──────────────┘
                                  │
                          User (laptop/mobile)
                          tự học / cùng gia sư
```

---

## 4. Phân vai (không lẫn)

| Thành phần | Vai trò | Source of truth? |
|---|---|---|
| **GitHub + CSV local** | `vocab_master`, `weak_words`, quyết định, lesson source | ✅ Có (curated) |
| **deutsch.twv.app (PHP)** | UI học + event log học tập | ❌ Operational DB |
| **Cowork desktop** | Não AI: xử lý event → append CSV → LingQ | ✅ Orchestrator |
| **LingQ** | Review mobile/desktop | ❌ Chỉ engine SRS |
| **Claude Code** | Build `module/deutsch_web/`, batch lesson, CLI sync | Implementer |
| **ChatGPT / dtz-training-os** | Brainstorm ý tưởng | ❌ Không triển khai trực tiếp |

---

## 5. Vì sao PHP (pattern giống dự án mieu)

- Stack PHP CLI đã có (`lingq_sync`) + cron Windows — mở rộng sang web cùng ngôn ngữ
- Host sẵn **`https://deutsch.twv.app`** — tái dùng auth/session pattern từ **mieu** (login cá nhân)
- Drill hiện tại là HTML/CSS/JS thuần — PHP **serve + inject data + API**, không cần framework nặng
- Mobile: layout drill Cowork đã responsive (panel vocab, audio player sticky)

**Không** dùng PHP gọi GitHub API mỗi lần user click — web ghi DB server; Cowork/desktop lo sync GitHub sau xử lý.

---

## 6. Chức năng MVP (Phase 1 — trước thi DTZ 06/2026)

### 6.1 Web học (authenticated)

- Login cá nhân (1 user hoặc vài user — giống mieu)
- Trang danh sách bài Hören (từ `input/html/deutsch-vorbereitung/horen_lessons.csv`)
- Render drill theo template `output/drills/horen_test_4.29-4.31.html`:
  - tab bài, audio player, làm bài, chấm điểm, transcript
  - **panel vocab bên phải** (load từ server — không hardcode `vocabData` trong JS)

### 6.2 Click từ → « chưa biết »

Trong panel vocab + highlight trong đề:

- Click từ → **« Thêm vào chưa biết »** (hoặc toggle: new / ok / hard — giống `lv-new/ok/hard` hiện tại)
- Ghi event server-side

Fields tối thiểu:

```text
word, lesson_id, context_sentence, word_status, clicked_at, sync_status=pending
```

### 6.3 Ghi nhận tiến độ học

Thay ghi tay `output/drills/horen_progress.csv`:

```text
lesson_id, completed_at, score (dung/tong), wrong_items[], notes
```

Cowork pull về → append CSV local (giữ schema hiện có).

### 6.4 API cho Cowork desktop

Base: **`https://deutsch.twv.app/api/`**

Auth: API key header `Authorization: Bearer ...` — chỉ Cowork/CLI, không expose frontend.

| Endpoint | Hướng | Mục đích |
|---|---|---|
| `GET /api/events?since=ISO8601` | Web → Cowork | Event mới: progress, word_mark, lesson_complete |
| `GET /api/unknown_words/pending` | Web → Cowork | Queue từ user đánh dấu chưa biết |
| `POST /api/events/ack` | Cowork → Web | Đánh dấu đã xử lý |
| `GET /api/lessons/{id}/vocab` | Web ← data | Vocab panel cho bài (JSON) |
| `POST /api/lessons/{id}/vocab` | Cowork → Web | Push vocab đã enrich sau xử lý local (phase 2) |

**Cowork desktop cron** (vd 30 phút hoặc sau buổi học):

```text
1. GET /api/events?since=last_sync
2. Mỗi unknown_word → Cowork quyết append vocab_master / weak_words
3. Chạy module/lingq_sync/update_local.php + push.php
4. POST /api/events/ack
5. (Tuỳ chọn) git commit CSV → GitHub
```

---

## 7. Luồng dữ liệu « thông suốt »

### User học trên mobile (xa máy nhà)

```text
Mobile → deutsch.twv.app → làm bài 4.30 → 2/3 đúng
       → click «Automatisierung», «antizipieren» = chưa biết
       → lưu DB server
```

### Cowork desktop (máy có repo local)

```text
Cowork pull API
  → 2 từ pending + score 4.30
  → append weak_words / vocab_master (linked_source = Hören 4.30)
  → ghi MISTAKES_LOG nếu có pattern lỗi
  → push LingQ qua lingq_sync
  → ack API
  → gen giáo án gia sư tuần sau
```

### Đồng bộ panel vocab

```text
Ban đầu: vocab panel từ batch extract / filter vocab_master theo lesson
User đổi trạng thái từ trên web → lưu DB ngay
Cowork pull → merge weak_words
Cowork POST vocab enriched → web hiển thị lần học sau
```

**GitHub:** mirror CSV sau xử lý Cowork — web **không** ghi thẳng GitHub.

---

## 8. Cấu trúc module đề xuất (monorepo `deutsch`)

```text
module/deutsch_web/              ← deploy lên deutsch.twv.app
├── public/
│   ├── index.php                ← router
│   └── assets/
│       ├── drill.css            ← tách từ horen_test
│       └── drill.js
├── api/
│   ├── events.php
│   └── unknown_words.php
├── views/
│   └── drill_horen.php
├── lib/
│   ├── auth.php                 ← reuse pattern mieu
│   └── db.php
├── config.example.php
└── README.md

module/deutsch_web_sync/         ← CLI trên máy Cowork (local)
├── pull_events.php              ← pull API → staging JSON/CSV
├── config.example.php
└── README.md
```

**Phase 0 (Claude Code, trước web):** tách template từ `horen_test_4.29-4.31.html` → `assets/` + `lessons/4.29.json` (1 bài pilot).

---

## 9. Schema event (draft — Cowork chốt)

```json
{
  "event_id": "uuid",
  "type": "horen_complete | word_mark | lesson_open",
  "lesson_id": "4.29",
  "payload": {
    "score": "2/3",
    "wrong": ["0-1"],
    "word": "antizipieren",
    "word_status": "hard",
    "context": "…"
  },
  "created_at": "2026-05-29T14:30:00Z",
  "synced": false
}
```

---

## 10. Phase triển khai

| Phase | Deliverable | Tool |
|---|---|---|
| **0** | Tách CSS/JS + 1 lesson JSON từ `horen_test` | Claude Code |
| **1** | PHP serve drill + login (pattern mieu) trên twv.app | Claude Code |
| **2** | Click từ + progress DB + API read | Claude Code |
| **3** | `deutsch_web_sync/pull_events.php` + doc Cowork workflow | Claude Code + Cowork |
| **4** | Nối pull → `weak_words` → `lingq_sync` | Cowork |
| **5** | Batch 344 bài Hören | Claude Code batch |

**Không làm trong phase 1:** multi-user public, GitHub API từ web, LMS đủ 4 modul DTZ.

---

## 11. Nguyên tắc bám repo

1. `vocab_master.csv` = source of truth — web chỉ **queue**, Cowork **curate** rồi append  
2. LingQ = push từ local qua `lingq_sync` — không push trực tiếp từ web  
3. Drill layout Cowork đã chốt — web reuse, không redesign  
4. Giải thích cho user: thuần Việt + tiếng Đức evidence (DD-20260522-001)  
5. ChatGPT brainstorm ≠ blueprint triển khai  

---

## 12. Câu hỏi Cowork cần chốt trước Claude Code

1. **DB web:** SQLite đủ (1 user) hay MySQL shared với mieu?  
2. **Auth:** user table chung mieu hay `deutsch_users` riêng?  
3. **Audio:** host MP3 trên twv.app (~220 MB) hay CDN / LingQ S3 như file demo?  
4. **Sync tần suất:** pull sau mỗi session hay cron 30 phút?  
5. **Ack model:** xóa event sau ack hay giữ audit log?  
6. **Vocab panel:** JSON tĩnh deploy cùng lesson hay DB động (Cowork POST enrich)?  
7. **Gia sư mode:** cùng URL xem progress user, hay solo-only phase 1?  

---

## 13. Definition of Done (hệ thống thông suốt)

```text
[ ] Login deutsch.twv.app trên mobile, làm xong bài Hören
[ ] Click 3 từ « chưa biết » → có trong DB web
[ ] Cowork pull API → 3 từ xuất hiện staging local
[ ] Cowork append weak_words + push LingQ OK
[ ] Lần mở bài sau: panel vocab phản ánh trạng thái đã sync
[ ] horen_progress không cần ghi tay
[ ] GitHub có commit CSV sau vòng xử lý Cowork
```

---

## 14. Opener paste cho Cowork

```
Đọc brainstorm/deutsch-web-platform-brief.md

Đóng vai Module Engineer + Lesson Planner:
- Phân tích brief deutsch.twv.app (PHP online + API ↔ Cowork desktop)
- Chốt câu trả lời mục 12 (DB, auth, audio, sync, vocab panel)
- Sinh docs/ai/tasks/DEUTSCH_WEB_PHASE1_PROMPT.md (format 7 phần giống LINGQ_SYNC_PROMPT.md)
- Phase 0 trước: tách template horen_test → assets + lesson JSON pilot 4.29

Bám: vocab_master = source of truth, lingq_sync local, không GitHub API từ web.
```

---

## 15. Liên kết file liên quan

| File | Vai trò |
|---|---|
| `output/drills/horen_test_4.29-4.31.html` | Template UI vàng |
| `output/drills/horen_progress.csv` | Schema progress hiện tại |
| `data/weak_words.csv` | Đích sync từ « chưa biết » |
| `data/03_unified/vocab_master.csv` | Source of truth từ vựng |
| `module/lingq_sync/` | Push LingQ sau Cowork xử lý |
| `input/html/deutsch-vorbereitung/horen_lessons.csv` | Index 344 bài |
| `docs/ai/DECISIONS.md` | Quyết định tool boundary |
| `brainstorm/dtz-training-os/` | Brainstorm tham khảo — không copy schema |
