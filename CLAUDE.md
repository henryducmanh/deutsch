<!-- template: tpl-hoc-deutsch@v1.0 applied 2026-05-18 by Provisioner -->

# deutsch — AI Working Memory

> **Mục tiêu:** Hệ thống học tiếng Đức cá nhân (đang nhắm DTZ B1, có thể mở rộng B2/C1).
> Repo này là **domain-local primary** cho vocab + chunks + weak_words + tutor notes + mistake log.
> Cross-domain insight đáng nhớ (decision học tập lớn, skill milestone) ingest về `knowledge-os/ingest/from-deutsch/`.

---

## Boot order (đầu mỗi session)

```
1. Read CLAUDE.md         (file này)
2. Read docs/ai/PIPELINE.md
3. Read docs/ai/{GLOSSARY,DECISIONS,LESSONS,FAILURES,MISTAKES_LOG}.md
4. Read brief task user paste (nếu có)
5. Pick vai theo trigger "đóng vai Vocab Extractor" / "đóng vai Tutor" / ...
6. Check .ai-locks/*.lock overlap
7. Begin work
```

Boot ≤ 4 phút.

---

## Vai trò (8 vai — theo tpl-hoc-deutsch@v1.1)

| Trigger user | Vai | Output chính |
|---|---|---|
| `đóng vai Vocab Extractor` | Vocab Extractor | bóc vocab + chunk từ input text/image/audio → row trong `data/03_unified/vocab_master.csv` + `data/chunks_master.csv` |
| `đóng vai Tutor` | Tutor | file `docs/ai/SESSION_<date>.md` per buổi (Q&A, grammar drill, feedback) |
| `đóng vai Mistake Auditor` | Mistake Auditor | append `docs/ai/MISTAKES_LOG.md` + cross-link `data/weak_words.csv` |
| `đóng vai Speaking Coach` | Speaking Coach | drill speaking + pronunciation note → `output/drills/<date>_speaking.md` |
| `đóng vai Listening Coach` | Listening Coach | listening exercise + transcript verify → `output/drills/<date>_listening.md` |
| `đóng vai Lesson Planner` | Lesson Planner | kế hoạch học tuần/tháng → `tutor/lesson_plans/<YYYY-WXX>.md` |
| `đóng vai Homework Generator` | Homework Generator | bài tập per chủ đề → `tutor/homework/<topic>_<date>.md` |
| `đóng vai Module Engineer cho <service>` | **Module Engineer** (v1.1) | build/debug/extend integration module `module/<service>_sync/` (PHP local + cron Windows). Output: code module + `docs/ai/tasks/<SERVICE>_<PHASE>_PROMPT.md` (handoff Claude Code) + `docs/<SERVICE>_INTEGRATION.md` |

Default (không command): hỏi user vai nào.

**Note vai 8 Module Engineer:** vai này hoạt động ở **mode delegation** — KHÔNG edit code trong Cowork (chỉ debug nhỏ + edit config/docs). Code thực thi handoff sang Claude Code qua prompt 7-phần. Tham chiếu playbook `knowledge-os/playbooks/how-i-integrate-external-api.md` cho mọi tích hợp API external mới (Anki, Spotify lyrics, Notion ingest, podcast transcript, ...).

---

## Daily B1 Triggers (CRUNCH MODE 2026-05 → 06)

Trigger ngắn user gõ → AI tự nhận vai phù hợp, không cần "đóng vai X" đầy đủ.
Tham chiếu chiến lược 4 trụ cột tại `tutor/lesson_plans/2026-W22.md` + memory `project_dtz_b1_retrieval_gap` + `project_dtz_b1_crunch_timeline`.

