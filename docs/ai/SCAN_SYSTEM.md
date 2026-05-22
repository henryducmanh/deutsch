# SCAN_SYSTEM — Hệ thống quét HTML tự động

> Tự động extract lesson + vocab từ file HTML các bộ sách B1 DTZ mỗi 30 phút.

---

## Cách dùng: 3 bước

### 1. Đặt file HTML vào đúng thư mục

```
input/html/<tên-bộ-sách>/<kỹ-năng>/<mã-bài>.html
```

**Tên bộ sách** (folder con của `input/html/`):
| Folder | Bộ sách |
|---|---|
| `deutsch-vorbereitung` | bo-vorbereitung.de — đang dùng |
| `telc-b1` | *(thêm khi có)* |
| `goethe-b1` | *(thêm khi có)* |
| `...` | Thêm bất kỳ tên nào — script tự nhận diện |

**Kỹ năng** (folder cấp 2):
| Folder | Môn |
|---|---|
| `horen` | Hören (Nghe) |
| `lesen` | Lesen (Đọc) |
| `schreiben` | Schreiben (Viết) |
| `sprechen` | Sprechen (Nói) |

**Mã bài** (tên file, bạn tự đặt — chuẩn hoá để dễ tra cứu):
```
B1-DTZ-H-001.html   → Hören bài 001
B1-DTZ-L-003.html   → Lesen bài 003
B1-DTZ-S-002.html   → Schreiben bài 002
B1-DTZ-SP-001.html  → Sprechen bài 001
```

> **Ví dụ đường dẫn đầy đủ:**
> `input/html/deutsch-vorbereitung/horen/B1-DTZ-H-001.html`

---

### 2. Chờ 30 phút (hoặc trigger thủ công)

Scheduled task `deutsch-html-scan` tự chạy mỗi 30 phút khi app Claude đang mở.

**Trigger thủ công:** Vào sidebar → Scheduled → `deutsch-html-scan` → Run now.

---

### 3. Xem kết quả

| Output | Vị trí |
|---|---|
| Lesson Markdown | `output/lessons/<bộ-sách>/<kỹ-năng>/<mã-bài>.md` |
| Vocab mới | `data/03_unified/vocab_master.csv` (append) |
| Chunk / Redemittel | `data/chunks_master.csv` (append) |
| Log đã xử lý | `data/processed_files.csv` (append) |
| LingQ | Auto-push sau mỗi lần scan có từ mới |

---

## Thêm bộ sách mới

Chỉ cần tạo folder con trong `input/html/`:
```
input/html/<tên-bộ-sách>/{horen,lesen,schreiben,sprechen}/
```
Script tự nhận diện book name từ đường dẫn — không cần config gì thêm.

---

## File đã quét — không xử lý lại

Script kiểm tra `data/processed_files.csv` trước khi xử lý. File đã có trong log → bỏ qua tự động. Nếu muốn xử lý lại 1 file: xoá dòng tương ứng trong `processed_files.csv` rồi trigger lại.

---

## Script plumbing

`module/scan_extract/scan_extract.py` — các lệnh thủ công:

```bash
# Xem file nào chưa xử lý
python3 module/scan_extract/scan_extract.py --scan

# Convert 1 file thủ công (ghi lesson.md + in ra stdout)
python3 module/scan_extract/scan_extract.py --to-md "input/html/deutsch-vorbereitung/horen/B1-DTZ-H-001.html"

# Sinh ID tiếp theo
python3 module/scan_extract/scan_extract.py --next-id vocab
python3 module/scan_extract/scan_extract.py --next-id chunk

# Đánh dấu file đã xử lý thủ công
python3 module/scan_extract/scan_extract.py --mark "input/html/.../file.html" --rows 12 --chunks 3
```

---

## Cấu trúc output/lessons/

```
output/lessons/
  deutsch-vorbereitung/
    horen/
      B1-DTZ-H-001.md    ← lesson đã extract, có YAML frontmatter
      B1-DTZ-H-002.md
    lesen/
    schreiben/
    sprechen/
  telc-b1/               ← tự tạo khi có sách mới
    ...
```

---

**Last updated:** 2026-05-22
**Scheduled task:** `deutsch-html-scan` (every 30 min)
**Script:** `module/scan_extract/scan_extract.py`
