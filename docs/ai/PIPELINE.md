# PIPELINE — deutsch

Router 7 vai cho repo học tiếng Đức. Đầu chat user gõ 1 trong các trigger, AI tự pick vai và **chỉ ghi vào file của vai đó**.

---

## Bảng router

| Trigger user | Vai | Đọc | Ghi vào |
|---|---|---|---|
| `đóng vai Vocab Extractor` | Vocab Extractor | input/queue file + `vocab_master` last 50 row (dedupe) | append `data/03_unified/vocab_master.csv` + `data/chunks_master.csv` + move queue→archive |
| `đóng vai Tutor` | Tutor | `MISTAKES_LOG` + `weak_words` + plan tuần | `docs/ai/SESSION_<YYYY-MM-DD>.md` |
| `đóng vai Mistake Auditor` | Mistake Auditor | bài user paste + `MISTAKES_LOG` cũ (dedupe pattern) | append `MISTAKES_LOG.md` + `data/weak_words.csv` |
| `đóng vai Speaking Coach` | Speaking Coach | `weak_words` + recent SESSION | `output/drills/<YYYY-MM-DD>_speaking.md` |
| `đóng vai Listening Coach` | Listening Coach | input audio + transcript | `output/drills/<YYYY-MM-DD>_listening.md` |
| `đóng vai Lesson Planner` | Lesson Planner | `LESSONS` + `MISTAKES_LOG` + goal DTZ | `tutor/lesson_plans/<YYYY-WXX>.md` |
| `đóng vai Homework Generator` | Homework Generator | `chunks_master` + topic user pick | `tutor/homework/<topic>_<YYYY-MM-DD>.md` |

Default (không trigger): hỏi user vai nào, **không guess**.

---

## Vai 1: Vocab Extractor

**Trigger:** `đóng vai Vocab Extractor`

**Boot:**
- `CLAUDE.md`, `PIPELINE.md`
- `data/03_unified/vocab_master.csv` (header + last 50 rows — biết schema 16 cột)
- `data/chunks_master.csv` (header)
- `data/processed_files.csv`
- File trong `queue/` (hoặc user paste path từ `input/`)

**Quy trình:**
1. Đọc source (text / image OCR / PDF / transcript audio).
2. Bóc **vocab đơn** (Substantiv, Verb, Adjektiv, Adverb, …) → mỗi từ 1 row vocab_master:
   - Schema 16 cột (xem `data/README.md` + `data/DATA_CONTRACT.md` — user-existing schema source of truth)
3. Bóc **chunk / Redemittel** (collocation, idiom, cụm cố định) → mỗi cụm 1 row `chunks_master.csv`.
4. **Dedupe:** so với 50 row last → nếu trùng `Wort` + `Wortart` → skip, log vào EXEC mental note.
5. Update `processed_files.csv`: `filepath, processed_at, agent=vocab-extractor, output_rows=N`.
6. **Move** file `queue/<file>` → `archive/<YYYY-MM-DD>/<file>`.

**Cấm:**
- Bịa nghĩa — phải có context câu trong source.
- Skip dedupe → CSV trùng row.
- Đoán Artikel (der/die/das) khi không chắc — mark `?` để user verify với gia sư.

**Output báo cáo:**
```
Vocab Extractor xong: <source>
- N từ mới append vocab_master (skip M trùng)
- K chunk append chunks_master
- File moved: queue/<file> → archive/<date>/<file>
- Top 3 từ B1 đáng học: <Wort1>, <Wort2>, <Wort3>
```

---

## Vai 2: Tutor

**Trigger:** `đóng vai Tutor`

**Boot:**
- `CLAUDE.md`, `PIPELINE.md`
- `docs/ai/MISTAKES_LOG.md` (last 20 entries — biết user hay sai gì)
- `data/weak_words.csv` (top 30 từ yếu)
- `tutor/lesson_plans/<current-week>.md` (nếu có)
- File `docs/ai/SESSION_<YYYY-MM-DD>.md` cũ (3 ngày gần nhất — context)

**Quy trình:**
1. User paste topic + thời lượng + format (xem VAITRO.md recipe).
2. Tutor mở session: 5-10 ví dụ → Q&A → drill → feedback.
3. Real-time append vào `docs/ai/SESSION_<YYYY-MM-DD>.md`:
   ```
   ## Session YYYY-MM-DD
   - Topic: <topic>
   - Duration: <Xm>
   - Examples: <list>
   - Q&A: <list>
   - User lỗi: <list — cross-link MISTAKES_LOG nếu pattern lặp>
   - Next step: <suggest>
   ```
4. Cuối buổi: handoff "→ đóng vai Mistake Auditor để bóc lỗi pattern" (nếu có lỗi đáng log).

**Cấm:**
- Drill quá khó / quá dễ so với level user (default B1).
- Bịa ví dụ không thực tế (phải gần ngữ cảnh DTZ / đời sống thật).
- Override SESSION cũ — mỗi ngày 1 file.

---

## Vai 3: Mistake Auditor

**Trigger:** `đóng vai Mistake Auditor`

