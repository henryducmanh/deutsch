# 02_tool_exports

Thư mục lưu dữ liệu do app/tool PHP/MySQL xuất ngược về GitHub.

Đây là nguồn thứ 2 để AI đọc và đối chiếu với dữ liệu do ChatGPT bóc tách.

## Cấu trúc

```text
02_tool_exports/
├── README.md
├── latest/
└── history/
```

## latest

Lưu bản export mới nhất từ tool.

Ví dụ:

```text
latest/vocab_export_latest.csv
```

## history

Lưu các bản export theo ngày để có lịch sử.

Ví dụ:

```text
history/2026-06-01-tool-export.csv
```

## Quy tắc ưu tiên

Khi AI tổng hợp dữ liệu:

```text
- Nếu cùng từ/cùng nghĩa nhưng level khác nhau, ưu tiên level từ tool_export.
- Nếu tool_export có notes mới, giữ notes đó.
- Nếu AI source có ví dụ/ngữ cảnh tốt hơn, có thể bổ sung vào unified.
```
