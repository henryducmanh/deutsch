# Next Action

## Trạng thái hiện tại

Đã chốt repo `henryducmanh/app-deutsch` làm kho chính cho dữ liệu học tiếng Đức.

Ban đầu chỉ tổ chức:

```text
docs/
data/
```

## Việc tiếp theo gần nhất

Khi có đoạn text tiếng Đức mới:

```text
1. ChatGPT bóc tách từ vựng theo ngữ cảnh.
2. Xuất bảng đúng schema trong data/README.md.
3. Lưu file theo ngày vào data/01_ai_extracted/by-date/.
4. Nếu có chủ đề rõ, lưu thêm theo chủ đề vào data/01_ai_extracted/by-topic/.
```

## Việc dành cho Cursor/Claude sau này

Khi người học muốn làm app/tool PHP/MySQL:

```text
1. Copy một dự án nền có sẵn như CIF/domain.
2. Xây module import CSV từ data/03_unified/vocab_master.csv.
3. Xây màn hình search/lọc theo wort, bedeutung, thema, lerndatum, level.
4. Cho phép update level và notes.
5. Export ngược CSV vào data/02_tool_exports/.
```
