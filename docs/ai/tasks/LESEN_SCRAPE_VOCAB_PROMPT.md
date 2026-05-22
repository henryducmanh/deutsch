# Task: deutsch-vorbereitung Lesen — Scrape + Vocab Extract (Phase A+B)

> **Handoff Claude Code** — đọc file này và làm theo từ A đến Z.

---

## 1. End-user

Solo học viên DTZ B1. Mục tiêu: tải toàn bộ 192 bài Lesen (5 Teil) về máy local, lưu text đọc + câu hỏi, sau đó bóc vocab vào vocab_master.csv và push LingQ.

---

## 2. Màn cuối cùng (Definition of Done)

**Phase A — Scrape:**
```
input/html/deutsch-vorbereitung/lesen/<bai>/
  <bai>_text.md        ← nội dung văn bản đọc (German text)
  <bai>_questions.md   ← câu hỏi + đáp án đúng + giải thích
```
Ví dụ: `lesen/1.1/1.1_text.md` + `lesen/1.1/1.1_questions.md`

**Phase B — Vocab:**
- Vocab mới append vào `data/03_unified/vocab_master.csv`
- Chunks append vào `data/chunks_master.csv`
- Files đánh dấu trong `data/processed_files.csv`
- LingQ push sau khi extraction xong

---

## 3. Dữ liệu thật

**Input CSV:** `input/html/deutsch-vorbereitung/lesen_lessons.csv`
```
stt,bai,chu_de,url,teil,teil_desc
1,1.1,Center Solaris – Übersicht der Etagen,https://deutsch-vorbereitung.com/en/uebung-13345.html,1,Teil 1 – Wegweiser/Übersichten
1,2.1,Kurse für Senioren,https://deutsch-vorbereitung.com/en/uebung-13374.html,2,Teil 2 – Anzeigen
...
```
Tổng 192 bài, Teil 1-5.

**5 loại bài (Teil) — cần hiểu để bóc vocab đúng:**

| Teil | Loại | Mô tả | Vocab rich nhất |
|---|---|---|---|
| 1 | Wegweiser / Übersichten | Biển chỉ đường, sơ đồ tầng, bảng giờ | Substantiv địa điểm/dịch vụ |
| 2 | Anzeigen | Quảng cáo việc làm, rao vặt | Verb nghề nghiệp, Substantiv |
| 3 | Kurze Texte | Email, thư thông báo ngắn (~100-200 từ) | Redemittel thư tín, Verb formal |
| 4 | Längere Texte | Hướng dẫn sử dụng, điều khoản (~300-500 từ) | Verb modal, Substantiv kỹ thuật |
| 5 | Formelle Briefe | Thư khiếu nại, đơn từ, thư hủy hợp đồng | Redemittel B1 formal |

**HTTP behaviour đã verify:**
- URL `/en/uebung-XXXX.html` → redirect 301 → `/uebung-XXXX.html` (`allow_redirects=True`)
- **GET tĩnh:** KHÔNG có audio. Reading text nằm trong `div.box_border.back__width` — có ngay trong GET
- **POST `submit=1`:** nhận câu hỏi + đáp án màu green/red + Erklärung (modal)
- Cloudflare: cần `User-Agent` header, sleep 1.5-2s/bài

**HTML structure đã verify (sample bài 1.1):**
```html
<!-- Reading text — lấy từ GET -->
<div class="box_border back__width">
  Center Solaris – Übersicht der Etagen
  4. Etage – Technik & Freizeit
  Restaurant & Café: Mittagessen, Kaffee & Kuchen...
  ...
</div>

<!-- Câu hỏi — lấy từ GET -->
<p class="label text p2_semibold">
  Ihre Mutter feiert bald Geburtstag...
</p>
<span class="input__box border__box">
  <input ... aria-label="a) Erdgeschoss"><span>a) Erdgeschoss</span>
</span>

<!-- Giải thích đáp án — lấy từ POST submit=1 -->
<div class="inputmodal__content">
  <p class="text p2">1 – Richtige Antwort: b (Erdgeschoss)
  Sie suchen für Ihre Mutter eine Gesichtscreme...</p>
</div>
```

**Parsing tips:**
```python
soup = BeautifulSoup(html, 'lxml')

# Reading text (GET):
text_box = soup.select_one('div.box_border.back__width') or soup.select_one('div.box_border')
reading_text = text_box.get_text(separator='\n', strip=True) if text_box else ""

# Câu hỏi (GET):
questions = soup.select('p.label.text')  # hoặc class p2_semibold

# Tất cả options (GET):
options = soup.select('span.input__box')

# Giải thích sau POST:
explanations = soup.select('div.inputmodal__content p.text.p2')

# Đáp án đúng sau POST (class green):
correct = soup.select('span.input__box.border__box.green')
```

---

## 4. Acceptance Tests

1. **Test 5 bài (1 bài/Teil):** `python lesen_scraper.py --test --csv lesen_lessons.csv`
   - Chạy bài 1.1, 2.1, 3.1, 4.1, 5.1 (1 bài đại diện mỗi Teil)
   - Mỗi bài: `_text.md` có ≥ 50 từ tiếng Đức thực, `_questions.md` có ≥ 3 câu hỏi với đáp án
   - `_text.md` KHÔNG chứa HTML tags, navigation, footer

2. **Resume:** chạy lại `--test` → skip bài đã có folder

