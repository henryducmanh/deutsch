# Next Claude Prompt

Bạn đang làm việc trong repo `henryducmanh/app-deutsch`.

## Bối cảnh

Repo này đang được xây dựng thành German AI Learning OS cho người học tiếng Đức chuẩn bị DTZ B1.

Người học có vấn đề chính:

- học nhiều nhưng nhanh quên;
- đọc thì hiểu nhưng nghe không bắt được;
- biết từ nhưng không nói/viết được;
- thiếu chunk/Redemittel để phản xạ;
- học với gia sư nhưng chưa có hệ thống quản lý trước/sau buổi học;
- dùng LingQ nhưng LingQ yếu ở học cụm từ và active production.

## Việc đã chốt

GitHub là source of truth.

LingQ là input/exposure engine.

Anki là review engine.

Claude/Cursor sẽ hỗ trợ local batch processing.

ChatGPT đóng vai trò planner/orchestrator.

## Hãy đọc các file sau

```text
README.md
data/README.md
brainstorm/2026-05-17-german-ai-learning-os.md
docs/GERMAN_AI_LEARNING_OS.md
docs/CLAUDE_CURSOR_PIPELINE.md
prompts/batch_extract_vocab.md
prompts/create_tutor_lesson_plan.md
prompts/create_homework_after_lesson.md
prompts/lingq_sync_plan.md
```

## Nhiệm vụ tiếp theo

1. Kiểm tra repo hiện tại.
2. Đề xuất bước triển khai Phase 1 nhỏ nhất, ít rủi ro nhất.
3. Nếu được phép sửa file, tạo folder structure:

```text
input/images
input/audio
input/text
input/pdf
queue
archive/images
archive/audio
archive/text
archive/pdf
tutor/lesson_plans
tutor/homework
tutor/tutor_notes
tutor/progress_reports
```

4. Tạo hoặc đề xuất `tutor/student_profile.md`.
5. Chưa viết script phức tạp nếu chưa cần.
6. Không thay đổi schema master nếu chưa có yêu cầu rõ.
7. Không ghi đè dữ liệu học hiện có.

## Kết quả mong muốn

Một plan ngắn, rõ, có checklist triển khai. Nếu sửa repo, commit chỉ nên gồm tài liệu/folder placeholder, chưa xử lý dữ liệu thật.
