# Prompt – Batch Extract Vocabulary

Bạn là AI agent xử lý batch dữ liệu học tiếng Đức trong repo `app-deutsch`.

## Mục tiêu

Đọc các file mới trong `input/` hoặc `queue/`, bóc tách từ vựng và chunk hữu ích cho DTZ B1, sau đó xuất file CSV/Markdown để người học review.

## Nguồn input

```text
input/images/
input/audio/
input/text/
input/pdf/
queue/
```

## Ưu tiên xử lý

1. Từ vựng/chunk dùng được cho speaking và writing.
2. Từ/chunk liên quan DTZ B1.
3. Cụm cố định, Redemittel, cấu trúc câu.
4. Từ người học dễ nhận ra khi đọc nhưng khó nghe/nói.
5. Không ưu tiên từ quá hiếm, quá hàn lâm hoặc ít dùng trong DTZ.

## Output vocabulary CSV

Xuất theo schema hiện tại:

```csv
id,wort,wortart,formen,bedeutung,beispiel,uebersetzung,thema,lerndatum,level,quelle,source_type,tags,notes
```

Quy tắc:

- `id`: để trống nếu chưa có hệ thống sinh ID.
- `wort`: Grundform.
- `wortart`: Nomen/Verb/Adjektiv/Adverb/Redemittel/Chunk.
- `formen`: giống/số/chia động từ nếu cần.
- `bedeutung`: nghĩa tiếng Việt ngắn gọn.
- `beispiel`: câu Đức theo đúng ngữ cảnh hoặc câu B1 tự nhiên.
- `uebersetzung`: nghĩa tiếng Việt của câu ví dụ.
- `thema`: chủ đề DTZ như Familie, Arbeit, Wohnen, Gesundheit, Behörden, Schule, Alltag.
- `lerndatum`: ngày xử lý YYYY-MM-DD.
- `level`: 1 mặc định nếu mới; 2 nếu quen; 3/4 chỉ khi có dữ liệu chắc chắn.
- `quelle`: tên file nguồn.
- `source_type`: image/audio/text/pdf/tutor/lingq/dtz.
- `tags`: chunk, sprechen, schreiben, hören, DTZ, Redemittel...
- `notes`: lý do chọn, lỗi thường gặp, cách dùng.

## Output chunks

Nếu gặp cụm/chunk quan trọng, xuất thêm Markdown hoặc CSV riêng với các cột:

```csv
chunk,meaning_vi,usage,thema,example_de,example_vi,level,status,source,notes
```

## Không được làm

- Không ghi thẳng vào `data/03_unified/vocab_master.csv` nếu chưa được yêu cầu.
- Không xóa file input.
- Không tự ý đổi schema master.
- Không thêm quá nhiều từ rời rạc A1 nếu không phục vụ production.

## Output location

Ưu tiên:

```text
output/new_vocab/YYYY-MM-DD-source.csv
output/chunks/YYYY-MM-DD-source.csv
output/speaking/YYYY-MM-DD-source.md
output/listening/YYYY-MM-DD-source.md
```

Nếu các thư mục output chưa tồn tại thì đề xuất tạo chúng.
