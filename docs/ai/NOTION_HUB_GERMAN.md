# 🇩🇪 Học Tiếng Đức — Notion Hub (mirror local)

> **Source of truth gốc:** https://www.notion.so/363164f52c928116b87ded321f043a42
> **Fetched:** 2026-05-17 — chỉ mirror trang hub, **không** dump database / sub-pages (theo yêu cầu).
> Khi cần data động (từ vựng, lỗi, session, grammar) → vào Notion trực tiếp.

---

Đây là **second brain** cho việc học tiếng Đức. Mọi data động (từ vựng, lỗi, session, grammar) lưu trong các database con. Mọi prompt + instruction lưu trong các page con.

> **Quy tắc vàng:** Mỗi session chat với Claude → mở đầu bằng 1 role prompt từ page "🎭 Role Prompts". Kết thúc session → Claude tự update các database tương ứng.

## 📚 Cấu trúc

### Databases (data sống)

- **📖 Vocabulary** — từ vựng có article, plural, conjugation, mastery level
  - https://www.notion.so/e9275bc9c40a43069897595b5fd7d52b
  - data-source: `collection://aa7e99ac-ce37-4db0-8254-9b22aba52c0b`
- **📐 Grammar Points** — quy tắc ngữ pháp đã học và mức thành thạo
  - https://www.notion.so/4d0457bd78f94308a5fb1eeb9ea86342
  - data-source: `collection://0f1a62d5-857a-473b-a346-f3bda8ffd07b`
- **📝 Sessions Log** — lịch sử session: vai trò, focus, lỗi, từ mới
  - https://www.notion.so/be59d7f80a114119bcf5f7ba1f71bebb
  - data-source: `collection://93436a74-d90c-4427-af1d-d0dd8ff2d1dc`
- **❌ Mistakes Log** — kho lỗi sai để Claude tự nhận diện pattern lặp
  - https://www.notion.so/ca3866bb7e094fad894cb2cc272e666f
  - data-source: `collection://0dab928e-4cd4-4c81-82db-ce4abae853f2`

### Pages (instruction tĩnh)

- **🎭 Role Prompts** — câu mở đầu paste-ready cho từng loại session
  - https://www.notion.so/363164f52c9281f2af01ecfa3f3e0791
- **🧠 Tutor Instructions** — system prompt định hình "gia sư"
  - https://www.notion.so/363164f52c9281d2b498f33112c5841d
- **🎯 Current Focus** — mục tiêu tuần này (đổi mỗi tuần)
  - https://www.notion.so/363164f52c9281f88762e9a91cbf7e04
- **🔌 Sync Bridge** — note về middleware PHP/MySQL
  - https://www.notion.so/363164f52c92817b9b39e4660222b2bd

## 🚀 Quick start

1. Mở chat mới với Claude (cùng account, Notion đã connect)
2. Vào page **🎭 Role Prompts**, copy prompt phù hợp
3. Paste vào Claude → Claude tự fetch Notion → vào vai
4. Cuối session bảo Claude: *"update Notion theo session vừa rồi"*

## ⚠️ Lưu ý quan trọng

- **Mỗi vai = mỗi chat riêng.** Đừng pha trộn (vừa luyện đàm thoại vừa drill grammar trong cùng 1 chat → Claude bối rối).
- **Trước khi đóng chat**: luôn nhắc Claude update Notion. Không update = data bay mất.
- **Memory của Project Claude chỉ là phụ.** Notion mới là nguồn sự thật (source of truth).

---

## 📝 Notes for local workflow

- File này là **mirror đọc** — sửa thì sửa trên Notion, không sửa local rồi expect sync ngược.
- Nếu cần kéo thêm sub-pages (Role Prompts / Tutor Instructions / Current Focus / Sync Bridge) hoặc dump rows từ database, bảo Claude: *"fetch thêm trang X xuống docs/ai/notion/"*.
- Brainstorm hệ thống AI knowledge → xem `BRAINSTORM_AI_KNOWLEDGE.md` cùng folder.
