# Quy tắc trích xuất và chuẩn hóa

## Mục tiêu
Giữ dữ liệu nhất quán để ChatGPT, Cursor và người học dễ tìm kiếm, đối chiếu, cập nhật.

## 1. Từ vựng
### Danh từ
- Lưu kèm mạo từ nếu xác định được
- Ví dụ: `der Termin`, `die Wohnung`, `das Formular`
- Trong CSV:
  - `article`: `der` / `die` / `das`
  - `lemma`: chỉ phần danh từ hoặc có thể giữ cả cụm tùy workflow
  - `normalized`: dạng viết thường không dấu câu

### Động từ
- Luôn lưu ở dạng nguyên mẫu
- Ví dụ: `vermieten`, `übersehen`, `beantragen`

### Tính từ / trạng từ
- Lưu dạng gốc
- Ví dụ: `deutlich`, `wichtig`, `dringend`

### Từ ghép / collocation / cụm ngắn
- Nếu có giá trị học như một đơn vị, lưu vào `vocab_master.csv`
- Ví dụ: `Bescheid geben`, `einen Termin vereinbaren`

### Thành ngữ / cụm cố định dài
- Nếu là mẫu dùng nguyên khối, có thể lưu vào `phrase_master.csv`

## 2. Ngữ pháp
Đưa vào `grammar_master.csv` nếu là:
- một cấu trúc ngữ pháp
- một quy tắc biến đổi
- một điểm dễ nhầm
- một mẫu câu gắn chặt với ngữ pháp

Ví dụ:
- `weil + Nebensatz`
- `Konjunktiv II`
- `Zustandspassiv`
- `Relativsatz`

## 3. Mẫu câu / Redemittel
Đưa vào `phrase_master.csv` nếu là:
- câu khung
- Redemittel trong Schreiben / Sprechen
- cách diễn đạt lịch sự
- mẫu thi DTZ

Ví dụ:
- `Ich möchte mich beschweren, weil ...`
- `Könnten Sie mir bitte sagen, ...`
- `Es kommt darauf an, dass ...`

## 4. Lỗi thường gặp
Đưa vào `mistake_master.csv` nếu là:
- lỗi bạn thường lặp lại
- cặp sai/đúng đáng ghi nhớ
- lỗi giới từ, cách, giống, trật tự từ

## 5. Chuẩn hóa để tránh trùng
- So sánh trùng trên `normalized`
- Bỏ khác biệt hoa / thường
- Bỏ khoảng trắng thừa
- Không thêm mục mới nếu chỉ là biến thể viết hoa
- Nếu cùng lemma nhưng khác từ loại, cho phép lưu riêng

## 6. Khi nào không thêm mới
Không thêm mới nếu:
- mục đã có rồi và nội dung không khác đáng kể
- chỉ là dạng chia của động từ mà lemma đã tồn tại
- chỉ là số nhiều của danh từ đã tồn tại

## 7. Khi nào nên bổ sung thay vì thêm mới
- có ví dụ tốt hơn
- có nghĩa rõ hơn
- có tag tốt hơn
- có ghi chú phân biệt với mục khác

## 8. Tag đề xuất
Tag có thể dùng:
- `DTZ`
- `A2`, `B1`, `B2`
- `Wohnen`, `Arbeit`, `Behörde`, `Gesundheit`, `Alltag`
- `Schreiben`, `Sprechen`, `Hören`, `Lesen`

## 9. Nguồn
Trường `source` nên ghi ngắn gọn nhưng nhận diện được:
- `dtz_html_2026_04_05_01`
- `transcript_modul_03`
- `manual_entry`
- `chatgpt_extract_2026_04_05`
