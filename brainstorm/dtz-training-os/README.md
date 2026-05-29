# DTZ B1 Training OS — Cowork + Claude Code + Gia sư

Bộ tài liệu này mô tả cách xây dựng một hệ thống luyện thi DTZ B1 dùng:

- đề thi mẫu DTZ
- tài liệu chính thức BAMF/g.a.s.t.
- Claude Code để xử lý dữ liệu
- Cowork để điều phối buổi học
- gia sư để tạo áp lực nói và sửa lỗi
- học viên để tạo output thật
- vòng lặp cá nhân hóa sau mỗi buổi học

Mục tiêu không phải là học tiếng Đức tổng quát, mà là tối ưu cho kỳ thi DTZ B1.

## Cấu trúc file

```text
brainstorm/dtz-training-os/
├── README.md
├── 00-system-overview.md
├── 01-data-input-protocol.md
├── 02-claude-code-processing-pipeline.md
├── 03-knowledge-base-structure.md
├── 04-cowork-session-generation.md
├── 05-live-90-minute-session.md
├── 06-interaction-layer.md
├── 07-post-session-personalization.md
├── 08-notion-database-design.md
├── 09-claude-code-task-spec.md
└── 10-implementation-roadmap.md
```

## Nguyên tắc vận hành

```text
Every session makes the next session smarter.
```

Mỗi buổi học 90 phút phải tạo ra dữ liệu mới:

- lỗi thật của học viên
- chunk dùng tốt
- grammar yếu
- speaking freeze moments
- listening traps bị sai
- writing template còn yếu
- bài retry cho buổi sau

Nếu sau buổi học không có dữ liệu quay ngược về Cowork, hệ thống chưa chạy đúng.
