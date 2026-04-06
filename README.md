# deutsch

Kho tri thức học tiếng Đức cá nhân, lưu trên GitHub để dùng cùng ChatGPT và Cursor.

## Mục tiêu
- Lưu từ vựng, ngữ pháp, mẫu câu, cụm từ, thành ngữ và lỗi thường gặp
- Với từ vựng, master chỉ giữ **2 cột**:
  - `Từ tiếng Đức`
  - `Nghĩa tiếng Việt`
- Dùng ChatGPT để trích xuất từ HTML / transcript / PDF / hình ảnh / đoạn text, rồi đối chiếu với dữ liệu có sẵn
- Dùng GitHub làm nguồn dữ liệu chuẩn để tích lũy lâu dài

## Cấu trúc chính
- `docs/` : mô tả schema, workflow, quy tắc trích xuất, hệ trạng thái
- `data/` : các file CSV dữ liệu trung tâm
- `inbox/` : nơi đặt HTML, transcript và kết quả trích xuất
- `notes/` : ghi chú học theo ngày/tuần/DTZ
- `templates/` : file mẫu để thêm dòng mới hoặc ghi log học

## File dữ liệu quan trọng
- `data/vocab_master.csv` : kho từ vựng trung tâm, chỉ gồm 2 cột
- `data/grammar_master.csv` : kho ngữ pháp trung tâm
- `data/phrase_master.csv` : kho mẫu câu / cụm cố định / Redemittel
- `data/mistake_master.csv` : các lỗi hay sai
- `data/review_queue.csv` : hàng đợi ôn tập
- `data/learning_log.csv` : nhật ký học

## Quy tắc cốt lõi cho từ vựng
- Mỗi dòng trong `vocab_master.csv` là **một cặp nghĩa theo ngữ cảnh**
- Khóa kiểm tra trùng là cặp:
  - `Từ tiếng Đức`
  - `Nghĩa tiếng Việt`
- Nếu **1 từ có 2 nghĩa khác nhau**, lưu thành **2 dòng**
- Nghĩa tiếng Việt phải **bám sát ngữ cảnh của nguồn**:
  - file HTML
  - đoạn text
  - transcript
  - PDF
  - hình ảnh
- Không gộp nhiều nghĩa vào một ô nếu các nghĩa đó thuộc các ngữ cảnh khác nhau

## Workflow đề xuất

### 1. Thêm kiến thức từ HTML / transcript / PDF / hình ảnh / text
1. Bỏ file nguồn vào `inbox/html_raw/` hoặc `inbox/transcript_raw/`
2. Dùng ChatGPT trích xuất:
   - từ vựng
   - ngữ pháp
   - mẫu câu / cụm từ
   - lỗi / điểm đáng lưu ý
3. Với từ vựng, ChatGPT đối chiếu `data/vocab_master.csv` theo **cặp** `Từ tiếng Đức : Nghĩa tiếng Việt`
4. ChatGPT xuất ra:
   - mục đã có
   - mục mới cần thêm
   - mục nên sửa vì nghĩa chưa đúng ngữ cảnh
   - block CSV sẵn để cập nhật

### 2. Khi đã học một mục
- cập nhật `status`
- tăng `memory_level`
- ghi `learned_at`
- tính `next_review`
- append log vào `data/learning_log.csv`

### 3. Khi cần ôn
- lọc các dòng có `next_review` đến hạn
- ưu tiên mục có `status = weak` hoặc `review`
- có thể tạo danh sách ôn theo ngày / tuần

## Gợi ý prompt dùng với ChatGPT

### Trích xuất từ HTML / PDF / ảnh / text
```text
Đọc nguồn tôi gửi.
Chuẩn hóa theo docs/rules_extract.md.
Đối chiếu với data/vocab_master.csv, data/grammar_master.csv, data/phrase_master.csv.

Riêng vocab:
- chỉ dùng 2 cột: Từ tiếng Đức, Nghĩa tiếng Việt
- kiểm tra trùng theo cặp "Từ tiếng Đức":"Nghĩa tiếng Việt"
- nếu 1 từ có 2 nghĩa theo 2 ngữ cảnh khác nhau thì tính là 2 dòng
- nghĩa phải bám sát ngữ cảnh của nguồn

Kết quả chia 4 phần:
1. Mục đã có
2. Mục mới cần thêm
3. Mục đã có nhưng nên sửa nghĩa
4. Block CSV sẵn để tôi cập nhật vào repo
```

### Đánh dấu đã học
```text
Đọc các file trong data/.
Đánh dấu các mục sau là đã học: ...
Hãy trả ra:
1. Các dòng cần cập nhật status / memory_level / learned_at / next_review
2. Block CSV để append vào data/learning_log.csv
3. Mục nào chưa tồn tại thì liệt kê riêng
```

## Nguyên tắc
- Với vocab, chỉ lưu dữ liệu tối giản để tra cứu nhanh
- Trùng chỉ tính khi **cả từ Đức và nghĩa Việt đều trùng**
- Cùng một từ Đức nhưng khác nghĩa Việt thì vẫn giữ riêng
- Không thêm mục mới nếu chỉ khác chữ hoa/thường hoặc khoảng trắng thừa
- Khi chưa chắc nghĩa theo ngữ cảnh, ghi vào `notes/` hoặc `inbox/extracted/` để xử lý sau
