# AI Knowledge System — Pilot: German Brain

> Cụ thể hóa kiến trúc cho domain Đức trong workspace `C:\twv_share\app\deutsch`. Tham chiếu: [`AI_KS_HUB.md`](./AI_KS_HUB.md), [`AI_KS_ARCHITECTURE.md`](./AI_KS_ARCHITECTURE.md), [`AI_KS_DOMAINS.md`](./AI_KS_DOMAINS.md) §1.

---

## 0. Tại sao Đức là pilot

- **Đã có 70% nền** (theo BRAINSTORM Chat 14): repo `henryducmanh/app-deutsch`, schema 14 cột, docs cấu trúc, data/01_ai_extracted/03_unified, README rõ.
- **Đã có Notion hub** (xem `NOTION_HUB_GERMAN.md`): 4 databases + 4 pages tĩnh.
- **Pain point rõ:** học nhiều quên nhiều, không retrieve được lúc nói/viết (Chat 6, 7).
- **Tác động mỗi ngày:** học Đức là daily task — pipeline đúng giúp B1 DTZ nhanh hơn.

→ Build đúng ở đây → pattern copy sang Dev, SEO, SOP dễ.

---

## 1. State hiện tại

### Có sẵn

```
deutsch/
├── README.md
├── data/
│   ├── 01_ai_extracted/    # ChatGPT bóc từ → CSV
│   ├── 02_tool_exports/    # PHP/MySQL xử lý
│   └── 03_unified/
│       └── vocab_master.csv  (schema 14 cột)
├── docs/
│   └── ai/                  # vừa tạo: HUB, ARCHITECTURE, DOMAINS, ROADMAP, PILOT (file này)
└── brainstorm/
```

Schema hiện có (theo Chat 14):
```
id, wort, wortart, formen, bedeutung, beispiel, uebersetzung,
thema, lerndatum, level, quelle, source_type, tags, notes
```

### Còn thiếu (cần seed)

```
deutsch/
├── data/
│   ├── chunks_master.csv          ← thiếu (quan trọng nhất cho B1)
│   ├── weak_words.csv             ← thiếu
│   ├── sources_master.csv         ← thiếu
│   └── processed_files.csv        ← thiếu
├── docs/ai/
│   ├── PIPELINE.md                ← thiếu
│   ├── ROLE_PROMPTS.md            ← thiếu (Notion có, chưa local)
│   ├── GLOSSARY.md                ← thiếu
│   ├── DECISIONS.md               ← thiếu
│   ├── LESSONS.md                 ← thiếu
│   ├── FAILURES.md                ← thiếu
│   ├── SESSIONS_LOG.md            ← thiếu
│   └── MISTAKES_LOG.md            ← thiếu
├── input/                          ← thiếu folder convention
│   ├── images/
│   ├── audio/
│   ├── text/
│   ├── pdf/
│   └── tutor_notes/
├── queue/                          ← thiếu
├── output/
│   ├── anki/
│   ├── drills/
│   └── new_vocab/
├── archive/                        ← thiếu
├── tutor/                          ← thiếu (BRAINSTORM Chat 12)
│   ├── lesson_plans/
│   ├── homework/
│   └── progress_reports/
├── prompts/                        ← thiếu
└── .ai-locks/                      ← thiếu
```

---

## 2. Schema nâng cấp (Phase 1)

### vocab_master.csv (extend)

Giữ 14 cột hiện có, **thêm 3 cột**:

```
schema_version: 2026-05-17_v2
columns:
  - id                  (giữ)
  - wort                (giữ)
  - wortart             (giữ)
  - formen              (giữ)
  - bedeutung           (giữ)
  - beispiel            (giữ)
  - uebersetzung        (giữ)
  - thema               (giữ)
  - lerndatum           (giữ)
  - level               (giữ)
  - quelle              (giữ)
  - source_type         (giữ)
  - tags                (giữ)
  - notes               (giữ)
  - status              (mới, 1-4 LingQ-style)
  - last_seen           (mới, ISO date)
  - audio_note          (mới, link file audio nếu có)
```

Decision: `docs/ai/DECISIONS.md` ghi rõ schema_version bump + migration plan (chỉ append, default status=1, last_seen=lerndatum).

### chunks_master.csv (mới)

