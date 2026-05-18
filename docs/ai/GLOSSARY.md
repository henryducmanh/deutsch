# GLOSSARY — Thuật ngữ học tiếng Đức

> Term + viết tắt cần biết để AI và tôi hiểu nhau. Append-only khi gặp term mới (vai Tutor / Mistake Auditor).

---

## Chứng chỉ + level

| Term | Viết tắt | Nghĩa |
|---|---|---|
| Deutsch-Test für Zuwanderer | DTZ | Test B1 cho người định cư Đức (Đọc + Nghe + Viết + Nói) |
| Goethe-Zertifikat | Goethe B1/B2/C1 | Chứng chỉ chuẩn của Goethe-Institut |
| TestDaF | TestDaF | Test cho sinh viên đại học Đức |
| Common European Framework of Reference | CEFR | Khung tham chiếu A1 → C2 |
| A1 / A2 | A1 / A2 | Beginner / Elementary |
| B1 / B2 | B1 / B2 | Intermediate / Upper-Intermediate |
| C1 / C2 | C1 / C2 | Advanced / Mastery |

---

## Grammar terms (Đức)

| Term DE | EN | Ghi chú |
|---|---|---|
| Substantiv | noun | Có 3 Artikel: der (mas), die (fem), das (neu) |
| Verb | verb | Conjugation theo Person + Tempus + Modus |
| Adjektiv | adjective | Đuôi đổi theo Kasus + Genus + Artikel |
| Adverb | adverb | Không đổi đuôi |
| Artikel | article | bestimmt (der/die/das) vs unbestimmt (ein/eine) |
| Kasus | case | Nominativ / Akkusativ / Dativ / Genitiv |
| Genus | gender | Maskulin / Feminin / Neutrum |
| Numerus | number | Singular / Plural |
| Tempus | tense | Präsens / Präteritum / Perfekt / Plusquamperfekt / Futur I/II |
| Modus | mood | Indikativ / Konjunktiv I/II / Imperativ |
| Konjunktiv II | subjunctive II | "würde + Inf" hoặc "wäre/hätte" — hypothesis, polite request |
| Passiv | passive | "werden + Partizip II" |
| Nebensatz | subordinate clause | Verb đẩy về cuối: weil, dass, ob, wenn, ... |
| Hauptsatz | main clause | Verb ở vị trí 2 (default) |
| Trennbares Verb | separable verb | aufstehen → ich stehe auf |
| Partizip II | past participle | gemacht, gegangen, ... |
| Redemittel | set phrases | Cụm cố định: "Meiner Meinung nach", "Ich bin der Ansicht, dass" |
| Konnektor | connector | weil, denn, deshalb, trotzdem, ... |

---

## Skill areas

| Skill | DE term | Note |
|---|---|---|
| Reading | Lesen | DTZ: 4 phần Lesen, 25 phút |
| Listening | Hören | DTZ: 4 phần Hören, 25 phút |
| Writing | Schreiben | DTZ: 1 bài viết, 30 phút |
| Speaking | Sprechen | DTZ: 3 phần Sprechen, ~15 phút |

---

## DTZ topic clusters (chủ đề B1)

| Cluster | Sub-topic |
|---|---|
| Arbeit | Bewerbung, Vorstellungsgespräch, Arbeitsplatz, Kollegen, Gehalt |
| Wohnen | Wohnungssuche, Mietvertrag, Nachbarn, Reparatur, Hausordnung |
| Gesundheit | Arzt, Krankenkasse, Krankheit, Apotheke, Notfall |
| Behörde | Bürgeramt, Anmeldung, Aufenthalt, Familienzusammenführung |
| Familie | Eltern, Kinder, Verwandte, Erziehung, Beziehung |
| Freizeit | Hobbys, Sport, Reisen, Kultur, Veranstaltung |
| Bildung | Schule, Ausbildung, Studium, Kurs, Zeugnis |
| Einkauf | Supermarkt, Kleidung, Reklamation, Online-Shopping |
| Verkehr | ÖPNV, Auto, Fahrrad, Führerschein, Stau |
| Wetter | Klima, Jahreszeiten, Wetterbericht |

---

## Tool + system term

| Term | Nghĩa trong repo này |
|---|---|
| vocab_master | `data/03_unified/vocab_master.csv` — source of truth từ vựng, 16 cột |
| chunks_master | `data/chunks_master.csv` — Redemittel + cụm idiom |
| weak_words | `data/weak_words.csv` — từ user hay quên / sai |
| sources_master | `data/sources_master.csv` — index input source |
| SESSION_<date>.md | `docs/ai/SESSION_<YYYY-MM-DD>.md` — log buổi tutor |
| MISTAKES_LOG | `docs/ai/MISTAKES_LOG.md` — pattern lỗi cross-session |
| queue → archive | flow: `input/<sub>/` → user move sang `queue/` → AI process → AI move sang `archive/<date>/` |
| Anki export | `output/anki/<date>_<source>.csv` — 4 cột Front/Back/Tags/Note |
| Easy German | YouTube channel + podcast B1-B2 |
| Slow German | Podcast A2-B1 (slow speed, clear pronunciation) |
| Deutsche Welle (DW) | Đức state media — có series Nicos Weg, Deutsch lernen |
| Cowork | Claude desktop app, daily driver |
| Claude Code | CLI tool, batch process |
| Cursor | IDE, review diff trước commit |
| LingQ | Reading app track known words |
| Anki | Flashcard SRS app |

---

## Viết tắt nội bộ

| Viết tắt | Đầy đủ | Khi dùng |
|---|---|---|
| KI-<date>-<NNN> | knowledge_index ID | knowledge-os/data/knowledge_index.csv |
| VM | vocab_master | vocab_master.csv |
| WW | weak_words | weak_words.csv |
| ML | MISTAKES_LOG | MISTAKES_LOG.md |
| SS | SESSION | SESSION_<date>.md |
| LP | Lesson Plan | tutor/lesson_plans/ |
| HW | Homework | tutor/homework/ |

---

**Last updated:** 2026-05-18 (initial scaffold).
