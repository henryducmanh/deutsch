# MISTAKES_LOG — Pattern lỗi cross-session

> Lỗi grammar / vocab / pronunciation cùng pattern lặp lại → ghi đây để Tutor + Speaking/Listening Coach tránh nhắc lại. Vai **Mistake Auditor** append.
>
> Pattern lặp 3+ lần → bump `pattern_count` + add từ liên quan vào `data/weak_words.csv`.

---

<!-- Append entry mới ở dưới đây. Template:

## YYYY-MM-DD — <category: Grammar/Vocab/Pronunciation> — <rule short>

- **Mistake:** <user wrote/said>
- **Correct:** <đúng>
- **Rule:** <quy tắc 1-2 câu>
- **Example đúng thêm:**
  - <example 1>
  - <example 2>
- **Pattern count:** N (lần thứ N lặp)
- **Source:** <SESSION_<date>.md hoặc homework file>
- **Related words:** <list — cross-link weak_words.csv>

-->

(chưa có entry — sẽ append từ vai Mistake Auditor)

---

## Top patterns (auto-summary — vai Tutor cập nhật mỗi tuần)

| Pattern | Count | Last seen | Category |
|---|---|---|---|
| _(chưa có)_ | - | - | - |

---

**Last updated:** 2026-05-18 (initial scaffold).

## 2026-05-24 — Grammar — Mạo từ ở Akkusativ (quên)

- **Mistake:** "Ich muss Tish im Restaurant", "Ich finde ein Heimkino-System" (đúng ein nhưng cần check 5 câu khác)
- **Correct:** "Ich möchte **einen** Tisch...", "...mit Blick auf **die** Stadt"
- **Rule:** Sau động từ chuyển tiếp (suchen, kaufen, möchten, sehen, finden...) → tân ngữ ở Akkusativ → mạo từ phải có và phải đổi: der→den (đực), die→die (cái không đổi), das→das (trung không đổi). Mạo từ không xác định: ein→einen (đực), eine→eine, ein→ein.
- **Example đúng thêm:**
  - *Ich suche **einen** Job.* (der Job → einen)
  - *Ich kaufe **eine** Tasse Kaffee.* (die Tasse → eine)
  - *Ich brauche **ein** Buch.* (das Buch → ein, không đổi)
- **Pattern count:** 1 (Active Retrieval Lesen 1.1 Lượt 1, 3/5 câu mắc)
- **Source:** SESSION_2026-05-24.md (Tutor — Active Retrieval Lesen 1.1)

## 2026-05-24 — Grammar — Tiếng Anh lọt vào câu Đức (production fallback)

- **Mistake:** "Welche Stock has Mobile Zubehör?" — "has" và "Mobile" là tiếng Anh
- **Correct:** "Auf welchem Stock gibt es Handyzubehör?"
- **Rule:** Khi não retrieve không kịp tiếng Đức → fallback sang tiếng Anh. Đây là biểu hiện rõ của "đứt gãy recognition vs production" (Chat 7 trong Notion). Cách fix duy nhất: tăng tần suất Active Retrieval. Mỗi từ Anh lọt vào → mistake_count tăng 2 (không phải 1) vì là tín hiệu nặng hơn.
- **Example đúng thêm:**
  - "có gì ở đâu" → *es gibt + Akkusativ* (KHÔNG dùng "haben/hat" cho tồn tại)
  - "điện thoại di động" → *das Handy* (KHÔNG dùng "Mobile")
  - "máy tính" → *der Computer / der Rechner* (Computer đã thành Đức hợp lệ)
- **Pattern count:** 1
- **Source:** SESSION_2026-05-24.md

## 2026-05-24 — Grammar — Trật tự câu hỏi yes/no (Ja/Nein-Frage)

- **Mistake:** "Ist teuer dieser Lautsprecher?"
- **Correct:** "Ist dieser Lautsprecher teuer?"
- **Rule:** Câu hỏi yes/no theo khung **[Động từ] [Chủ ngữ] [Bổ ngữ/tính từ]?** — tính từ ở cuối, KHÔNG đứng ngay sau động từ. Lỗi gốc: copy thứ tự tiếng Việt "đắt không?" → đảo tính từ lên.
- **Example đúng thêm:**
  - *Ist **die Suppe** kalt?*
  - *Ist **das Auto** schnell?*
  - *Ist **der Mantel** warm?*
- **Pattern count:** 1
- **Source:** SESSION_2026-05-24.md

## 2026-05-24 — Vocab — finden vs suchen confusion

