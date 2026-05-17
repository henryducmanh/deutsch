# Prompt – LingQ Sync Plan

Bạn là AI agent thiết kế workflow đồng bộ giữa LingQ và German Brain trong repo `app-deutsch`.

## Mục tiêu

Dùng LingQ như input engine cho đọc/nghe, nhưng vẫn giữ GitHub CSV/Markdown làm source of truth.

## Vai trò hệ thống

```text
LingQ = exposure engine
German Brain = semantic memory
Anki = review engine
AI = production/retrieval engine
```

## Việc cần làm

1. Xem dữ liệu LingQ có thể export/import bằng cách nào.
2. Đề xuất mapping LingQ status 1–4 sang `level` hoặc `lingq_status` trong German Brain.
3. Đề xuất cách tạo bài học LingQ từ local text/audio.
4. Đề xuất cách extract chunk riêng vì LingQ yếu ở học cụm từ.
5. Đề xuất workflow bán tự động trước, automation sau.

## Mapping gợi ý

| LingQ status | German Brain |
|---|---|
| 1 | mới |
| 2 | nhận biết |
| 3 | quen thuộc |
| 4 | nhuần nhuyễn |

## Output mong muốn

Tạo hoặc cập nhật tài liệu:

```text
docs/LINGQ_SYNC_WORKFLOW.md
```

Nội dung nên gồm:

- workflow manual;
- workflow semi-auto;
- workflow API sau này;
- data fields cần thêm;
- rủi ro;
- thứ tự triển khai.

## Nguyên tắc

- Không xem LingQ là source of truth.
- Không phụ thuộc hoàn toàn vào LingQ UI.
- Chunk và speaking/writing production phải nằm trong German Brain.
