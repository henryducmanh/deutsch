# Workflow sử dụng repo deutsch

## Mục tiêu
Repo này dùng để tích lũy kiến thức tiếng Đức theo thời gian và phản ánh trạng thái trí nhớ của người học.

## Luồng 1: thêm kiến thức từ HTML / transcript
1. Đặt file nguồn vào:
   - `inbox/html_raw/`
   - hoặc `inbox/transcript_raw/`
2. Gửi file cho ChatGPT
3. Yêu cầu ChatGPT:
   - trích từ vựng
   - trích ngữ pháp
   - trích mẫu câu / cụm cố định
   - phát hiện mục nào nên lưu vào lỗi hay sai
4. Đối chiếu với:
   - `data/vocab_master.csv`
   - `data/grammar_master.csv`
   - `data/phrase_master.csv`
   - `data/mistake_master.csv`
5. ChatGPT xuất:
   - mục đã có
   - mục mới
   - mục nên bổ sung
   - block CSV để cập nhật
6. Cập nhật file trong repo, rồi commit lên GitHub

## Luồng 2: ghi nhận đã học
Khi bạn nhắn:
- “Hôm nay tôi học từ ...”
- “Tôi đã học xong ngữ pháp ...”
- “Tôi vừa ôn lại ...”

Thì nên:
1. Tìm dòng tương ứng trong file master
2. Cập nhật:
   - `status`
   - `memory_level`
   - `learned_at`
   - `last_seen`
   - `next_review`
3. Append log vào `data/learning_log.csv`
4. Nếu cần, đẩy mục vào `data/review_queue.csv`

## Luồng 3: khi quên hoặc sai
Khi bạn nói:
- “Tôi hay quên từ này”
- “Tôi thường sai cấu trúc này”
- “Giới từ này tôi dễ nhầm”

Thì nên:
1. Giảm `memory_level`
2. Đổi `status` thành `weak` hoặc `review`
3. Cập nhật `next_review` gần hơn
4. Nếu là lỗi điển hình, thêm vào `mistake_master.csv`

## Luồng 4: ôn tập hằng ngày
Mỗi ngày hoặc mỗi tuần:
1. Lọc các mục có `next_review <= hôm nay`
2. Ưu tiên:
   - `status = weak`
   - `status = review`
   - các mục vừa học gần đây
3. Ghi kết quả ôn vào `learning_log.csv`

## Luồng 5: bổ sung dần chất lượng dữ liệu
Sau mỗi lần dùng:
- thêm nghĩa rõ hơn
- thêm ví dụ tốt hơn
- thêm tag chủ đề
- thêm ghi chú phân biệt

## Thực hành tối giản
Nếu bận:
- chỉ cần thêm mục mới
- đánh dấu đã học
- cập nhật `next_review`
- ghi log ngắn

Về sau có thể làm sạch dữ liệu từng bước.
