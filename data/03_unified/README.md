# 03_unified

Thư mục lưu dữ liệu đã được tổng hợp và chuẩn hóa từ 2 nguồn:

```text
1. data/01_ai_extracted/
2. data/02_tool_exports/
```

File chính:

```text
vocab_master.csv
```

## Vai trò

```text
vocab_master.csv = nguồn sạch nhất để import vào Google Sheets, Anki hoặc app/tool PHP/MySQL
```

## Quy tắc

- Không ghi dữ liệu thô chưa kiểm tra vào đây.
- Chỉ ghi sau khi đã đối chiếu trùng cơ bản.
- Nếu level khác nhau giữa AI source và tool export, ưu tiên tool export.
