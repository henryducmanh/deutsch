# Task: Vocab Extract từ Hören lessons → LingQ (Phase B)

> **Handoff Claude Code** — đọc file này và làm theo từ A đến Z.

---

## 1. End-user

Solo học viên DTZ B1. Đã có 143 folder bài Hören tại `input/html/deutsch-vorbereitung/horen/`.
Mục tiêu: trích xuất từ vựng B1 từ transcript + câu hỏi của từng bài, append vào vocab_master.csv, push lên LingQ để học Flashcard.

---

## 2. Màn cuối cùng (Definition of Done)

- Tất cả file `*_transcript.md` và `*_questions.md` trong `input/html/deutsch-vorbereitung/horen/*/` đã được xử lý
- Vocab mới (chưa có trong vocab_master.csv) được append vào `data/03_unified/vocab_master.csv`
- Chunk/Redemittel được append vào `data/chunks_master.csv`
- Mỗi file nguồn được ghi vào `data/processed_files.csv` (đánh dấu đã xử lý)
- LingQ push chạy sau khi toàn bộ extraction xong

---

## 3. Dữ liệu thật

**Root project:** `C:\twv_share\app\deutsch\`

**Nguồn input:**
```
input/html/deutsch-vorbereitung/horen/1.1/1.1_transcript.md
input/html/deutsch-vorbereitung/horen/1.1/1.1_questions.md
input/html/deutsch-vorbereitung/horen/1.2/1.2_transcript.md
... (143 folder, mỗi folder 2 file)
```

**Mẫu transcript (text tiếng Đức thuần — source chính để bóc vocab):**
```
Sehr geehrte Fahrgäste, auf Gleis 4 fährt der ICE 577 nach Köln ein.
Aufgrund des heutigen Karnevals verkehrt dieser Zug in geänderter Wagenreihenfolge.
Die Wagen der ersten Klasse befinden sich ganz nach vorne.
```

**Mẫu questions (có câu hỏi + đáp án + giải thích):**
```
## Sie haben eine Reservierung in Wagen 25. Wohin müssen Sie?
- b) Nach A bis C. **(richtig)**
**Erklärung:** In der Durchsage wird klar gesagt: „Die Wagen mit den Nummern 22 bis 27..."
```

**Schema vocab_master.csv (13 cột):**
```
id,wort,wortart,formen,bedeutung,beispiel,uebersetzung,thema,lerndatum,level,quelle,source_type,tags,notes
VOC-20260518-001,Entwicklung,Substantiv,"die Entwicklung, -en",sự phát triển,...,Technologie,2026-05-18,1,SRC-001,tutor,B1;DTZ;Technologie,
```

**Schema chunks_master.csv:**
```
# columns: id, chunk_de, chunk_vn, type, topic, level, source, first_seen, last_practiced, frequency, note
CH-20260518-001,"auf dem Land leben","sống ở vùng nông thôn",Wendung,Wohnen,B1,...
```

**Tool sinh ID:** `python module/scan_extract/scan_extract.py --next-id vocab` và `--next-id chunk`

---

## 4. Quy trình thực hiện

### Bước 0 — Setup
```bash
# Tạo lock
echo "horen_vocab_extract_B" > .ai-locks/horen_vocab_B.lock

# Kiểm tra processed_files.csv — biết file nào đã xử lý
python module/scan_extract/scan_extract.py --scan
# (Script này chỉ quét input/html/*.html, dùng làm reference thôi)
```

### Bước 1 — Build danh sách file cần xử lý
Viết/chạy Python script inline để:
```python
import os, csv
from pathlib import Path

ROOT = Path(".")
HOREN_DIR = ROOT / "input/html/deutsch-vorbereitung/horen"
PROC_CSV  = ROOT / "data/processed_files.csv"

# Load đã processed
processed = set()
if PROC_CSV.exists():
    for line in PROC_CSV.read_text().splitlines():
        if line and not line.startswith('#'):
            processed.add(line.split(',')[0].strip())

# Tìm file chưa xử lý
todo = []
for folder in sorted(HOREN_DIR.iterdir()):
    if not folder.is_dir(): continue
    for fname in ['_transcript.md', '_questions.md']:
        f = folder / (folder.name + fname)
        if f.exists():
            rel = str(f.relative_to(ROOT)).replace('\\','/')
            if rel not in processed:
                todo.append((folder.name, fname.strip('_').replace('.md',''), f, rel))