```
schema_version: 2026-05-17_v1
columns:
  - chunk_id
  - chunk_de            (Es hängt davon ab, ob ...)
  - chunk_vi            (Tôi cho rằng ...)
  - usage_context       (nêu ý kiến / xin lỗi / hỏi đường)
  - thema_dtz           (Familie / Arbeit / Wohnung / ...)
  - example             (Ich bin der Meinung, dass Kinder früh Deutsch lernen sollten)
  - level               (A2 / B1 / B2)
  - status              (1-4)
  - source              (Tutor / Netflix / DTZ-Test)
  - last_seen
```

→ **Quan trọng hơn vocab cho DTZ B1** (BRAINSTORM Chat 7, 11).

### weak_words.csv (mới)

```
columns:
  - wort
  - times_missed        (đếm số lần quên/dùng sai)
  - last_missed         (date)
  - reason              (recognition / production / pronunciation)
  - linked_chunks       (chunk_id liên quan)
```

→ Source cho Speaking Coach + Lesson Planner.

### sources_master.csv (mới)

```
columns:
  - source_id
  - type                (Tutor / Netflix / DTZ / Book / Podcast / OCR)
  - title
  - date
  - topic_main
  - lang_level
  - processed           (yes/no)
```

---

## 3. Vai (roles) — file ROLE_PROMPTS.md

Tổng 7 vai cho Đức, mỗi vai có paste-ready opener:

### 🎭 Vai 1: Tutor (chat 1-on-1 luyện đàm thoại)

```
Bạn là gia sư tiếng Đức của tôi, chuẩn bị DTZ B1.

Đọc:
- CLAUDE.md
- docs/ai/PIPELINE.md
- docs/ai/GLOSSARY.md
- data/weak_words.csv (top 20)
- data/chunks_master.csv (status ≤ 2, level B1)

Quy tắc:
- Trả lời tiếng Đức trước, kèm dịch Việt ngắn khi cần.
- Ưu tiên dùng chunks status ≤ 2 trong câu hỏi.
- Sửa lỗi tôi nói: chỉ rõ chunk đúng, ghi ngay vào docs/ai/MISTAKES_LOG.md.
- Cuối session: bảo tôi "update Notion" và update CSV liên quan.

Mục tiêu hôm nay: <topic>
```

### 🎭 Vai 2: Vocab Extractor (batch bóc từ)

```
Bạn là agent bóc từ vựng từ batch input local.

Đọc:
- prompts/extract_vocab.md
- data/vocab_master.csv (header)
- data/chunks_master.csv (header)
- data/processed_files.csv

Việc:
1. List file trong input/text/ và input/tutor_notes/ chưa có trong processed_files.csv.
2. Với mỗi file: bóc từ B1 DTZ + chunk + Redemittel.
3. Skip từ A1 quá dễ (đã biết rõ).
4. Dedupe với master.
5. Output:
   - output/new_vocab/<date>_<source>.csv (vocab mới)
   - output/new_chunks/<date>_<source>.csv (chunks mới)
6. Append processed_files.csv.
7. Archive input → archive/<type>/<date>/.

Không merge thẳng vào master.csv — chờ user review.
```

### 🎭 Vai 3: Speaking Coach

```
Bạn là speaking coach, tạo drill cho tôi production-first.

Đọc:
- data/weak_words.csv (top 15)
- data/chunks_master.csv (status ≤ 2, level B1, thema = <topic>)
- docs/ai/MISTAKES_LOG.md (lỗi nói gần đây)

Việc:
1. Tạo 5 mini-conversation cho topic <topic>, mỗi conv 4-6 câu.
2. Mỗi conv dùng ít nhất 2 weak words + 1 chunk yếu.
3. Bổ sung phần "ghi âm 30 giây trả lời" với prompt cụ thể.
4. Output: output/drills/speaking_<date>_<topic>.md

Không lặp lại drill tuần qua (xem output/drills/ history).
```

### 🎭 Vai 4: Listening Coach

```
Đọc weak_words + chunks_master, tạo bài nghe ngắn (60-90s) có chứa
chunk yếu, transcript A/B variant (nói nhanh vs chậm), với 3 câu hỏi
multiple choice. Output: output/drills/listening_<date>_<topic>.md.
```

### 🎭 Vai 5: Mistake Auditor

