# Schema dữ liệu

Tài liệu này mô tả các file CSV chính trong thư mục `data/`.

## 1. vocab_master.csv
Lưu từ vựng trung tâm: từ đơn, từ ghép, động từ, cụm từ ngắn, collocation, thành ngữ ngắn.

### Cột
- `id`: mã duy nhất, ví dụ `VOC0001`
- `term_type`: `word`, `compound`, `phrasal`, `idiom`, `collocation`
- `lemma`: dạng gốc
- `normalized`: dạng chuẩn hóa để dò trùng
- `article`: `der`, `die`, `das` nếu là danh từ
- `pos`: từ loại
- `meaning_vi`: nghĩa tiếng Việt
- `meaning_de`: giải thích tiếng Đức ngắn nếu cần
- `example`: ví dụ
- `status`: xem `docs/status_system.md`
- `memory_level`: mức độ nhớ từ `0` đến `5`
- `first_seen`: ngày gặp đầu tiên
- `last_seen`: ngày ôn hoặc nhìn thấy gần nhất
- `learned_at`: ngày đánh dấu đã học
- `next_review`: ngày cần ôn tiếp
- `source`: nguồn, ví dụ `dtz_html_2026_04_05`
- `tags`: nhãn, phân tách bằng `|`
- `notes`: ghi chú

## 2. grammar_master.csv
Lưu điểm ngữ pháp, cấu trúc, mẫu biến đổi.

### Cột
- `id`
- `topic`: tên chủ điểm
- `pattern`: cấu trúc chính
- `usage`: cách dùng
- `level`: ví dụ `A2`, `B1`, `B2`
- `example`: ví dụ
- `compare_with`: cấu trúc dễ nhầm
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
- `phrase_type`: `redemittel`, `sentence_pattern`, `fixed_phrase`, `exam_phrase`, `polite_expression`
- `pattern`: cụm hoặc mẫu câu
- `meaning_vi`: nghĩa tiếng Việt
- `use_case`: ngữ cảnh dùng
- `example`: ví dụ
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
- `error_type`: loại lỗi
- `wrong_form`: dạng sai
- `correct_form`: dạng đúng
- `explanation`: giải thích ngắn
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
- `item_type`: `vocab`, `grammar`, `phrase`, `mistake`
- `item_id`
- `reason`: ví dụ `due_today`, `forgotten`, `weak_item`
- `priority`: `low`, `medium`, `high`
- `status`: `open`, `done`, `skip`

## 6. learning_log.csv
Nhật ký học tập.

### Cột
- `date`
- `action`: `add`, `learn`, `review`, `forget`, `edit`, `promote`, `demote`
- `item_type`
- `item_id`
- `content`: nội dung chính
- `result`: kết quả, ví dụ `status=new->learning`
- `source`
- `notes`

## Quy ước ngày tháng
- Dùng định dạng `YYYY-MM-DD`
- Nếu chưa có dữ liệu thì để trống

## Quy ước tags
- Dùng dấu `|` để nối nhiều tag
- Ví dụ: `DTZ|B1|Wohnen|Arbeit`