- **Mistake:** "Ich finde ein Heimkino-System." (ý: tôi đang TÌM)
- **Correct:** "Ich **suche** ein Heimkino-System."
- **Rule:** **suchen** = tìm KIẾM (chưa thấy, đang quá trình); **finden** = tìm THẤY (đã thấy/tìm ra). Tiếng Việt "tìm" mơ hồ — gộp cả 2 → não user copy sang "finden" (gặp đầu tiên trong từ điển). Quy tắc khắc: ngữ cảnh "đang đi tìm" → **suchen**; ngữ cảnh "đã tìm được rồi" → Perfekt **gefunden**.
- **Example đúng thêm:**
  - *Ich **suche** eine Wohnung.* (đang tìm)
  - *Ich habe eine Wohnung **gefunden**.* (đã tìm được)
  - *Wo **finde** ich die Toilette?* (Tôi tìm thấy nhà vệ sinh ở đâu — kiểu phổ biến nhưng nghĩa thực vẫn là "tìm thấy")
- **Pattern count:** 1
- **Source:** SESSION_2026-05-24.md

## 2026-05-26 — Chính tả — Đuôi -t bị rụng (jetzt / ist)

- **Mistake:** `jetz` (→ `jetzt`), `is` (→ `ist`)
- **Correct:** `jetzt`, `ist`
- **Rule:** Hai từ cực kỳ thường gặp bị rụng đuôi `-t` khi gõ nhanh. `jetzt` (bây giờ) và `ist` (ngôi 3 số ít của `sein`) đều kết thúc bằng `-t` — không được bỏ. Lỗi này thường do gõ nhanh / không review.
- **Example đúng thêm:**
  - *Es **ist** jetzt 10 Uhr.*
  - *Ich komme **jetzt**.*
  - *Das Hemd **ist** reduziert.*
- **Pattern count:** 1
- **Source:** SESSION_2026-05-26.md

## 2026-05-26 — Grammar — Mạo từ bị thiếu/sai (Akkusativ) — LẶPLẠI

- **Mistake:** `Schnäppchen` (thiếu `ein`), `Geschirr` (thiếu `das`), `die Kette` (→ `eine goldene Kette`)
- **Correct:** `ein Schnäppchen`, `das Geschirr`, `eine goldene Kette`
- **Rule:** Tân ngữ trong câu tiếng Đức hầu như LUÔN cần mạo từ — bỏ mạo từ nghe như robot. Ngoài ra khi ngữ cảnh là "một cái nào đó chưa xác định" → dùng mạo từ bất định (`ein/eine/ein`), KHÔNG dùng mạo từ xác định (`der/die/das`). `Geschirr` uncountable → `das Geschirr`.
- **Example đúng thêm:**
  - *Ich suche **eine** goldene Kette.*
  - *Ich wasche **das** Geschirr.*
  - *Ich habe **ein** Schnäppchen gemacht.*
- **Pattern count:** 2 (lặp từ 2026-05-24)
- **Source:** SESSION_2026-05-26.md
- **Related words:** Schnäppchen, Geschirr, Kette

## 2026-05-26 — Vocab — Bịa động từ tiếng Anh ("washare") — LẶPLẠI

- **Mistake:** `washare ich Geschirr` — "washare" không tồn tại trong tiếng Đức
- **Correct:** `ich wasche das Geschirr` (hoặc `ich wasche ... ab` / `ich spüle`)
- **Rule:** Khi não không retrieve được động từ Đức → fallback bịa từ lai Anh-Đức (`washare`, tương tự `has` hôm 2026-05-24). Tín hiệu: "waschen" chưa vào long-term production. Cần thêm vào Anki với câu ví dụ rửa bát. Pattern đếm nặng hơn (×2 tín hiệu).
- **Example đúng thêm:**
  - *Kannst du bitte **das Geschirr abwaschen**?*
  - *Ich **spüle** nach dem Essen.*
- **Pattern count:** 2 (lặp pattern "tiếng Anh lọt vào" từ 2026-05-24)
- **Source:** SESSION_2026-05-26.md

## 2026-05-24 — Văn hóa/Grammar — du vs Sie với người lạ

- **Mistake:** "Verkaufts du Verlängerungskabel?" (dùng "du" với nhân viên cửa hàng)
- **Correct:** "Verkaufen **Sie** ein Verlängerungskabel?"
- **Rule:** BẮT BUỘC dùng **Sie** (lịch sự) với: nhân viên cửa hàng, người lạ, người lớn tuổi, cán bộ Behörde, bác sĩ, sếp. **du** chỉ với: bạn bè, gia đình, trẻ em, đồng nghiệp thân được mời "duzen". Thi DTZ Sprechen lẫn du/Sie sai context → trừ điểm.
- **Example đúng thêm:**
  - Vào cửa hàng: *"Entschuldigung, **haben Sie** ...?"*
  - Hỏi đường: *"Entschuldigung, **können Sie** mir helfen?"*
  - Gặp bác sĩ: *"Guten Tag, ich **habe** Kopfschmerzen."*
- **Pattern count:** 1
- **Source:** SESSION_2026-05-24.md