```
Đọc input/tutor_notes/ + output/drills/ recent → phân loại lỗi:
- recognition (nghe không bắt được)
- production (không nói/viết ra được)
- pronunciation
- grammar (Akkusativ/Dativ/Genus/Konjugation)
Append docs/ai/MISTAKES_LOG.md với pattern + frequency.
Nếu pattern lặp ≥ 3 lần: flag vào docs/ai/FAILURES.md + đề xuất drill.
```

### 🎭 Vai 6: Lesson Planner (cho gia sư)

```
Đọc:
- weak_words.csv top 20
- chunks_master.csv status ≤ 2 ưu tiên DTZ topic này tuần
- MISTAKES_LOG.md tuần qua
- docs/ai/Current_Focus.md (mục tiêu tuần)

Tạo giáo án 60-90 phút cho gia sư (tutor/lesson_plans/<date>.md):
- Mục tiêu cụ thể
- 15-20 từ/chunk cần kích hoạt
- 3-5 câu hỏi nói (open-ended)
- 1-2 roleplay tình huống thật
- Bài nghe ngắn nếu có
- Output mong muốn cuối buổi
```

### 🎭 Vai 7: Homework Generator (sau buổi)

```
Đọc tutor_notes/<date>.md + lesson_plan tương ứng.
Tạo homework đa kênh (tutor/homework/<date>.md):
- 5-8 câu viết dùng từ/chunk mới
- 1 đoạn ghi âm 2 phút (prompt cụ thể)
- 10 cloze cards (export ra output/anki/<date>_cloze.csv)
- Bài nghe lặp lại 10 câu chứa chunk yếu
Update weak_words.csv: tăng times_missed cho từ tôi sai.
```

---

## 4. PIPELINE.md (router)

```markdown
# PIPELINE — German Brain

Đầu chat user gõ 1 trong các lệnh, AI pick role tương ứng:

| Lệnh | Vai | File log |
|---|---|---|
| "đóng vai tutor" / "luyện đàm thoại" | Tutor | docs/ai/SESSIONS_LOG.md |
| "bóc từ batch" / "extract vocab" | Vocab Extractor | output/new_vocab/ |
| "tạo speaking drill" | Speaking Coach | output/drills/ |
| "tạo listening drill" | Listening Coach | output/drills/ |
| "audit lỗi" / "đóng vai mistake auditor" | Mistake Auditor | docs/ai/MISTAKES_LOG.md |
| "tạo giáo án" / "lesson plan" | Lesson Planner | tutor/lesson_plans/ |
| "tạo homework" | Homework Generator | tutor/homework/ |

Default (không lệnh rõ): hỏi user vai nào, không guess.

Boot order: CLAUDE.md → PIPELINE.md → GLOSSARY/DECISIONS/LESSONS/FAILURES.md → role-specific files.
```

---

## 5. Notion mirror (giữ, không bỏ)

Notion hub Đức hiện đang là source of truth cho user. Sau Phase 1:

- **Source of truth chuyển về local repo.**
- **Notion thành mirror đọc** với Phase 2 auto sync 1-chiều (CSV → Notion DB).
- 4 databases Notion (Vocabulary, Grammar, Sessions, Mistakes) tiếp tục dùng để filter/share với gia sư trên mobile.

Migration:
1. Export Notion databases → CSV.
2. Map column Notion → schema repo.
3. Import vào `data/vocab_master.csv` (dedupe theo wort + bedeutung).
4. Phase 2: viết script `scripts/sync_to_notion.py` chạy mỗi tối → đẩy CSV → Notion (CSV win conflict).

---

## 6. LingQ integration (BRAINSTORM Chat 11)

LingQ rất mạnh exposure nhưng yếu chunk learning. Kế hoạch:

### Phase 1 (manual)

- Status LingQ (1-4) map sang `status` column vocab_master.
- Export LingQ vocabulary CSV → import vào `data/lingq_import_<date>.csv` → dedupe vào master.

### Phase 2 (auto)

- LingQ API: `scripts/lingq_sync.py`
  - Pull known words → update status master.
  - Pull lessons → archive `input/lingq_lessons/`.
- AI tạo lesson LingQ từ batch input local → upload via API:
  - OCR / transcribe input → split lesson 150-400 từ → upload.

---

## 7. Tutor Learning Manager (BRAINSTORM Chat 12)

Pipeline full per buổi học:

