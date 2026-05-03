# Data Contract - app-deutsch

Tài liệu này định nghĩa chuẩn dữ liệu tối thiểu để ChatGPT, Cursor/Claude và tool PHP/MySQL cùng đọc/ghi thống nhất.

## Nguồn dữ liệu

### 1. AI source

```text
data/01_ai_extracted/
```

Dữ liệu do ChatGPT tạo khi bóc tách từ vựng theo ngữ cảnh.

### 2. Tool source

```text
data/02_tool_exports/
```

Dữ liệu do app/tool PHP/MySQL xuất ngược ra GitHub sau khi người học tra cứu, sửa, cập nhật level.

### 3. Unified source

```text
data/03_unified/
```

Dữ liệu đã tổng hợp, chống trùng, chuẩn hóa từ 2 nguồn trên.

## CSV schema chính

Các file CSV từ vựng nên dùng cột theo thứ tự sau:

```csv
id,wort,wortart,formen,bedeutung,beispiel,uebersetzung,thema,lerndatum,level,quelle,source_type,tags,notes
```

## Ý nghĩa cột

| Cột | Ý nghĩa |
|---|---|
| id | ID ổn định nếu có, có thể để trống lúc AI tạo |
| wort | từ/cụm từ tiếng Đức |
| wortart | loại từ: Nomen, Verb, Adjektiv, Redemittel... |
| formen | giống/số nhiều/chia động từ/cấu trúc ngữ pháp |
| bedeutung | nghĩa tiếng Việt theo ngữ cảnh |
| beispiel | câu ví dụ tiếng Đức |
| uebersetzung | nghĩa tiếng Việt của câu ví dụ |
| thema | chủ đề |
| lerndatum | ngày học, dạng YYYY-MM-DD |
| level | mức độ 1-4 |
| quelle | nguồn bài/text/hình |
| source_type | ai_extracted/tool_export/unified |
| tags | tag phụ, phân tách bằng dấu `;` |
| notes | ghi chú thêm |

## Level

```text
1 = mới
2 = nhận biết
3 = quen thuộc
4 = nhuần nhuyễn
```

## Quy tắc chống trùng

Ưu tiên so khớp theo:

```text
lowercase(trim(wort)) + lowercase(trim(bedeutung))
```

Nếu cùng `wort` nhưng nghĩa khác theo ngữ cảnh, vẫn được giữ nhiều dòng.

Ví dụ:

```text
die Bank = ngân hàng
die Bank = ghế dài
```

## Quy tắc AI khi tổng hợp

Khi AI đọc dữ liệu:

```text
1. Đọc data/01_ai_extracted/ trước để lấy nguồn học hằng ngày.
2. Đọc data/02_tool_exports/ để biết dữ liệu đã được người học cập nhật trong tool.
3. Ưu tiên level/notes mới nhất từ tool_export nếu có xung đột.
4. Ghi kết quả chuẩn hóa vào data/03_unified/.
```
