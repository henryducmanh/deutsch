# docs

Thư mục này là cầu nối giữa người học, ChatGPT, Cursor/Claude và tool PHP/MySQL sau này.

## Vai trò

```text
docs/AI_MEMORY.md        = memory dự án, quyết định đã chốt
docs/WORKFLOW.md         = luồng làm việc hằng ngày
docs/DATA_CONTRACT.md    = quy ước dữ liệu để AI và app cùng hiểu
docs/NEXT_ACTION.md      = việc tiếp theo cho ChatGPT/Cursor/Claude
```

## Nguyên tắc

- Không lưu dữ liệu từ vựng chính trong docs.
- Không biến docs thành database.
- Chỉ lưu quy ước, trạng thái, quyết định, hướng dẫn cho AI/tool.
- Khi thay đổi cấu trúc data, cập nhật `docs/DATA_CONTRACT.md` và `data/README.md`.