print(f"Cần xử lý: {len(todo)} file")
```

### Bước 2 — Xử lý từng batch (10 folder / lần)
Với mỗi folder (bài học), đọc cả 2 file (transcript + questions), bóc vocab:

**Ưu tiên bóc từ transcript** (text thuần, ngữ cảnh rõ nhất).
**Bổ sung từ questions/erklärung** nếu có từ mới chưa xuất hiện trong transcript.

**Tiêu chí chọn từ vựng cần bóc:**
- Substantiv: danh từ có Artikel (der/die/das) — B1 level, xuất hiện trong ngữ cảnh rõ
- Verb: động từ nguyên mẫu quan trọng B1 (đặc biệt: trennbar, modal context, Konjunktiv)
- Adjektiv / Adverb: tính từ/trạng từ B1 đáng học (không bóc adjektiv quá đơn giản như "gut", "groß")
- Redemittel / Wendung: cụm cố định, collocation hay gặp DTZ (vd: "aufgrund + Gen", "sich befinden in + Dat")
- **KHÔNG bóc:** tên riêng, số, thương hiệu, từ A1 quá đơn giản (der Mann, die Frau, das Kind...)
- **KHÔNG bịa nghĩa** — chỉ ghi `bedeutung` và `uebersetzung` khi context câu đủ rõ, nếu không rõ để trống
- Artikel không chắc → `formen` ghi `?`

**Field mapping:**
- `thema`: suy từ tên bài (vd "ICE 577 nach Köln" → Reisen, "Fitnessstudio" → Gesundheit/Freizeit)
- `quelle`: tên bài (vd "1.1", "4.1")
- `source_type`: `horen`
- `tags`: `B1;DTZ;<thema>`
- `level`: `1` (B1)
- `lerndatum`: ngày hôm nay (YYYY-MM-DD)

### Bước 3 — Append CSV
```python
# Đọc vocab_master.csv để dedupe (chỉ cần cột 'wort' + 'wortart')
existing = set()
with open('data/03_unified/vocab_master.csv') as f:
    for row in csv.DictReader(f):
        existing.add((row['wort'].lower(), row['wortart'].lower()))

# Với mỗi từ mới:
# 1. Sinh ID: python module/scan_extract/scan_extract.py --next-id vocab
# 2. Kiểm tra dedupe
# 3. Append 1 dòng CSV (đúng 13 cột, escape dấu phẩy bằng double-quote)
```

**Quan trọng — append atomic:**
```python
with open('data/03_unified/vocab_master.csv', 'a', newline='', encoding='utf-8') as f:
    writer = csv.writer(f)
    writer.writerow([id, wort, wortart, formen, bedeutung, beispiel,
                     uebersetzung, thema, lerndatum, level, quelle, source_type, tags, notes])
```

### Bước 4 — Đánh dấu processed
Sau khi xử lý xong 1 folder (cả transcript + questions):
```bash
python module/scan_extract/scan_extract.py \
  --mark "input/html/deutsch-vorbereitung/horen/1.1/1.1_transcript.md" \
  --rows N_vocab --chunks K_chunks --note "horen/1.1"

python module/scan_extract/scan_extract.py \
  --mark "input/html/deutsch-vorbereitung/horen/1.1/1.1_questions.md" \
  --rows 0 --chunks 0 --note "horen/1.1 questions"
```

### Bước 5 — LingQ push (sau khi toàn bộ extraction xong)
```bash
# Dry-run trước
php module/lingq_sync/push.php --limit=100 --dry-run

# Nếu ok, chạy thật
php module/lingq_sync/push.php --limit=100
```
Nếu php không có trong PATH → note lại, user tự chạy.

### Bước 6 — Xoá lock
```bash
del .ai-locks\horen_vocab_B.lock
```

---

## 5. Acceptance Tests

1. Sau batch đầu (10 folder): `tail -20 data/03_unified/vocab_master.csv` — có ≥ 20 dòng mới, đúng 13 cột
2. `python module/scan_extract/scan_extract.py --scan` — số file HTML chưa xử lý không tăng (csv mark đúng)
3. Không có dòng trùng `wort+wortart` trong vocab_master.csv mới append
4. `wc -l data/03_unified/vocab_master.csv` — số dòng tăng đúng với số vocab đã bóc
5. LingQ dry-run không lỗi schema

---

## 6. Cấm

- Bịa Artikel (der/die/das) khi không có trong context → dùng `?`
- Override row cũ trong vocab_master.csv — **append-only**
- Skip dedupe — so với existing vocab trước khi append
- Bóc quá nhiều từ vô nghĩa (A1 basic, tên riêng, số)
- Tự git commit / git push
- Tự chạy LingQ live push trước khi dry-run pass

---

## 7. Format report Claude Code in cuối

```
=== HOREN VOCAB EXTRACT — Phase B ===
Folders processed : N / 143
Files processed   : N (transcript) + N (questions)
---
Vocab mới append  : N từ
  Substantiv      : N
  Verb            : N
  Adjektiv/Adv    : N
Chunks mới append : N cụm
Skipped (dedupe)  : N
---
LingQ push        : N từ pushed / skip (php unavailable)
---
Top 5 từ B1 đáng học nhất từ batch này:
  1. <Wort> (<Wortart>) — <bedeutung>
  ...
Lock xoá: ok
Next step: "đóng vai Vocab Extractor" để review batch tiếp theo nếu cần
```