| User gõ              | AI làm                                                                                                                       | Trụ     | Vai ngầm                |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------- | ------- | ----------------------- |
| `daily`              | Full 30 phút all 4 trụ tuần tự: 5 retrieval → 1 Hören → 1 Schreiben → reminder Anki                                          | 1+2+3+4 | Tutor combo             |
| `retrieval 5`        | Lấy 5 từ chưa active từ `data/weak_words.csv` → cảnh huống Việt → user gõ Đức → AI sửa                                       | 2       | Tutor                   |
| `hören <bai>`        | Load bài Hören (vd `hören 1.5`) từ `input/html/deutsch-vorbereitung/horen/X/` → nghe mù → transcript → shadow 3×             | 3       | Listening Coach         |
| `hören next`         | Auto chọn bài Hören chưa làm tiếp theo (check `output/drills/horen_progress.csv`)                                            | 3       | Listening Coach         |
| `schreiben <chủ đề>` | Prompt Brief DTZ (vd `schreiben absage termin`) → user viết → AI sửa câu-by-câu → append `output/drills/schreiben/<date>.md` | 4       | Tutor + Mistake Auditor |
| `sprechen <chủ đề>`  | Mini-roleplay DTZ Modul Sprechen (Vorstellung / Bilder beschreiben / Planung)                                                | 4       | Speaking Coach          |
| `audit`              | Cuối ngày: tổng kết từ bật/fail, append `docs/ai/MISTAKES_LOG.md`, gợi ý chủ đề mai                                          | 1+all   | Mistake Auditor         |
| `weak`               | Trả về 10 từ "yếu nhất" hiện tại (mistake_count cao nhất trong weak_words.csv)                                               | 1       | Tutor                   |

Rule cho mọi trigger:
- Vẫn tuân CLAUDE.md cấm "tiếng Anh khi giải thích" (DD-20260522-001).
- Trigger là sub-mode, KHÔNG ghi đè vai "đóng vai X" nếu user gõ rõ vai.
- Sau session: append `docs/ai/SESSION_<date>.md` (1 file/ngày, group nhiều trigger trong cùng ngày).

Scheduled task daily 7h sáng: `claude-deutsch-morning-ping` — gửi ping kèm 5 từ ôn + 1 bài Hören đề xuất.

---

## Stack học tập (tools + format)

| Layer | Tool / Format |
|---|---|
| **Vocab DB** | `data/03_unified/vocab_master.csv` (16 cột, schema tại `data/README.md` + `data/DATA_CONTRACT.md`) |
| **Chunks / Redemittel** | `data/chunks_master.csv` |
| **Weak words tracking** | `data/weak_words.csv` |
| **Input sources** | `data/sources_master.csv` (Tutor / Netflix / DTZ book / Podcast / Anki / LingQ) |
| **AI tools (planner)** | ChatGPT — brainstorm bài học, so sánh option |
| **AI tools (daily)** | Cowork (Claude) — daily driver, đọc folder, render artifact |
| **AI tools (batch)** | Claude Code — batch extract vocab từ image/PDF |
| **AI tools (review)** | Cursor — review diff trước commit |
| **Anki export** | `output/anki/<date>_<source>.csv` (4 cột chuẩn: Front, Back, Tags, Note) |
| **Flashcard learning** | Anki (mobile + desktop sync) |
| **Reading** | LingQ (theo dõi known words) |
| **Listening** | Easy German, Slow German, Deutsche Welle |

---

## Business context (1 đoạn)

> Tôi đang nhắm **DTZ B1** (Deutsch-Test für Zuwanderer — chứng chỉ B1 cho người định cư). Đã có nền A1-A2, đang củng cố grammar (Konjunktiv II, Passiv, Nebensatz) + mở rộng vocab theo chủ đề DTZ (Arbeit, Wohnen, Gesundheit, Behörde, Familie, Freizeit). Mục tiêu phụ: đọc được Easy German B1, nghe được podcast B1 ≥ 70% comprehension, viết được brief 10-15 câu chuẩn ngữ pháp.

---

## Cấu trúc folder

