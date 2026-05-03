# data

Thư mục này lưu dữ liệu từ vựng tiếng Đức theo ngữ cảnh.

## Cấu trúc

```text
data/
├── README.md
├── 01_ai_extracted/
│   ├── by-date/
│   ├── by-topic/
│   └── source-texts/
├── 02_tool_exports/
│   ├── latest/
│   └── history/
└── 03_unified/
    ├── vocab_master.csv
    ├── by-level/
    └── exports/
```

## Nguồn 1: AI bóc tách

```text
data/01_ai_extracted/
```

Dữ liệu do ChatGPT tạo từ đoạn text học hằng ngày.

Ví dụ:

```text
data/01_ai_extracted/by-date/2026-05-03.csv
data/01_ai_extracted/by-topic/familie.csv
data/01_ai_extracted/source-texts/2026-05-03-kinderbetreuung.md
```

## Nguồn 2: Tool xuất ngược

```text
data/02_tool_exports/
```

Dữ liệu do app/tool PHP/MySQL xuất ngược sau khi người học cập nhật level, notes, trạng thái học.

Ví dụ:

```text
data/02_tool_exports/latest/vocab_export_latest.csv
data/02_tool_exports/history/2026-06-01-tool-export.csv
```

## Nguồn tổng hợp

```text
data/03_unified/
```

Dữ liệu chuẩn hóa sau khi AI đối chiếu 2 nguồn.

File chính:

```text
data/03_unified/vocab_master.csv
```

## CSV schema chính

```csv
id,wort,wortart,formen,bedeutung,beispiel,uebersetzung,thema,lerndatum,level,quelle,source_type,tags,notes
```

## Level

```text
1 = mới
2 = nhận biết
3 = quen thuộc
4 = nhuần nhuyễn
```
