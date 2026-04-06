# Quy tắc trích xuất và chuẩn hóa

## Mục tiêu
Giữ dữ liệu nhất quán để ChatGPT, Cursor và người học dễ tìm kiếm, đối chiếu, cập nhật.

## 1. Từ vựng
### Cấu trúc lưu
Với `data/vocab_master.csv`, chỉ lưu 2 cột:
- `Từ tiếng Đức`
- `Nghĩa tiếng Việt`

### Quy tắc bắt buộc
- Mỗi dòng là **một cặp nghĩa theo ngữ cảnh**
- Kiểm tra trùng theo cặp:
  - `Từ tiếng Đức`
  - `Nghĩa tiếng Việt`
- Nếu một từ tiếng Đức có 2 nghĩa khác nhau, lưu thành 2 dòng
- Nghĩa tiếng Việt phải bám sát ngữ cảnh từ nguồn:
  - file HTML
  - đoạn text
  - transcript
  - PDF
  - hình ảnh

### Ví dụ
```csv
Từ tiếng Đức,Nghĩa tiếng Việt
übersehen,bỏ sót
übersehen,không nhận ra
```

## 2. Cách hiểu “trùng”
Hai dòng chỉ được xem là trùng khi:
- `Từ tiếng Đức` giống nhau sau khi chuẩn hóa
- `Nghĩa tiếng Việt` cũng giống nhau sau khi chuẩn hóa

### Chuẩn hóa khi so sánh
- bỏ khác biệt hoa / thường
- bỏ khoảng trắng thừa ở đầu/cuối
- gộp nhiều khoảng trắng liên tiếp thành một khoảng trắng

### Không xem là trùng nếu
- cùng từ tiếng Đức nhưng nghĩa tiếng Việt khác
- nghĩa cũ quá rộng, còn nghĩa mới sát ngữ cảnh hơn
- cùng lemma nhưng nguồn hiện tại dùng theo một sắc thái nghĩa khác

## 3. Cách viết nghĩa tiếng Việt
- Ưu tiên nghĩa ngắn, rõ, đúng ngữ cảnh
- Không nhồi nhiều nghĩa vào một ô bằng dấu `;` nếu đó là các nghĩa tách biệt
- Chỉ ghi một nghĩa chính cho một ngữ cảnh cụ thể
- Nếu nguồn thực sự thể hiện 2 nghĩa khác nhau, tách thành 2 dòng

## 4. Khi nào thêm mới
Thêm mới khi:
- cặp `Từ tiếng Đức : Nghĩa tiếng Việt` chưa có trong `vocab_master.csv`
- nguồn hiện tại cung cấp một nghĩa mới theo ngữ cảnh khác
- nghĩa cũ chưa phản ánh đúng cách dùng trong nguồn

## 5. Khi nào không thêm mới
Không thêm mới nếu:
- cặp Đức : Việt đã có rồi
- chỉ khác chữ hoa/thường
- chỉ khác khoảng trắng thừa
- chỉ là cách diễn đạt lại nhưng vẫn cùng một nghĩa trong cùng ngữ cảnh và bạn quyết định giữ bản cũ

## 6. Ngữ pháp
Đưa vào `grammar_master.csv` nếu là:
- một cấu trúc ngữ pháp
- một quy tắc biến đổi
- một điểm dễ nhầm
- một mẫu câu gắn chặt với ngữ pháp

## 7. Mẫu câu / Redemittel
Đưa vào `phrase_master.csv` nếu là:
- câu khung
- Redemittel trong Schreiben / Sprechen
- cách diễn đạt lịch sự
- mẫu thi DTZ

## 8. Lỗi thường gặp
Đưa vào `mistake_master.csv` nếu là:
- lỗi bạn thường lặp lại
- cặp sai/đúng đáng ghi nhớ
- lỗi giới từ, cách, giống, trật tự từ

## 9. Tag đề xuất
Tag có thể dùng:
- `DTZ`
- `A2`, `B1`, `B2`
- `Wohnen`, `Arbeit`, `Behörde`, `Gesundheit`, `Alltag`
- `Schreiben`, `Sprechen`, `Hören`, `Lesen`

## 10. Nguồn
Khi trích xuất, luôn giữ ý thức rằng nghĩa phải đến từ ngữ cảnh nguồn.
Ví dụ tên nguồn có thể là:
- `dtz_html_2026_04_05_01`
- `transcript_modul_03`
- `manual_entry`
- `chatgpt_extract_2026_04_05`