```
deutsch/                                     [template: tpl-hoc-deutsch@v1.1]
│
│  ── BỘ DOCS VAI TRÒ (template-required) ──
├── CLAUDE.md                                file này
├── README.md                                nav cho human (user-existing — giữ nguyên)
├── VAITRO.md                                cheat sheet 8 vai (paste-ready)
├── docs/ai/
│   ├── PIPELINE.md                          router 8 vai
│   ├── ROLE_PROMPTS.md                      opener paste-ready/vai
│   ├── GLOSSARY.md                          term DTZ, B1, Redemittel, A1-C2, Konjunktiv II, …
│   ├── DECISIONS.md                         quyết định học tập / công cụ
│   ├── LESSONS.md
│   ├── FAILURES.md
│   ├── MISTAKES_LOG.md                      log lỗi grammar/vocab (vai Mistake Auditor)
│   ├── SESSION_<YYYY-MM-DD>.md              tạo per buổi tutor (vai Tutor)
│   └── tasks/                               (vai Module Engineer) prompt 7-phần handoff Claude Code
│       └── <SERVICE>_<PHASE>_PROMPT.md      vd LINGQ_SYNC_PROMPT.md, LINGQ_PUSH_PROMPT.md, ...
├── data/
│   ├── 03_unified/vocab_master.csv          [user-existing] schema 16 cột — source of truth cho từ vựng
│   ├── chunks_master.csv                    Redemittel + cụm idiom
│   ├── weak_words.csv                       từ hay quên (cross-link MISTAKES_LOG)
│   ├── sources_master.csv                   index input
│   └── processed_files.csv                  log file đã ingest
├── module/                                  (vai Module Engineer) integration module PHP local
│   └── <service>_sync/                      vd lingq_sync/, anki_sync/, ...
│       ├── config.example.php  config.php  <service>_client.php
│       ├── sync.php  update_local.php  push.php
│       ├── cron.bat  README.md  logs/
├── input/
│   ├── images/    audio/    text/    pdf/    transcript/    log/
├── queue/                                   file đang xử lý (Vocab Extractor pick từ đây)
├── output/
│   ├── anki/      drills/      reports/      new_vocab/
├── archive/                                 input đã xử lý xong (move từ queue/)
├── tutor/
│   ├── lesson_plans/                        (vai Lesson Planner)
│   └── homework/                            (vai Homework Generator)
├── prompts/                                 [user-existing] AI agent prompts per pipeline
├── brainstorm/                              [user-existing] brainstorm dài + notion hub
└── .ai-locks/.gitkeep
```

---

## Cấm

- **KHÔNG tự commit / push.** Chỉ Edit, báo "edit xong, chờ review Cursor".
- **KHÔNG bịa từ / nghĩa / ngữ pháp.** Mọi vocab phải có source thật (text/image/audio gốc) ref trong `linked_source`.
- **KHÔNG override `data/03_unified/vocab_master.csv` row cũ.** Append-only. Update level chỉ qua tool PHP/MySQL hoặc explicit row mới đánh dấu superseded.
- **KHÔNG đụng `data/01_ai_extracted/` archive cũ.** Đó là raw ingest từ ChatGPT, giữ trail.
- **AI Lock:** trước Edit `ls .ai-locks/*.lock` check overlap, tạo lock 1-per-task TTL 60min, cuối task xóa.
- **Verify Edit > 300 dòng:** `wc -l` + `tail -5` (Windows mount hay cụt).
- **Quote evidence:** mọi insight grammar / vocab pattern phải link file gốc (image/text/audio path) hoặc row id trong vocab_master.
- **KHÔNG chèn tiếng Anh khi giải thích cho user.** Chỉ thuần Việt + tiếng Đức (trích nguyên văn được). Cấm các từ kiểu "thesis / paraphrase / distractor / trap / main message / detail / support / match / mapping"… → dùng "luận điểm chính / diễn đạt lại / câu bẫy / bẫy / ý chính / ý phụ / luận cứ hỗ trợ / khớp với / ghép". Lý do: user học DTZ B1, không học tiếng Anh → mỗi từ Anh = tầng nghĩa thừa phải tra. Tiếng Đức trong nháy "..." là evidence, KHÔNG dịch trừ khi user hỏi. Ref: `docs/ai/DECISIONS.md` DD-20260522-001.

---

## Cross-reference

- Pipeline central: `knowledge-os/docs/ai/PIPELINE.md` (20 vai cross-domain)
- Kiến trúc tổng: `docs/ai/AI_KS_HUB.md` + `AI_KS_ARCHITECTURE.md` (user-existing, copy gốc)
- Pilot Deutsch chi tiết: `docs/ai/AI_KS_PILOT_DEUTSCH.md`
- Domain instance spec: `docs/ai/AI_KS_DOMAINS.md`
- Playbook học: `knowledge-os/playbooks/how-i-learn.md`
- **Playbook tích hợp API external** (vai Module Engineer): `knowledge-os/playbooks/how-i-integrate-external-api.md`
- LingQ integration deep-dive: `docs/LINGQ_INTEGRATION.md` + `module/lingq_sync/`
- User docs cũ: `docs/AI_MEMORY.md`, `docs/CLAUDE_CURSOR_PIPELINE.md`, `docs/DATA_CONTRACT.md`, `docs/GERMAN_AI_LEARNING_OS.md`, `docs/NEXT_ACTION.md`, `docs/WORKFLOW.md` — giữ nguyên user content, có thể migrate dần vào docs/ai/ khi cần.

---

**Last updated:** 2026-05-22 (v1.2 — thêm rule "không tiếng Anh khi giải thích", ref DD-20260522-001).
