# Workflow - app-deutsch

## Workflow hằng ngày

```text
1. Người học gửi đoạn text tiếng Đức cho ChatGPT.
2. ChatGPT bóc tách từ vựng theo ngữ cảnh.
3. ChatGPT xuất bảng copy table hoặc CSV đúng schema.
4. Lưu vào data/01_ai_extracted/by-date/YYYY-MM-DD.csv.
5. Nếu có chủ đề rõ ràng, copy/ghi thêm vào data/01_ai_extracted/by-topic/<topic>.csv.
```

## Workflow tổng hợp định kỳ

```text
1. AI đọc data/01_ai_extracted/.
2. AI đọc data/02_tool_exports/ nếu đã có app/tool xuất dữ liệu.
3. AI đối chiếu trùng lặp.
4. AI ưu tiên level/notes từ tool export nếu có.
5. AI tạo hoặc cập nhật data/03_unified/vocab_master.csv.
```

## Workflow với app/tool PHP/MySQL sau này

```text
1. Import data/03_unified/vocab_master.csv vào MySQL.
2. Người học dùng app để search, lọc, học, update level.
3. App export dữ liệu mới ra CSV.
4. Lưu CSV xuất ngược vào data/02_tool_exports/.
5. AI đọc file export này để cập nhật nguồn tổng hợp.
```

## Nguyên tắc thao tác

- GitHub là nơi tổng hợp data và trao đổi thông tin.
- App/tool chỉ là giao diện học và xử lý dữ liệu.
- Không để app/tool trở thành nguồn duy nhất.
- Mọi dữ liệu quan trọng cần xuất được về GitHub dưới dạng CSV.
