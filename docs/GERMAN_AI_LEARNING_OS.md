# German AI Learning OS

## 1. Mục tiêu

Xây dựng hệ thống học tiếng Đức cá nhân giúp người học vượt qua điểm nghẽn A2 → B1 DTZ.

Hệ thống cần giải quyết các vấn đề:

- học trước quên sau;
- đọc thì hiểu nhưng nghe không bắt được;
- biết từ nhưng không nói/viết được;
- thiếu phản xạ chunk và Redemittel;
- dữ liệu học nằm rải rác trong ChatGPT, LingQ, gia sư, đề thi, audio, ảnh chụp.

## 2. Vai trò của từng thành phần

| Thành phần | Vai trò |
|---|---|
| GitHub | Source of truth, lưu CSV/Markdown, version control |
| LingQ | Input engine cho đọc/nghe extensive |
| Anki | Review engine cho spaced repetition |
| ChatGPT | Planner, extraction, explanation, tutor assistant |
| Claude Code | Local batch processing, xử lý nhiều file |
| Cursor | Code/tool implementation, repo-aware assistant |
| Notion | Optional UI/wiki, không phải source of truth |
| Qdrant/Chroma | Future semantic vector search |

## 3. Kiến trúc giai đoạn đầu

```text
input/
  images/
  audio/
  text/
  pdf/
        ↓
queue/
        ↓
AI batch processing
        ↓
output + data master
        ↓
archive/
```

## 4. Data master

Các file dữ liệu chính cần có hoặc sẽ bổ sung:

```text
data/03_unified/vocab_master.csv
data/03_unified/chunks_master.csv
data/03_unified/weak_words.csv
data/03_unified/review_log.csv
data/processed_files.csv
```

## 5. Vocabulary schema hiện tại

Schema hiện tại trong repo:

```csv
id,wort,wortart,formen,bedeutung,beispiel,uebersetzung,thema,lerndatum,level,quelle,source_type,tags,notes
```

Có thể mở rộng sau với:

```csv
status,last_seen,audio_note,chunk_refs,anki_exported,lingq_status
```

## 6. Chunk-first learning

Hệ thống phải ưu tiên chunk, không chỉ từng từ.

Ví dụ:

| Word | Chunk cần học |
|---|---|
| abhängen | Es hängt davon ab, ob ... |
| unterstützen | sich gegenseitig unterstützen |
| kümmern | sich um jemanden kümmern |
| Meinung | Ich bin der Meinung, dass ... |

## 7. Tutor Manager

AI chuẩn bị giáo án trước buổi học với gia sư và tạo bài tập sau buổi học.

### Trước buổi học

AI đọc:

- vocab_master.csv;
- chunks_master.csv;
- weak_words.csv;
- review_log.csv;
- tutor/student_profile.md;

Sau đó tạo:

```text
tutor/lesson_plans/YYYY-MM-DD.md
```

Nội dung gồm:

- mục tiêu buổi học;
- chủ đề DTZ;
- từ/chunk yếu cần kích hoạt;
- câu hỏi speaking;
- roleplay;
- lỗi cần gia sư chú ý;
- output mong muốn cuối buổi.

### Sau buổi học

AI đọc:

```text
tutor/tutor_notes/YYYY-MM-DD.md
```

Sau đó tạo:

```text
tutor/homework/YYYY-MM-DD.md
```

bao gồm:

- speaking drill;
- writing drill;
- listening drill;
- Anki card suggestions;
- review list ngày hôm sau.

## 8. LingQ workflow

LingQ không thay thế German Brain.

```text
LingQ = exposure engine
German Brain = semantic memory
Anki = review engine
AI = production/retrieval engine
```

Cần triển khai bán tự động trước:

1. Chuẩn bị text/audio local.
2. AI clean text và chia lesson nhỏ.
3. Người học import vào LingQ.
4. Xuất vocabulary/status từ LingQ nếu có.
5. AI sync status vào German Brain.
6. AI extract chunks riêng vì LingQ yếu ở chunk.

## 9. Roadmap

### Phase 1 – Folder + prompt + manual batch

- Tạo input/queue/archive.
- Tạo tutor folders.
- Tạo prompts cho batch extraction và tutor manager.
- Dùng Claude/Cursor xử lý thủ công theo batch nhỏ.

### Phase 2 – Scripts local

- scan input folders.
- ghi processed_files.csv.
- move file vào queue/archive.
- chuẩn hóa output CSV.
- export Anki CSV.

### Phase 3 – LingQ sync

- tìm hiểu LingQ API/export.
- map LingQ status 1–4 vào German Brain.
- tạo lesson-ready package từ local source.

### Phase 4 – Semantic search

- embedding.
- Qdrant/Chroma.
- AI hỏi đáp toàn bộ knowledge.

## 10. Quy tắc triển khai

- Không phá schema cũ nếu chưa cần.
- Thêm tài liệu trước, code sau.
- Mọi output nên ở CSV/Markdown để AI đọc được.
- Source raw sau xử lý phải move sang archive.
- Không ghi thẳng vào master nếu chưa có bước review.