```
T-1 ngày: user mở Cowork → "đóng vai Lesson Planner cho topic X"
        → AI tạo tutor/lesson_plans/2026-05-XX.md
        → user share lesson plan với gia sư

T-0: buổi học
        → user/gia sư ghi nhanh vào input/tutor_notes/2026-05-XX.md
        (có thể chụp ảnh nếu giấy → input/images/, OCR sau)

T+0: cuối buổi: "đóng vai Mistake Auditor + Vocab Extractor + Homework Generator"
        → AI:
          1. extract vocab/chunks → output/new_vocab/<date>.csv
          2. audit mistakes → MISTAKES_LOG.md
          3. generate homework → tutor/homework/<date>.md
          4. update weak_words.csv

T+1 ngày: user làm homework + drill → Cowork track completion vào tutor/progress_reports/<week>.md
```

---

## 8. Quick wins Đức tuần này

### Tối A — Seed skeleton (30 phút)

1. Tạo folder structure (theo §1 — list "còn thiếu").
2. Tạo file `docs/ai/PIPELINE.md` (template §4).
3. Tạo `docs/ai/ROLE_PROMPTS.md` với 7 vai (§3).
4. Tạo `docs/ai/GLOSSARY.md` rỗng + 5 entry sample (DTZ, Redemittel, A1/A2/B1, Lerndatum, LingQ).

### Tối B — Schema migration (1h)

1. Backup `data/03_unified/vocab_master.csv` hiện tại.
2. Migrate sang schema v2 (+ status, last_seen, audio_note).
3. Tạo `chunks_master.csv`, `weak_words.csv`, `sources_master.csv` rỗng (chỉ header).
4. Manually import 10-20 chunks từ Notion (Redemittel mà tôi đang yếu).
5. Ghi DECISIONS.md.

### Tối C — End-to-end 1 buổi học (2h)

1. Mở Cowork → "đóng vai Lesson Planner cho topic Familie".
2. AI tạo lesson plan → review.
3. (Mô phỏng) Buổi học → ghi 5-10 từ/chunk + 2 lỗi vào `input/tutor_notes/<today>.md`.
4. "đóng vai Vocab Extractor + Mistake Auditor + Homework Generator".
5. Verify pipeline đầy đủ: vocab mới có, mistake logged, homework generated, weak_words update.

### Tuần sau

Lặp pipeline 3-4 buổi học thật. Đánh giá:
- Có dùng được hay quá phức tạp?
- Vai nào không dùng → cut.
- Schema có chỗ thiếu / thừa?
- Notion mirror auto-sync có cần Phase 2 sớm không?

---

## 9. Connection với các domain khác

Đức là pilot — pattern thành công ở đây copy nguyên qua Dev/SEO/SOP:

| Pattern Đức | Áp dụng cho |
|---|---|
| 7 role prompts paste-ready | Dev: 4 vai PIPELINE / SEO: 5 vai / SOP: 5 vai |
| Master CSV + schema_version | Dev: schema_definitions.php / SEO: keyword_master / SOP: customer_master |
| Input → Queue → Memory → Output → Archive | All 4 domains |
| Tutor Manager (planner → log → homework) | Dev: Tiếp nhận → Triển khai → Audit / SOP: Onboard → Run → Audit |
| LingQ sync 1-chiều | SEO: GSC sync / SOP: Mail/Calendar sync |
| Notion mirror optional | SOP (cần share) / Dev (không cần) / SEO (optional) |

---

## 10. Đánh giá thành công Phase 1 (Đức)

Sau 4 tuần:

- [ ] ≥ 8 buổi học có lesson plan + homework auto.
- [ ] `chunks_master.csv` ≥ 100 rows status 1-2 + ≥ 30 rows status 3-4.
- [ ] `weak_words.csv` ≥ 30 rows với times_missed > 1 (proof pipeline hoạt động).
- [ ] User cảm thấy: "ơ từ này mình nhớ" tăng đáng kể (subjective).
- [ ] Mistake patterns lặp giảm rõ ở 2 nhóm (vd recognition speed, Akkusativ).

→ Nếu đạt: scale pattern sang Dev/SEO/SOP. Nếu không: tinh chỉnh role prompts + schema trước khi nhân rộng.

---

**Cập nhật file này khi:** đổi schema master, thêm/bớt vai, đạt mốc Phase 1.