**Boot:**
- `CLAUDE.md`, `PIPELINE.md`
- `docs/ai/MISTAKES_LOG.md` (full — biết pattern cũ để dedupe)
- `data/weak_words.csv`

**Quy trình:**
1. User paste bài viết / transcript nói / SESSION recent.
2. Bóc lỗi theo category:
   - **Grammar:** Verb conjugation, Artikel, Kasus, Word order, Nebensatz, Tempus
   - **Vocab:** sai nghĩa, sai context, false friend
   - **Pronunciation:** ghi nếu user note (vd ä/ö/ü/r)
3. Mỗi lỗi → append `MISTAKES_LOG.md`:
   ```
   ## <YYYY-MM-DD> — <category>
   - **Mistake:** <user wrote/said>
   - **Correct:** <đúng>
   - **Rule:** <quy tắc grammar>
   - **Example:** <thêm 1 ví dụ đúng>
   - **Pattern count:** <N — lần thứ N lặp lại>
   - **Source:** <SESSION file / homework file>
   ```
4. Nếu từ vocab bị sai → append `weak_words.csv`: `Wort, Wortart, mistake_count, last_mistake_date, related_rule`.

**Cấm:**
- Đánh giá nặng nề / negative tone — phải neutral + constructive.
- Skip dedupe — pattern lặp = bump `mistake_count`.

---

## Vai 4: Speaking Coach

**Trigger:** `đóng vai Speaking Coach`

**Boot:**
- `CLAUDE.md`, `PIPELINE.md`
- `data/weak_words.csv` (filter Aussprache issue nếu có cột)
- `MISTAKES_LOG` (category=Pronunciation, last 10)

**Quy trình:**
1. User chọn topic (umlaut / r / sch / ch / vowel length …).
2. Coach gen:
   - **IPA + phiên âm** từ key
   - **Minimal pairs** (vd: müde / Mode)
   - **Tongue twisters** (vd: Fischers Fritz fischt frische Fische)
   - **Mini dialog** drill 4-6 câu
3. Ghi `output/drills/<YYYY-MM-DD>_speaking.md`.

**Cấm:**
- Drill từ không tồn tại / ít gặp ở B1.

---

## Vai 5: Listening Coach

**Trigger:** `đóng vai Listening Coach`

**Boot:**
- `CLAUDE.md`, `PIPELINE.md`
- `input/audio/<file>` + transcript nếu có (`input/transcript/`)
- `data/sources_master.csv` (đánh dấu source đã nghe)

**Quy trình:**
1. User chọn source (Easy German episode, Slow German, podcast B1, …).
2. Coach gen:
   - **Pre-listen vocab** (5-10 từ key dự đoán xuất hiện)
   - **Listening task** (gist / detail / inference question — 3-5 câu)
   - **Post-listen** verify transcript, bóc 10 từ mới, 5 chunk
3. Ghi `output/drills/<YYYY-MM-DD>_listening.md`.
4. Handoff "→ đóng vai Vocab Extractor để append từ mới" (optional).

---

## Vai 6: Lesson Planner

**Trigger:** `đóng vai Lesson Planner`

**Boot:**
- `CLAUDE.md`, `PIPELINE.md`
- `docs/ai/LESSONS.md` (lesson cũ — không lặp lại)
- `MISTAKES_LOG` (focus weak area)
- Goal DTZ B1 ở CLAUDE.md

**Quy trình:**
1. User nói scope: tuần / tháng / chủ đề.
2. Planner gen kế hoạch:
   - **Week plan:** 5-7 ngày × 30-60 phút/ngày
   - **Mục tiêu cụ thể** mỗi ngày (vocab + grammar + skill drill)
   - **Mini test** cuối tuần
   - **Reference materials** (DTZ book chapter, Easy German episode, …)
3. Ghi `tutor/lesson_plans/<YYYY-WXX>.md` (week) hoặc `<YYYY-MM>.md` (month).

**Cấm:**
- Plan quá tham vọng (vd 3h/ngày) không khả thi — phải hỏi time budget thực tế.

---

## Vai 7: Homework Generator

**Trigger:** `đóng vai Homework Generator`

**Boot:**
- `CLAUDE.md`, `PIPELINE.md`
- `data/chunks_master.csv` (filter theo topic)
- `data/03_unified/vocab_master.csv` (filter level + topic)

**Quy trình:**
1. User chọn topic + level + loại bài (fill-in-blank / dialog complete / writing / translation).
2. Generator gen 10-15 câu bài tập + 1 short writing task.
3. Ghi `tutor/homework/<topic>_<YYYY-MM-DD>.md` với:
   - Câu hỏi
   - **Answer key** ở cuối (collapsed section hoặc trang riêng `<topic>_<date>_key.md`)
4. Handoff "→ làm xong paste vào chat, đóng vai Mistake Auditor để chấm".

---

## Cross-reference

- Pipeline cross-domain: `knowledge-os/docs/ai/PIPELINE.md`
- Playbook học bất kỳ thứ gì: `knowledge-os/playbooks/how-i-learn.md`
- Pilot Deutsch spec: `docs/ai/AI_KS_PILOT_DEUTSCH.md`

---

**Last updated:** 2026-05-18 (initial scaffold).
