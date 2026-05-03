# AI Memory - app-deutsch

## Quyết định đã chốt

- GitHub là kho chính để lưu dữ liệu học tiếng Đức.
- CSV là định dạng trung tâm.
- Google Sheets dùng để mở/xem/sửa nhanh CSV khi cần.
- Anki dùng để import dữ liệu học lặp lại.
- Sau này có thể thêm tool PHP/MySQL để import, search, lọc, cập nhật từ vựng và xuất ngược về GitHub.

## Mô hình dữ liệu

Repo có 2 nguồn dữ liệu chính:

```text
1. AI extracted source
   - Dữ liệu do ChatGPT bóc tách từ đoạn text học hằng ngày.
   - Lưu trong data/01_ai_extracted/

2. Tool export source
   - Dữ liệu xuất ngược từ app/tool sau khi người học search, lọc, update level.
   - Lưu trong data/02_tool_exports/
```

Nguồn tổng hợp chuẩn hóa:

```text
data/03_unified/
```

## Luồng học dự kiến

```text
Đoạn text Đức hằng ngày
→ ChatGPT bóc từ vựng theo ngữ cảnh
→ lưu CSV vào data/01_ai_extracted/by-date/
→ định kỳ tổng hợp vào data/03_unified/vocab_master.csv
→ import vào app/tool PHP/MySQL
→ người học tra cứu, học, cập nhật level
→ tool export lại CSV vào data/02_tool_exports/
→ AI đối chiếu 2 nguồn để chống trùng và chuẩn hóa
```

## Level học

```text
1 = mới
2 = nhận biết
3 = quen thuộc
4 = nhuần nhuyễn
```

## Ghi chú cho AI

Khi người dùng gửi text tiếng Đức, ưu tiên xuất từ vựng theo ngữ cảnh dạng CSV/copy table, bám schema trong `data/README.md`.

Không tự ý đổi tên cột chính nếu chưa cập nhật `docs/DATA_CONTRACT.md`.
