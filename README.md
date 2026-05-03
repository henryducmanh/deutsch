# app-deutsch

Kho chính để lưu, chuẩn hóa và trao đổi dữ liệu học tiếng Đức.

## Mục tiêu

Repo này là nơi tổng hợp dữ liệu từ vựng tiếng Đức theo ngữ cảnh để ChatGPT, Cursor/Claude và tool PHP/MySQL sau này cùng dùng chung một chuẩn.

Ban đầu repo tập trung vào 2 thư mục chính:

```text
docs/   = tài liệu cầu nối AI, memory, quy ước xử lý
data/   = dữ liệu từ vựng CSV/Markdown
```

Các thư mục app/tool PHP/MySQL có thể được thêm sau, ví dụ copy từ một dự án có sẵn như CIF/domain để làm giao diện import, search, lọc, update và xuất ngược dữ liệu.

## Luồng dữ liệu chính

```text
1. Người học gửi đoạn text tiếng Đức cho ChatGPT
2. ChatGPT bóc tách từ vựng theo ngữ cảnh
3. Dữ liệu được lưu vào data/01_ai_extracted/
4. Lâu lâu import tổng thể vào tool PHP/MySQL để học, search, lọc, update level
5. Tool xuất ngược dữ liệu ra data/02_tool_exports/
6. AI đọc cả 2 nguồn để đối chiếu, tổng hợp, chống trùng
7. Kết quả chuẩn hóa nằm ở data/03_unified/
```

## Hai nguồn dữ liệu

### 1. Nguồn do AI bóc tách

Lưu tại:

```text
data/01_ai_extracted/
```

Dùng cho dữ liệu hằng ngày do ChatGPT tạo từ bài học, đoạn text, hình ảnh hoặc bài nghe.

### 2. Nguồn xuất từ tool

Lưu tại:

```text
data/02_tool_exports/
```

Dùng cho dữ liệu đã được học, sửa, lọc, cập nhật level trong app/tool PHP/MySQL rồi xuất ngược về GitHub.

## Định dạng chính

CSV là định dạng trung tâm để dễ:

- mở bằng Google Sheets
- import vào Anki
- import vào PHP/MySQL tool
- để AI/Cursor/Claude đọc và xử lý

File schema chính xem tại:

```text
data/README.md
```

## Quy ước mức độ học

```text
1 = mới
2 = nhận biết
3 = quen thuộc
4 = nhuần nhuyễn
```
