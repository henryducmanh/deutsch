# Task: deutsch-vorbereitung Hören — Scraper (Phase A)

> **Handoff Claude Code** — đọc file này và làm theo.

---

## 1. End-user

Solo học viên DTZ B1. Mục tiêu: tải toàn bộ bài Hören của trang deutsch-vorbereitung.com về máy local, mỗi bài thành thư mục riêng gồm 3 file (audio + câu hỏi + transcript), để học offline và trích xuất vocab tự động sau này.

---

## 2. Màn cuối cùng (Definition of Done)

Với **mỗi bài** trong danh sách `input/html/deutsch-vorbereitung/horen_lessons.csv`:

```
input/html/deutsch-vorbereitung/horen/<bai>/
  <bai>.mp3              ← audio đã download (hoặc <bai>_audio_url.txt nếu download thất bại)
  <bai>_questions.md     ← câu hỏi + đáp án + giải thích (Markdown)
  <bai>_transcript.md    ← nội dung nghe transcript (Markdown)
```

Ví dụ thực tế:
```
input/html/deutsch-vorbereitung/horen/4.1/
  4.1.mp3
  4.1_questions.md
  4.1_transcript.md
```

Script chạy được với flag `--test` (3 bài đầu) và `--all` (toàn bộ, resume được).

---

## 3. Dữ liệu thật

**Input CSV** (`horen_lessons.csv`):
```
stt,bai,chu_de,url,sheet
1,1.1,ICE 577 nach Köln,https://deutsch-vorbereitung.com/en/uebung-14234.html,Horen
1,4.1,Leben in der Stadt und auf dem Land,https://deutsch-vorbereitung.com/en/uebung-14226.html,Horen
```
Tổng 692 bài.

**HTTP behaviour đã verify:**
- URL `/en/uebung-XXXX.html` redirect 301 → `/uebung-XXXX.html` — dùng `allow_redirects=True`
- GET tĩnh: có audio src + 18 radio inputs (6 câu × 3+ options) + explanation modals
- POST `data={"submit": "1"}` → response HTML thêm transcription (`div.inputmodal-transcription`) + đáp án đúng (class `green`) / sai (class `red`)
- Audio URL: `https://deutsch-vorbereitung.com/audio/<filename>` (lấy từ `<audio src="audio/...">`)
- Cloudflare: cần `User-Agent` header, sleep 1-2s giữa request

**Mẫu HTML cần parse:**

Audio:
```html
<audio src="audio/Leben in der Stadt und auf dem Land-2026-01-26.MP3" controls=""></audio>
```

Câu hỏi (từ GET, 6 câu Aussage):
```html
<p class="label text p2_semibold">Aussage 1</p>
<div class="inputmodal inputmodal-76851">
  <!-- explanation modal: <h3>Aussage 1 → richtig: c) ...</h3> -->
</div>
<span class="input__box border__box red">
  <input ... value="304831" aria-label="Das Leben in der Stadt ..."><span>...</span>
</span>
<span class="input__box border__box green">  <!-- sau POST mới có class green -->
  <input ... value="304833" aria-label="Für Kinder ist das Aufwachsen ...">
</span>
```

Transcript (chỉ xuất hiện sau POST `submit=1`):
```html
<div class="inputmodal inputmodal-transcription">
  <div class="inputmodal__content">
    <p><em>Wir haben nachgefragt...</em></p>
    <p><strong>Aussage 1</strong><br>Meiner Meinung nach...</p>
  </div>
</div>
```

---

## 4. Acceptance Tests

1. **Test mode:** `python scraper.py --test --csv horen_lessons.csv --out input/html/deutsch-vorbereitung/horen`
   - Chạy 3 bài đầu (bai 1.1, 1.2, 1.3)
   - Mỗi bài tạo đúng 3 file (mp3 + questions.md + transcript.md)
   - `questions.md` có ≥ 3 câu Aussage với options và richtig answer
   - `transcript.md` có ≥ 1 đoạn văn tiếng Đức thực sự (không phải placeholder)

2. **Resume:** chạy lại `--test` → script skip bài đã có folder, không download lại

3. **Full run:** `python scraper.py --all --csv horen_lessons.csv --out input/html/deutsch-vorbereitung/horen`
   - Progress bar hoặc log mỗi 10 bài
   - Ghi `horen_scrape.log` (filepath, status: ok/skip/error, timestamp)
   - Bài lỗi HTTP → ghi `_error.txt` trong folder, continue bài tiếp

4. **Audio fallback:** nếu MP3 download lỗi (403/404) → tạo file `<bai>_audio_url.txt` chứa URL gốc, không crash

5. **Rate limit:** sleep 1.5s giữa mỗi bài (GET + POST + audio = 3 request/bài, tổng ~17 phút cho 692 bài)

---

## 5. Cấm đụng

- `vendor/` — không có trong project này
- `data/03_unified/vocab_master.csv` — phase này chỉ lấy raw material, KHÔNG bóc vocab
- Module `lingq_sync/` — chưa liên quan phase này
- Tự `git commit` / `git push`
- Tự chạy `--all` trước khi `--test` pass

---

## 6. Performance / Scale

- 692 bài × 3 request = 2076 request tổng
- Sleep 1.5s/bài → ~17 phút wall time
- Audio trung bình ~2MB → 692 bài ≈ 1.4GB disk space
  - Nếu disk concern: flag `--no-audio` → chỉ download text (tạo `_audio_url.txt`)
- Retry: HTTP error 429/503 → exponential backoff (2s, 4s, 8s), max 3 lần
- Session reuse: dùng `requests.Session()` (keep-alive, tái dùng cookie)
- Cloudflare: nếu bị block (403 liên tục > 5 bài) → dừng, in cảnh báo, không crash silent

---

## 7. Format report Claude Code in cuối

```
=== HOREN SCRAPE — Phase A ===
CSV: input/html/deutsch-vorbereitung/horen_lessons.csv (692 bài)
Mode: --test / --all
---
Processed : N bài
  ok       : N
  skip     : N (đã có)
  error    : N
Audio      : N files downloaded / N skipped (url_only) / N failed
Log        : input/html/deutsch-vorbereitung/horen_scrape.log
---
Bài lỗi: [liệt kê nếu có]
Next step: chạy --all khi --test pass, sau đó "đóng vai Vocab Extractor" để bóc vocab
```

---

## Ghi chú kỹ thuật

**Dependencies:**
```bash
pip install requests beautifulsoup4 lxml tqdm --break-system-packages
```

**File output script:**
```
module/scan_extract/horen_scraper.py
```

**Path root project (Windows):** `C:\twv_share\app\deutsch\`
**Path bash (sandbox):** `/sessions/trusting-keen-fermat/mnt/deutsch/`

**Parsing tips:**
- `soup.select('audio[src]')[0]['src']` → relative audio path
- `soup.select('div.inputmodal:not(.inputmodal-transcription)')` → 6 explanation modals
- `soup.select('span.input__box')` → tất cả radio options
- Sau POST: `soup.select('div.inputmodal-transcription .inputmodal__content')` → transcript
- Đáp án đúng từ GET (không cần POST): trong mỗi `div.inputmodal`, thẻ `h3` có text `richtig: X)`
- Unescape HTML entities: dùng `html.unescape()` hoặc BeautifulSoup tự handle

---

**Tạo lock trước khi bắt đầu:**
```
.ai-locks/horen_scrape_A.lock
```
Xoá lock sau khi xong.
