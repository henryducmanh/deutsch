# PIPELINE — deutsch

Router 8 vai cho repo học tiếng Đức. Đầu chat user gõ 1 trong các trigger, AI tự pick vai và **chỉ ghi vào file của vai đó**.

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
| `đóng vai Module Engineer cho <service>` | Module Engineer (v1.1) | playbook `knowledge-os/playbooks/how-i-integrate-external-api.md` + module hiện có (nếu có) | `module/<service>_sync/` (code) + `docs/ai/tasks/<SERVICE>_<PHASE>_PROMPT.md` (spec handoff) + `docs/<SERVICE>_INTEGRATION.md` (deep-dive) |

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

## Vai 8: Module Engineer (v1.1)

**Trigger:**
- `đóng vai Module Engineer cho <service>` — build module mới
- `đóng vai Module Engineer: <issue>` — debug/extend module hiện có

**Boot:**
- `CLAUDE.md`, `PIPELINE.md` (file này)
- `knowledge-os/playbooks/how-i-integrate-external-api.md` (playbook generic, BẮT BUỘC đọc)
- Nếu module đã tồn tại: `module/<service>_sync/README.md` + `docs/<SERVICE>_INTEGRATION.md`
- Nếu có spec sẵn: `docs/ai/tasks/<SERVICE>_<PHASE>_PROMPT.md`

**Mode delegation (vai này KHÔNG edit code lớn trong Cowork):**

Cowork = brain (plan + write spec). Code thực thi handoff Claude Code qua prompt 7-phần.

Cowork ĐƯỢC edit:
- `config.php` (tune sleep/retry/threshold)
- `docs/*.md` (update INTEGRATION + DECISIONS + LESSONS)
- `docs/ai/tasks/*_PROMPT.md` (write spec mới)
- Bash/Python script trong sandbox cho data analysis 1 lần

Cowork KHÔNG edit:
- `.php` files trong `module/*/` (gốc của module — to/phức tạp, có test)
- Mass operation API (POST/PATCH/DELETE) — chỉ dry-run; live apply do user

**Quy trình build module mới:**

1. **Hỏi 5 câu chốt design** (nếu chưa có trong prompt user):
   - Direction of truth: local primary / external primary / bidirectional?
   - Sync frequency: daily / hourly / on-demand?
   - Auth method: API token / OAuth / file path?
   - Schema mapping: field nào của local → field nào external?
   - Idempotency key: field nào dùng để dedupe (term, id, hash)?

2. **Probe API trước khi spec** (nếu chưa quen service):
   - Web search "<service> API <endpoint>" — đọc community wrapper trên GitHub
   - Bash 1-2 curl call để xem response shape thật (không trust doc)
   - Note quirks: plural vs singular field, status mapping, rate limit hint

3. **Write spec file** `docs/ai/tasks/<SERVICE>_<PHASE>_PROMPT.md` theo format 7-phần:
   - End-user
   - Màn cuối cùng (definition of done)
   - Ví dụ dữ liệu thật (request/response sample)
   - Acceptance tests (≥ 5 steps, có test `--limit=1` UI verify trước mass)
   - Cấm đụng
   - Performance / scale (rate limit + retry + backoff)
   - Format report Claude Code in cuối

4. **Đưa câu paste 1 dòng** cho user copy vào Claude Code CLI:
   ```
   Đọc docs/ai/tasks/<SERVICE>_<PHASE>_PROMPT.md và làm. Tạo lock .ai-locks/<service>_<phase>_impl.lock. KHÔNG tự chạy live --apply. Báo "edit xong, chờ review Cursor".
   ```

5. **Đợi user xác nhận Claude Code đã làm xong + chạy test** (single PATCH manual UI verify), rồi guide live apply.

6. **Sau khi module ổn định:**
   - Append `docs/ai/DECISIONS.md` (deutsch) — decision lớn về service đó
   - Append `knowledge-os/data/knowledge_index.csv` — KI quirks discover
   - Update `knowledge-os/playbooks/how-i-integrate-external-api.md` nếu có pattern mới

**Quy trình debug module hiện có:**

1. Đọc log gần nhất: `module/<service>_sync/logs/<latest>.log`
2. Identify pattern: HTTP code distribution, exit code, có throw không
3. Map sang troubleshoot table trong `docs/<SERVICE>_INTEGRATION.md`
4. Đề xuất fix:
   - Config tune (sleep/retry/threshold) → edit `config.php` trực tiếp
   - Code fix → write task prompt `docs/ai/tasks/<SERVICE>_FIX_<NN>_PROMPT.md`, handoff Claude Code
5. Báo user 1 dòng + chờ review Cursor

**Cấm khi vai Module Engineer:**

- Tự edit code `.php` lớn trong Cowork (bottleneck, dễ break test có sẵn)
- Tự chạy `--apply` mass operation — chỉ dry-run, live apply do user
- Bịa endpoint API chưa verify — probe trước
- Skip "test --limit=1 + UI verify" trong acceptance tests
- Mở rộng scope: 1 service = 1 chuỗi phase (C/D/E/F), KHÔNG bundle nhiều service trong 1 task

**Reference:**
- Playbook bắt buộc: `knowledge-os/playbooks/how-i-integrate-external-api.md`
- Case study LingQ: `docs/LINGQ_INTEGRATION.md` + 4 prompt phase `docs/ai/tasks/LINGQ_*_PROMPT.md`
- Cross-domain KI: `knowledge-os/data/knowledge_index.csv` (KI-20260518-001..005)

---

## Cross-reference

- Pipeline cross-domain: `knowledge-os/docs/ai/PIPELINE.md`
- Playbook học bất kỳ thứ gì: `knowledge-os/playbooks/how-i-learn.md`
- Playbook tích hợp API: `knowledge-os/playbooks/how-i-integrate-external-api.md`
- Pilot Deutsch spec: `docs/ai/AI_KS_PILOT_DEUTSCH.md`

---

**Last updated:** 2026-05-18 (v1.1 — thêm vai 8 Module Engineer sau session LingQ integration).
