# 09 — Claude Code Task Spec

## Goal

Define the operational tasks Claude Code must support for the DTZ Training OS.

Claude Code should automate:

- ingestion
- extraction
- classification
- session generation
- personalization
- Anki export
- report generation

---

# Core Tasks

## 1. ingest_new_test

Purpose:

- validate folder structure
- create/update META.md
- register new source

Example:

```text
/ingest DTZ_001
```

---

## 2. extract_pdf_text

Purpose:

- extract raw text
- create clean text
- mark OCR issues

Outputs:

```text
raw_text.md
clean_text.md
validation_report.md
```

---

## 3. classify_sections

Purpose:

Split content into:

```text
Hören
Lesen
Schreiben
Sprechen
Answer Key
Transcript
Rubric
```

Outputs:

```text
sections.md
sections.json
```

---

## 4. extract_speaking_tasks

Purpose:

Generate:

- roleplay tasks
- required chunks
- target grammar
- retry drills

---

## 5. extract_writing_templates

Purpose:

Generate:

- email skeletons
- opening chunks
- request chunks
- closing chunks
- rewrite drills

---

## 6. extract_listening_traps

Purpose:

Identify:

- correction traps
- negation traps
- date changes
- number confusion

---

## 7. extract_reading_keywords

Purpose:

Generate:

- scan keywords
- distractor keywords
- reading strategies

---

## 8. update_chunk_database

Purpose:

Store reusable chunks with:

```text
meaning
use case
register
frequency
example
```

---

## 9. generate_session_context_pack

Purpose:

Create personalized 90-minute session plans.

Input:

- student profile
- previous errors
- target skill
- tutor focus

Outputs:

```text
context pack
live worksheet
retry drills
```

---

## 10. generate_live_worksheet

Purpose:

Create the editable worksheet used during live sessions.

Sections:

```text
Goal
Chunks
Grammar
Tasks
Corrections
Mistakes
Retry Tasks
Homework
```

---

## 11. process_post_session_notes

Purpose:

Convert tutor notes + worksheet into structured student data.

Outputs:

```text
error report
strong chunks
weak grammar
next-session priorities
```

---

## 12. update_student_profile

Purpose:

Update:

- fluency
- recurring mistakes
- listening weaknesses
- reading speed
- confidence

---

## 13. generate_anki_csv

Purpose:

Export:

- recurring errors
- high-frequency chunks
- correction patterns
- survival phrases

CSV format:

```text
Front,Back,Tag
```

---

## 14. generate_next_session_plan

Purpose:

Build next session using:

```text
70% weak-point repair
20% new DTZ patterns
10% confidence building
```
