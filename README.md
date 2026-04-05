# deutsch

Kho tri thức học tiếng Đức cá nhân, lưu trên GitHub để dùng cùng ChatGPT và Cursor.

## Mục tiêu
- Lưu từ vựng, ngữ pháp, mẫu câu, cụm từ, thành ngữ và lỗi thường gặp
- Theo dõi trạng thái học: mới gặp, đang học, đã học, cần ôn, hay quên, đã chắc
- Dùng ChatGPT để trích xuất từ HTML / transcript, đối chiếu với dữ liệu có sẵn, rồi đề xuất cập nhật
- Dùng GitHub làm nguồn dữ liệu chuẩn để tích lũy lâu dài

## Cấu trúc chính
- `docs/` : mô tả schema, workflow, quy tắc trích xuất, hệ trạng thái
- `data/` : các file CSV dữ liệu trung tâm
- `inbox/` : nơi đặt HTML, transcript và kết quả trích xuất
- `notes/` : ghi chú học theo ngày/tuần/DTZ
- `templates/` : file mẫu để thêm dòng mới hoặc ghi log học

## File dữ liệu quan trọng
- `data/vocab_master.csv` : kho từ vựng trung tâm
- `data/grammar_master.csv` : kho ngữ pháp trung tâm
- `data/phrase_master.csv` : kho mẫu câu / cụm cố định / Redemittel
- `data/mistake_master.csv` : các lỗi hay sai
- `data/review_queue.csv` : hàng đợi ôn tập
- `data/learning_log.csv` : nhật ký học

## Workflow đề xuất

### 1. Thêm kiến thức từ HTML / transcript
1. Bỏ file nguồn vào `inbox/html_raw/` hoặc `inbox/transcript_raw/`
2. Dùng ChatGPT trích xuất:
   - từ vựng
   - ngữ pháp
   - mẫu câu / cụm từ
   - lỗi / điểm đáng lưu ý
3. ChatGPT đối chiếu với các file trong `data/`
4. ChatGPT xuất ra block CSV để thêm mới hoặc chỉnh sửa
5. Dùng Cursor hoặc GitHub web để cập nhật rồi commit

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

### Trích xuất từ HTML
```text
Đọc file HTML tôi gửi.
Chuẩn hóa theo docs/rules_extract.md.
Đối chiếu với data/vocab_master.csv, data/grammar_master.csv, data/phrase_master.csv.
Kết quả chia 4 phần:
1. Mục đã có
2. Mục mới cần thêm
3. Mục đã có nhưng nên bổ sung nghĩa / ví dụ / ghi chú
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
- Mỗi kiến thức chỉ nên có một dòng master chính
- Ưu tiên lưu dạng chuẩn hóa để tránh trùng
- Không thêm mục mới nếu chỉ khác chữ hoa/thường
- Nếu cùng một lemma nhưng khác từ loại, có thể lưu riêng
- Khi chưa chắc cách phân loại, ghi tạm vào `notes/` rồi xử lý sau
