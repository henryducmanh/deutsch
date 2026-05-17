# Claude/Cursor Pipeline – German AI Learning OS

## 1. Mục tiêu

Tài liệu này dành cho Claude Code và Cursor đọc để triển khai local batch pipeline cho repo `app-deutsch`.

Người học không muốn dán từng đoạn text vào ChatGPT nữa. Thay vào đó, người học chỉ cần bỏ source vào các thư mục input, AI/script sẽ xử lý hàng loạt.

## 2. Folder mục tiêu

```text
input/
  images/
  audio/
  text/
  pdf/

queue/

archive/
  images/
  audio/
  text/
  pdf/

tutor/
  lesson_plans/
  homework/
  tutor_notes/
  progress_reports/

prompts/
  batch_extract_vocab.md
  create_tutor_lesson_plan.md
  create_homework_after_lesson.md
  lingq_sync_plan.md
```

## 3. Batch workflow

```text
1. Người học bỏ file vào input/images, input/audio, input/text hoặc input/pdf.
2. Script scan file mới.
3. File mới được ghi log vào data/processed_files.csv với status pending.
4. Claude/Cursor xử lý theo prompt batch_extract_vocab.md.
5. Output mới ghi vào output/new_vocab hoặc data/01_ai_extracted.
6. Không ghi thẳng vào vocab_master nếu chưa review.
7. Sau khi xử lý xong, source move sang archive theo loại file.
8. processed_files.csv cập nhật status processed.
```

## 4. Giai đoạn không dùng API

Ban đầu không cần gọi OpenAI/Claude API riêng. Claude Code hoặc Cursor agent đọc local file và làm theo prompt.

Giới hạn:

- Không xử lý quá nhiều file một lần.
- Nên chia batch theo ngày/chủ đề.
- Audio cần có transcript sẵn hoặc dùng tool transcribe riêng ở giai đoạn sau.

## 5. Giai đoạn có script

Sau này có thể thêm scripts:

```text
scripts/scan_inputs.py
scripts/move_to_queue.py
scripts/archive_processed.py
scripts/export_anki.py
scripts/merge_reviewed_vocab.py
```

## 6. Quy tắc an toàn dữ liệu

- Không xóa source raw ngay sau xử lý.
- Chỉ move sang archive.
- Không ghi đè master CSV nếu chưa backup/review.
- Mọi file output nên có ngày và source trong tên file.
- processed_files.csv dùng để chống xử lý trùng.

## 7. Output mong muốn

Mỗi batch nên tạo:

```text
output/new_vocab/YYYY-MM-DD-source.csv
output/chunks/YYYY-MM-DD-source.csv
output/speaking/YYYY-MM-DD-source.md
output/listening/YYYY-MM-DD-source.md
```

Nếu output folder chưa có, Claude/Cursor có thể tạo thêm trong bước triển khai sau.

## 8. Cursor/Claude nên làm gì trước

Thứ tự ưu tiên:

1. Tạo đầy đủ folder + .gitkeep.
2. Tạo prompts chuẩn.
3. Tạo docs hướng dẫn workflow.
4. Chưa viết script phức tạp.
5. Sau khi người học xác nhận workflow, mới viết scripts.
