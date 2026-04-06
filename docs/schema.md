# Schema dữ liệu

Tài liệu này mô tả các file CSV chính trong thư mục `data/`.

## 1. vocab_master.csv
Lưu từ vựng trung tâm theo dạng tối giản.

### Cột
- `Từ tiếng Đức`
- `Nghĩa tiếng Việt`

### Quy tắc
- Mỗi dòng là **một cặp nghĩa theo ngữ cảnh**
- Khóa dò trùng là cặp:
  - `Từ tiếng Đức`
  - `Nghĩa tiếng Việt`
- Nếu cùng một từ tiếng Đức nhưng có 2 nghĩa Việt khác nhau theo 2 ngữ cảnh, lưu thành 2 dòng
- Nghĩa tiếng Việt phải bám theo nguồn gốc ngữ cảnh, không gộp bừa nhiều nghĩa rộng vào một ô
- Khi so sánh trùng:
  - bỏ khác biệt chữ hoa / chữ thường
  - bỏ khoảng trắng thừa ở đầu/cuối
  - chuẩn hóa nhiều khoảng trắng liên tiếp thành một khoảng trắng

### Ví dụ
```csv
Từ tiếng Đức,Nghĩa tiếng Việt
übersehen,bỏ sót
übersehen,không nhận ra
vermieten,cho thuê
```

## 2. grammar_master.csv
Lưu điểm ngữ pháp, cấu trúc, mẫu biến đổi.

### Cột
- `id`
- `topic`
- `pattern`
- `usage`
- `level`
- `example`
- `compare_with`
- `status`
- `memory_level`
- `first_seen`
- `last_seen`
- `learned_at`
- `next_review`
- `source`
- `tags`
- `notes`

## 3. phrase_master.csv
Lưu Redemittel, mẫu câu, cụm cố định, câu khung dùng trong DTZ.

### Cột
- `id`
- `phrase_type`
- `pattern`
- `meaning_vi`
- `use_case`
- `example`
- `status`
- `memory_level`
- `first_seen`
- `last_seen`
- `learned_at`
- `next_review`
- `source`
- `tags`
- `notes`

## 4. mistake_master.csv
Lưu lỗi bạn thường sai hoặc các cặp dễ nhầm.

### Cột
- `id`
- `error_type`
- `wrong_form`
- `correct_form`
- `explanation`
- `status`
- `memory_level`
- `first_seen`
- `last_seen`
- `next_review`
- `source`
- `tags`
- `notes`

## 5. review_queue.csv
Hàng đợi ôn tập.

### Cột
- `date`
- `item_type`
- `item_id`
- `reason`
- `priority`
- `status`

## 6. learning_log.csv
Nhật ký học tập.

### Cột
- `date`
- `action`
- `item_type`
- `item_id`
- `content`
- `result`
- `source`
- `notes`

## Quy ước ngày tháng
- Dùng định dạng `YYYY-MM-DD`
- Nếu chưa có dữ liệu thì để trống

## Quy ước tags
- Dùng dấu `|` để nối nhiều tag
- Ví dụ: `DTZ|B1|Wohnen|Arbeit`
