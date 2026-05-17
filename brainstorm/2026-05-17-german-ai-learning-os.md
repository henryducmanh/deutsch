# German AI Learning OS – Brainstorm 2026-05-17

## 1. Bối cảnh

Người học đã từng đạt A2 và từng gần đạt B1 DTZ nhưng gặp vấn đề lớn: học rất nhiều, giải gần 100 bộ đề, học gia sư nhiều buổi, nhưng học trước quên sau. Khi đọc thì nhận ra từ, nhưng khi nghe thì không bắt được ngay cả từ dễ; khi nói/viết thì không bật được câu và chunk.

Vấn đề chính không phải thiếu input, mà là thiếu một hệ thống ghi nhớ dài hạn và kích hoạt lại kiến thức.

## 2. Mục tiêu lớn

Xây dựng một hệ thống giống kết hợp giữa:

- LingQ: đọc/nghe nhiều theo ngữ cảnh.
- Anki: spaced repetition.
- NotebookLM: hỏi đáp trên toàn bộ knowledge.
- Internal wiki: lưu quyết định, nguồn học, tiến độ.
- AI tutor manager: chuẩn bị bài cho gia sư và tạo bài tập sau buổi học.

Mục tiêu cuối cùng:

- AI nhớ toàn bộ docs, từ vựng, chunk, lỗi sai, bài học và tiến độ.
- AI tìm theo ý nghĩa, không chỉ theo keyword.
- AI giúp học nhanh, nhớ nhanh, nói/viết tốt hơn.
- AI tạo bài học, bài tập, speaking drill, listening drill, Anki export.
- ChatGPT, Claude, Cursor và tool local đều dùng chung một bộ não dữ liệu.

## 3. Nguyên tắc kiến trúc

GitHub là source of truth.

Notion có thể làm UI quản lý, nhưng không nên là nguồn dữ liệu chính. CSV/Markdown trong GitHub giúp AI, Cursor, Claude và các script đọc tốt hơn.

LingQ là input engine, không phải trung tâm dữ liệu.

Anki là review engine, không phải toàn bộ hệ thống học.

German Brain là memory system trung tâm.

## 4. Các tầng hệ thống

```text
Input sources
(images, audio, text, pdf, LingQ, tutor notes)
        ↓
Local batch pipeline
(OCR, transcription, cleanup, extraction)
        ↓
German Brain data
(vocab_master, chunks_master, weak words, source log)
        ↓
Output engines
(Anki, speaking drills, listening drills, tutor lesson plan)
        ↓
AI interfaces
(ChatGPT, Claude, Cursor, future local app)
```

## 5. Vấn đề học cần giải quyết

### 5.1 Passive recognition

Người học đọc thì hiểu nhưng không nghe/nói/viết được. Đây là khoảng cách giữa recognition và production.

Cần chuyển từ:

```text
đọc thấy thì hiểu
```

sang:

```text
nghe bắt được → nói bật ra được → viết tạo được câu
```

### 5.2 Audio-linked memory

Từ vựng không chỉ có chữ, mà cần gắn với âm thanh, câu ví dụ, ngữ cảnh và chunk.

### 5.3 Chunk-first learning

DTZ B1 cần chunk và Redemittel hơn là học từng từ rời rạc.

Ví dụ:

```text
abhängen → Es hängt davon ab, ob ...
unterstützen → sich gegenseitig unterstützen
Meinung → Ich bin der Meinung, dass ...
```

## 6. LingQ integration idea

LingQ dùng để đọc/nghe và tracking exposure. Nhưng LingQ yếu ở học cụm/chunk và production.

Hướng dùng:

```text
LingQ = exposure engine
German Brain = semantic memory
Anki = review engine
AI = production/retrieval engine
```

Cần sync hoặc bán tự động:

- import bài học vào LingQ từ local text/audio.
- export vocabulary/status từ LingQ.
- map LingQ status 1–4 vào German Brain.
- extract chunk riêng vì LingQ chủ yếu track single words.

## 7. Tutor manager idea

AI đóng vai trò learning manager cho gia sư.

Trước buổi học:

- đọc vocab/chunks/weak words/review log.
- tạo lesson plan 60–90 phút.
- chọn chủ đề DTZ.
- chọn từ/chunk yếu cần kích hoạt.
- tạo roleplay, câu hỏi speaking, writing mini task.

Sau buổi học:

- đọc tutor notes.
- bóc từ mới, lỗi nói, lỗi nghe.
- cập nhật German Brain.
- tạo homework.
- tạo Anki export và speaking/listening drills.

## 8. Local batch ingestion idea

Người học chỉ cần cung cấp nguồn vào đúng thư mục:

```text
input/images
input/audio
input/text
input/pdf
```

AI/Claude/Cursor/script sẽ xử lý hàng loạt:

```text
scan input → queue → OCR/transcribe → extract vocab/chunks → dedupe → update output → archive source
```

Sau khi xử lý, source được move sang archive để tránh xử lý lặp.

## 9. Future semantic layer

Sau khi dữ liệu đủ lớn, thêm:

- Qdrant hoặc Chroma.
- embedding pipeline.
- semantic search.
- chat interface hỏi đáp toàn bộ knowledge.

Nhưng giai đoạn đầu chưa cần vector DB. Trước tiên cần pipeline ổn định và dữ liệu sạch.

## 10. Quyết định hiện tại

Triển khai trước trong GitHub repo `app-deutsch`:

- tạo cấu trúc input/queue/archive.
- tạo tutor manager folders.
- tạo prompts cho Claude/Cursor.
- tạo docs tổng hợp architecture.
- tạo brainstorm và handoff cho Claude đọc tiếp.

Chưa triển khai API/Qdrant ngay.