3. **Full run:** `python lesen_scraper.py --all --csv lesen_lessons.csv`
   - Log `lesen_scrape.log` (filepath, status, timestamp)
   - Bài lỗi HTTP → `_error.txt`, continue

4. **Vocab extraction:** sau scrape, chạy vocab extract cho toàn bộ `_text.md` + `_questions.md`
   - Teil 3+5 (formal texts) đặc biệt giàu Redemittel → ưu tiên bóc chunks
   - `tail -20 data/03_unified/vocab_master.csv` → đúng 13 cột, source_type=lesen

5. **LingQ dry-run:** `php module/lingq_sync/push.php --dry-run` → không lỗi schema

---

## 5. Quy trình Phase A (Scrape)

### Script: `module/scan_extract/lesen_scraper.py`

Tái sử dụng pattern từ `horen_scraper.py` (đã có), điều chỉnh:
1. **Không cần download audio** (Lesen không có audio)
2. **2 request/bài** thay vì 3:
   - GET → reading text + câu hỏi
   - POST `submit=1` → đáp án + giải thích
3. **Output 2 file/bài** (không có `_transcript.md`):
   - `_text.md` — reading text (với YAML frontmatter: bai, teil, chu_de, url)
   - `_questions.md` — câu hỏi + đáp án + giải thích
4. **Sleep 1.5s/bài** (2 request = ~3s actual với network)

### Output format `_text.md`:
```markdown
---
bai: 1.1
teil: 1
teil_desc: Teil 1 – Wegweiser/Übersichten
chu_de: Center Solaris – Übersicht der Etagen
url: https://deutsch-vorbereitung.com/uebung-13345.html
extracted_at: 2026-05-22
---

# Center Solaris – Übersicht der Etagen

4. Etage – Technik & Freizeit
Restaurant & Café: Mittagessen, Kaffee & Kuchen mit Blick auf die Stadt
Elektronik: Fernseher, Computer, Laptops, Tablets
...
```

### Output format `_questions.md`:
```markdown
# Aufgabe 1.1 — Center Solaris – Übersicht der Etagen

## Frage 1
Ihre Mutter feiert bald Geburtstag. Sie hat sich eine neue Hautpflege gewünscht...

- a) Untergeschoss
- b) Erdgeschoss **(richtig)**
- c) 1. Etage

**Erklärung:** Sie suchen für Ihre Mutter eine Gesichtscreme...
```

---

## 6. Quy trình Phase B (Vocab Extract)

Sau khi Phase A xong (hoặc ngay khi mỗi batch scrape xong):

**Nguồn vocab theo Teil:**
- Teil 1 (Wegweiser): bóc danh từ địa điểm, dịch vụ, vị trí (Erdgeschoss, Kundenservice...)
- Teil 2 (Anzeigen): bóc danh từ nghề nghiệp, động từ tuyển dụng (bewerben, einstellen...)
- Teil 3 (Kurze Texte): ưu tiên Redemittel thư tín (hiermit, im Auftrag, in Kürze...)
- Teil 4 (Längere Texte): Verb modal phức tạp, Substantiv kỹ thuật/luật
- Teil 5 (Formelle Briefe): **rất giàu Redemittel** — bóc ưu tiên chunks_master

**Schema và quy trình:** giống HOREN_VOCAB_EXTRACT_PROMPT.md
- ID: `python module/scan_extract/scan_extract.py --next-id vocab`
- `source_type`: `lesen`
- `quelle`: bai number (vd "3.15")
- `thema`: suy từ chu_de (Arbeit/Wohnen/Gesundheit/Behörde/Schule/Freizeit/Reisen...)

---

## 7. Performance / Scale

- 192 bài × 2 request = 384 request
- Sleep 1.5s/bài → ~5 phút wall time (Phase A)
- Không có audio → disk space nhỏ (~5MB tổng)
- Session reuse + retry exponential backoff (429/503)

---

## 8. Format report in cuối

```
=== LESEN SCRAPE + VOCAB — Phase A+B ===
CSV: lesen_lessons.csv (192 bài, 5 Teil)

Phase A — Scrape:
  Processed: N bài
  ok/skip/error: N/N/N
  Log: input/html/deutsch-vorbereitung/lesen_scrape.log

Phase B — Vocab:
  Vocab mới: N từ (lesen)
    Teil 1-5 breakdown: N/N/N/N/N
  Chunks mới: N (đặc biệt Teil 3+5 formelle Texte)
  Dedupe skip: N
  LingQ push: N CREATE + N UPDATE / skip

Top 5 từ B1 đáng học nhất:
  1. <Wort> (<Wortart>) — <bedeutung>
  ...

Locks cleaned: ok
```

---

## Ghi chú

**Lock:** `.ai-locks/lesen_scrape_vocab_A.lock`

**Dependencies:** `pip install requests beautifulsoup4 lxml tqdm --break-system-packages`

**Script:** `module/scan_extract/lesen_scraper.py` (clone từ horen_scraper.py, bỏ audio download)

**Paths:**
- Root Windows: `C:\twv_share\app\deutsch\`
- Root bash: `/sessions/trusting-keen-fermat/mnt/deutsch/`
- CSV input: `input/html/deutsch-vorbereitung/lesen_lessons.csv`
- Output: `input/html/deutsch-vorbereitung/lesen/<bai>/`
