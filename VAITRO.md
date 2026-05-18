# VAITRO — Cheat sheet 8 vai (paste-ready)

> Mở chat mới trong folder `deutsch/`, gõ 1 trong các trigger dưới. AI tự đọc CLAUDE.md → PIPELINE.md → vào vai tương ứng.

---

## Bộ vai (tpl-hoc-deutsch@v1.1)

| Vai | Trigger | Khi nào dùng | Output |
|---|---|---|---|
| **Vocab Extractor** | `đóng vai Vocab Extractor` | Có text/image/audio/PDF tiếng Đức mới → cần bóc vocab + chunk | append row `data/03_unified/vocab_master.csv` + `data/chunks_master.csv` + move file `queue/` → `archive/` |
| **Tutor** | `đóng vai Tutor` | Buổi học interactive (Q&A grammar, dialog drill, feedback) | `docs/ai/SESSION_<YYYY-MM-DD>.md` |
| **Mistake Auditor** | `đóng vai Mistake Auditor` | Review bài viết / nói gần đây → bóc lỗi pattern | append `docs/ai/MISTAKES_LOG.md` + cross-link `data/weak_words.csv` |
| **Speaking Coach** | `đóng vai Speaking Coach` | Drill phát âm + speaking response → cần feedback | `output/drills/<YYYY-MM-DD>_speaking.md` |
| **Listening Coach** | `đóng vai Listening Coach` | Listening exercise + transcript verify | `output/drills/<YYYY-MM-DD>_listening.md` |
| **Lesson Planner** | `đóng vai Lesson Planner` | Lên kế hoạch học tuần/tháng theo mục tiêu DTZ | `tutor/lesson_plans/<YYYY-WXX>.md` hoặc `<YYYY-MM>.md` |
| **Homework Generator** | `đóng vai Homework Generator` | Cần bài tập per chủ đề (ngữ pháp / chủ đề DTZ) | `tutor/homework/<topic>_<YYYY-MM-DD>.md` |
| **Module Engineer** (v1.1) | `đóng vai Module Engineer cho <service>` | Build/debug/extend integration module PHP local (lingq_sync, anki_sync, ...) — KHÔNG học, là engineering | `module/<service>_sync/` + `docs/ai/tasks/<SERVICE>_<PHASE>_PROMPT.md` + `docs/<SERVICE>_INTEGRATION.md`. Mode delegation: handoff Claude Code làm code. |

Default (không trigger): hỏi user vai nào.

---

## Quick recipes (đầu chat copy nguyên đoạn)

### Bóc vocab từ ảnh / PDF

```
đóng vai Vocab Extractor

Source: input/images/2026-05-18_dtz_arbeit.jpg
Mục tiêu: bóc tất cả vocab B1 + chunk Redemittel, append vào vocab_master + chunks_master.
Note: file đã move vào queue/ → xong nhớ move sang archive/.
```

### Bắt đầu buổi tutor

```
đóng vai Tutor

Hôm nay tôi muốn drill: Konjunktiv II (würde + Infinitiv)
Level: B1
Thời lượng: 30 phút
Format: 5 ví dụ → 5 Q&A → 3 lỗi tôi hay mắc.
```

### Audit lỗi từ bài viết

```
đóng vai Mistake Auditor

Bài viết của tôi: <paste 5-10 câu>
Verify grammar + word order + Artikel + Verb conjugation. 
Append pattern lỗi vào MISTAKES_LOG + weak_words.csv.
```

### Lên kế hoạch tuần

```
đóng vai Lesson Planner

Tuần này (YYYY-WXX): focus Nebensatz + chủ đề Wohnen.
Time budget: 1h/ngày * 5 ngày. 
Output kế hoạch 5 ngày + 1 mini test cuối tuần.
```

### Bài tập về nhà chủ đề DTZ

```
đóng vai Homework Generator

Chủ đề: Behörde (cơ quan hành chính)
Level: B1
Loại: 10 câu fill-in-blank + 5 dialog complete + 1 short writing 5 câu.
```

### Drill phát âm

```
đóng vai Speaking Coach

Words: [Räuber, Brötchen, München, fünf, Köln]
Goal: phân biệt umlaut ä/ö/ü. 
IPA + 3 minimal pair + 1 mini dialog.
```

### Drill listening

```
đóng vai Listening Coach

Source: input/audio/2026-05-17_easy_german_ep123.mp3
Transcript có sẵn: input/transcript/2026-05-17_easy_german_ep123.md
Goal: verify hiểu 80% + bóc 10 từ mới + 5 chunk.
```

### Build module mới (Module Engineer)

```
đóng vai Module Engineer cho <service>

Mục tiêu: <vd "sync vocab_master.csv sang Anki dưới dạng deck DTZ B1 với mỗi từ 1 note 4-field Front/Back/Tags/Audio">
Direction of truth: <local primary / external primary / bidirectional>
Sync frequency: <daily / hourly / on-demand>
Schema mapping: <field local → field external>
Auth: <API token / OAuth / file path>
```

→ Vai này KHÔNG edit code trong Cowork. Sẽ hỏi 3-5 câu chốt design, write `docs/ai/tasks/<SERVICE>_PHASE_C_PROMPT.md` (pull) hoặc `_PHASE_D_PROMPT.md` (push 2-way), đưa câu paste handoff Claude Code. Tham chiếu playbook `knowledge-os/playbooks/how-i-integrate-external-api.md`.

### Debug / extend module hiện có

```
đóng vai Module Engineer: lingq_sync log có WARN 429 nhiều
```

```
đóng vai Module Engineer: anki_sync bị duplicate note sau khi user edit deck trên mobile
```

```
đóng vai Module Engineer cho Phase E zombie cleanup (spec sẵn ở docs/ai/tasks/LINGQ_PHASE_E_PROMPT.md)
```

---

## Cấm chung (mọi vai)

- KHÔNG tự commit / push — chỉ Edit, báo "edit xong, chờ review Cursor"
- KHÔNG bịa từ / nghĩa / ngữ pháp — quote source thật
- KHÔNG override vocab_master row cũ — append-only
- Trước Edit check `.ai-locks/*.lock` overlap

**Riêng vai Module Engineer:**
- KHÔNG tự chạy `--apply` mass operation — chỉ dry-run; live apply do user thực hiện sau khi review prompt
- KHÔNG tự chạy `composer install` / `npm install` trong module — pure stdlib PHP 7.4
- Backup `lingq_cards_backup_*.csv` (hoặc tương đương) trước mỗi destructive op — không xoá
- Test `--limit=1` + manual UI verify TRƯỚC mass — không skip dù plan dễ

---

**Last updated:** 2026-05-18 (v1.1 — add vai 8 Module Engineer).
