# ROLE_PROMPTS — Opener paste-ready per vai

> File này chứa opener chi tiết cho từng vai. Khi muốn vào vai mà chưa rõ format, copy template tương ứng dưới đây, điền placeholder `<...>`, paste vào chat mới.

---

## Vocab Extractor

```
đóng vai Vocab Extractor

Source: <queue/<file> hoặc input/<sub>/<file>>
Loại: <text / image / audio transcript / PDF>
Level filter: <A1-A2 / B1 / B2+ / all>
Topic hint: <vd Arbeit, Wohnen, Gesundheit, Behörde>

Mục tiêu:
- Bóc vocab đơn + chunk Redemittel
- Append vocab_master + chunks_master (dedupe với last 50 row)
- Move file queue → archive sau khi xong

Output báo cáo theo format PIPELINE Vai 1.
```

---

## Tutor

```
đóng vai Tutor

Topic hôm nay: <vd Konjunktiv II, Nebensatz mit weil/dass, Passiv Präsens, ...>
Level: <A2 / B1 / B2>
Thời lượng: <Xm — vd 30 phút>
Format mong muốn:
- <N> ví dụ
- <M> Q&A
- <K> drill (fill-in-blank / dialog complete / translation)
- Feedback lỗi tôi hay mắc (cross-check MISTAKES_LOG)

Special focus (nếu có): <vd "tôi hay nhầm Akkusativ vs Dativ với 'helfen'">

Ghi SESSION_<YYYY-MM-DD>.md.
```

---

## Mistake Auditor

```
đóng vai Mistake Auditor

Bài / transcript user: 
<paste 5-15 câu>

Context: <vd "bài viết về tuần làm việc" / "transcript speaking session với gia sư">
Level target: <B1>

Audit:
- Grammar (conjugation, Artikel, Kasus, word order)
- Vocab (sai context, false friend)
- Pronunciation (chỉ nếu user note)

Append MISTAKES_LOG + cập nhật weak_words.csv (bump count nếu pattern lặp).
```

---

## Speaking Coach

```
đóng vai Speaking Coach

Focus: <vd umlaut ä/ö/ü, r-sound (Räuber/Brötchen), sch/ch distinction, ...>
Level: <B1>
Time: <15-30m drill>

Words/topic seed: <list 5-10 word hoặc 1 chủ đề>

Output:
- IPA + phiên âm
- 3 minimal pair
- 1 tongue twister
- 1 mini dialog 4-6 câu

Ghi output/drills/<YYYY-MM-DD>_speaking.md.
```

---

## Listening Coach

```
đóng vai Listening Coach

Source: <input/audio/<file> hoặc URL podcast>
Transcript (nếu có): <input/transcript/<file>>
Level: <B1>
Duration: <ep length / hoặc đoạn cụ thể MM:SS-MM:SS>

Task:
- Pre-listen: 10 vocab dự đoán
- Comprehension Q&A: 5 câu (gist + detail + inference)
- Post-listen: bóc 10 từ mới + 5 chunk

Ghi output/drills/<YYYY-MM-DD>_listening.md.
```

---

## Lesson Planner

```
đóng vai Lesson Planner

Scope: <week YYYY-WXX hoặc month YYYY-MM>
Time budget: <Xh/ngày × Y ngày>
Goal focus: <vd "Konjunktiv II + chủ đề Wohnen" / "Nebensatz mastery">
Materials có sẵn: <DTZ book chap N, Easy German playlist, podcast list>

Output:
- Plan ngày-by-ngày (vocab + grammar + skill drill)
- Mini test cuối tuần (5-10 câu)
- Reference cụ thể (chap nào, ep nào)

Ghi tutor/lesson_plans/<YYYY-WXX>.md.
```

---

## Homework Generator

```
đóng vai Homework Generator

Topic: <vd Behörde, Arbeitsplatz, Wohnungssuche, Gesundheit>
Level: <B1>
Loại bài (pick 1-3):
- Fill-in-blank: <N câu>
- Dialog complete: <M dialog>
- Translation (VN ↔ DE): <K cặp>
- Short writing: <topic + word count target>

Có/không answer key?: <yes/no>

Ghi tutor/homework/<topic>_<YYYY-MM-DD>.md + key file riêng nếu yes.
```

---

## Module Engineer (v1.1)

### Build module mới — opener đầy đủ (5 câu chốt design)

```
đóng vai Module Engineer cho <service>

Mục tiêu: <1 câu — vd "Sync vocab_master.csv sang Anki dưới dạng deck DTZ B1, mỗi từ 1 note 4-field Front=wort/Back=bedeutung+beispiel/Tags=wortart+level+thema/Audio=optional">

5 câu chốt design:
1. Direction of truth: <local primary / external primary / bidirectional>
   → Recommend local primary nếu external tạo data nhiễu (case LingQ).
2. Sync frequency: <daily / hourly / on-demand>
   → Recommend daily (cron 10:00 cùng task scheduler) trừ khi data realtime.
3. Auth method: <API token / OAuth / file path>
   → Lấy ở đâu (URL paste sẵn)?
4. Schema mapping: <field local → field external>
   → vd: wort→Front, bedeutung→Back, wortart→Tag, beispiel→...
5. Idempotency key: <field nào để dedupe>
   → vd: lingq_id, deck_note_guid, hash(wort+wortart)

Constraints:
- Stack PHP 7.4 local, no MySQL, cron Windows
- KHÔNG tự git commit/push
- Reference playbook: knowledge-os/playbooks/how-i-integrate-external-api.md

Output mong muốn từ Cowork (vai này):
1. Probe API (1-2 curl call sandbox) confirm endpoint + response shape
2. Spec file docs/ai/tasks/<SERVICE>_PHASE_C_PROMPT.md (pull) + _PHASE_D_PROMPT.md (push) theo format 7-phần
3. Câu paste handoff Claude Code

Code thực thi do Claude Code làm sau. Live --apply do user.
```

### Debug / extend module hiện có — opener ngắn

```
đóng vai Module Engineer: <issue ngắn>

Module: <service>
Log gần nhất: module/<service>_sync/logs/<file>
Symptom: <quote 3-5 dòng log có error hoặc behaviour bất thường>
Đã thử: <list những cách user đã chạy nếu có>
```

→ Vai sẽ đọc log + DECISIONS.md + INTEGRATION.md, propose fix (config tune trực tiếp hoặc spec file mới cho Claude Code).

### Phase tiếp theo của module có sẵn (đã có spec)

```
đóng vai Module Engineer cho <PHASE_X>

Spec: docs/ai/tasks/<SERVICE>_PHASE_<X>_PROMPT.md
```

→ Vai sẽ đọc spec, đưa câu paste handoff Claude Code (không cần hỏi gì thêm).

---

## Quy tắc chung (mọi vai)

- KHÔNG commit / push tự động
- Verify file > 300 dòng bằng `wc -l` (Windows mount hay cụt)
- Check `.ai-locks/*.lock` trước Edit
- Quote source (file path / row id) cho mọi claim
- Append-only với master CSV

**Riêng vai Module Engineer:**
- Cowork edit `config.php` + `docs/*.md` OK; KHÔNG tự edit `.php` lớn (handoff Claude Code)
- Mass operation (`--apply` POST/PATCH/DELETE) chỉ dry-run; live do user
- Backup `<service>_*_backup_*.csv` trước destructive op — không xoá

---

**Last updated:** 2026-05-18 (v1.1 — thêm opener Module Engineer).
