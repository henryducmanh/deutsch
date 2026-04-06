# Workflow sử dụng repo deutsch

## Mục tiêu
Repo này dùng để tích lũy kiến thức tiếng Đức theo thời gian và phản ánh trạng thái trí nhớ của người học.

## Luồng 1: thêm kiến thức từ HTML / transcript / PDF / hình ảnh / text
1. Đặt file nguồn vào:
   - `inbox/html_raw/`
   - hoặc `inbox/transcript_raw/`
   - hoặc lưu kết quả OCR / text trích ra vào `inbox/extracted/`
2. Gửi file hoặc nội dung cho ChatGPT
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
5. Riêng với `data/vocab_master.csv`:
   - chỉ dùng 2 cột: `Từ tiếng Đức`, `Nghĩa tiếng Việt`
   - kiểm tra trùng theo cặp `Từ tiếng Đức : Nghĩa tiếng Việt`
   - nếu 1 từ có 2 nghĩa khác nhau theo ngữ cảnh, lưu thành 2 dòng
6. ChatGPT xuất:
   - mục đã có
   - mục mới
   - mục nên sửa nghĩa
   - block CSV để cập nhật
7. Cập nhật file trong repo, rồi commit lên GitHub

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
- sửa nghĩa cho sát ngữ cảnh hơn
- tách một dòng thành nhiều dòng nếu phát hiện một từ có nhiều nghĩa khác nhau
- thêm nghĩa mới chỉ khi có nguồn ngữ cảnh rõ ràng
- thêm tag hoặc ghi chú nếu cần ở các file không phải vocab

## Thực hành tối giản
Nếu bận:
- chỉ cần thêm cặp `Từ tiếng Đức : Nghĩa tiếng Việt`
- giữ nghĩa đúng với ngữ cảnh nguồn
- không gộp nhiều nghĩa rời nhau vào cùng một ô
- cập nhật log khi cần
