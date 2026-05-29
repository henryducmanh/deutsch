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
id,wort,wortart,formen,bedeutung,beispiel,uebersetzung,thema,lerndatum,level,quelle,source_type,tags,notes,parent_id,form_type
```

### Mô tả 16 cột

| Cột | Ý nghĩa | Ví dụ |
|---|---|---|
| `id` | VOC ID duy nhất | `VOC-20260527-0002` |
| `wort` | Dạng từ (lemma gốc hoặc biến thể) | `Karneval` / `Karnevals` |
| `wortart` | Từ loại | `Substantiv` / `Adjektiv` / `Verb` |
| `formen` | Các dạng biến hoá chuẩn (chỉ điền ở lemma) | `der Karneval, -e/-s` |
| `bedeutung` | Nghĩa tiếng Việt | `lễ hội hóa trang` |
| `beispiel` | Câu ví dụ từ nguồn gốc | *(câu tiếng Đức)* |
| `uebersetzung` | Dịch câu ví dụ | *(câu tiếng Việt)* |
| `thema` | Chủ đề DTZ | `Freizeit` / `Verkehr` |
| `lerndatum` | Ngày thêm vào | `2026-05-27` |
| `level` | Trình độ | `A2` / `B1` |
| `quelle` | Nguồn | `horen/1.1` |
| `source_type` | Loại nguồn | `horen` / `lesen` / `tutor` |
| `tags` | Tags tìm kiếm | `B1;DTZ;Verkehr` |
| `notes` | Ghi chú ngữ pháp / collocations | *(text tự do)* |
| `parent_id` | VOC ID của lemma gốc (rỗng nếu đây là lemma) | `VOC-20260527-0002` |
| `form_type` | Loại biến cách (rỗng nếu đây là lemma) | `GEN.SG` / `ADJ.AKK` |

### form_type — bảng mã chuẩn

| Code | Ý nghĩa |
|---|---|
| `NOM.SG` | Nominativ số ít |
| `GEN.SG` | Genitiv số ít |
| `DAT.SG` | Dativ số ít |
| `AKK.SG` | Akkusativ số ít |
| `NOM.PL` | Nominativ số nhiều |
| `GEN.PL` | Genitiv số nhiều |
| `DAT.PL` | Dativ số nhiều |
| `AKK.PL` | Akkusativ số nhiều |
| `ADJ.NOM` | Tính từ biến cách Nominativ |
| `ADJ.AKK` | Tính từ biến cách Akkusativ |
| `ADJ.DAT` | Tính từ biến cách Dativ |
| `ADJ.GEN` | Tính từ biến cách Genitiv |
| `KOMP` | So sánh hơn (Komparativ) |
| `SUP` | So sánh nhất (Superlativ) |
| `PRAET` | Quá khứ đơn (Präteritum) |
| `PERF` | Quá khứ hoàn thành (Perfekt / Partizip II) |

### Quy tắc biến thể (inflected forms)

- **ID biến thể:** `{parent_id}-{FORM_CODE}` — vd `VOC-20260527-0002-GEN.SG`
- **`wort`:** ghi đúng ký tự biến thể xuất hiện trong văn bản — vd `Karnevals`
- **`bedeutung`:** hint LingQ format — `{lemma} ({form_type viết đầy đủ}) = {nghĩa}` — vd `Karneval (Genitiv Sg.) = lễ hội hóa trang`
- **`formen`:** để rỗng (chỉ điền ở lemma)
- **`parent_id`:** VOC ID của lemma gốc
- **Append-only:** biến thể cũng append-only, không override

## Level

```text
1 = mới
2 = nhận biết
3 = quen thuộc
4 = nhuần nhuyễn
```
