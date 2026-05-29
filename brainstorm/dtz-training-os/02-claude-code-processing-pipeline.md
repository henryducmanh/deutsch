# 02 — Claude Code Processing Pipeline

## Goal

Claude Code converts raw DTZ sources into structured training assets.

```text
PDF / DOCX / GDoc export / transcript
→ clean text
→ classified sections
→ patterns, chunks, traps, templates
→ session-ready knowledge base
```

Claude Code should not only summarize documents. It must create reusable exam-training units.

---

## Supported Inputs

- official BAMF/g.a.s.t. framework
- DTZ model tests
- answer keys
- listening transcripts
- audio metadata
- writing samples
- speaking prompts
- tutor notes
- post-session worksheets

---

## Pipeline

```text
1. Intake
2. Validation
3. Text extraction
4. Section classification
5. Skill-specific extraction
6. Knowledge base update
7. QA report
8. Session asset generation
```

---

## Step 1 — Intake

Input folder:

```text
RAW/MODEL_TESTS/DTZ_001/
├── META.md
├── exam.pdf
├── answer_key.pdf
├── transcript.pdf
└── audio/
```

Checklist:

```text
[ ] folder follows DTZ_XXX
[ ] META.md exists
[ ] source type is clear
[ ] exam file exists
[ ] answer key exists if available
[ ] transcript exists if available
[ ] audio exists if needed
```

If missing, create/update `META.md`.

---

## Step 2 — Validation

Create:

```text
extracted/validation_report.md
```

Quality labels:

```text
high / medium / low / OCR_NEEDED / MANUAL_REVIEW
```

Common issues:

- poor scan
- missing answer key
- missing transcript
- unclear source
- duplicate file

---

## Step 3 — Text Extraction

Create:

```text
extracted/raw_text.md
extracted/clean_text.md
```

Rules:

- preserve task wording
- do not rewrite exam text creatively
- mark uncertain OCR
- keep page/source references when possible

---

## Step 4 — Section Classification

Classify content into:

```text
Hören / Lesen / Schreiben / Sprechen / Answer Key / Transcript / Rubric / Unknown
```

Create:

```text
extracted/sections.md
extracted/sections.json
```

---

## Step 5 — Skill-Specific Extraction

Create these files:

```text
extracted/speaking_patterns.md
extracted/writing_templates.md
extracted/listening_traps.md
extracted/reading_keywords.md
extracted/chunk_list.md
extracted/grammar_targets.md
```

Each item must include:

```text
Topic:
Skill:
Task Type:
Training Use:
Required Chunks:
Target Grammar:
Likely Mistakes:
Retry Drill:
Source:
Confidence:
```

---

## Step 6 — Knowledge Base Update

Move normalized knowledge into:

```text
PROCESSED/
├── CHUNKS/
├── SPEAKING_PATTERNS/
├── WRITING_TEMPLATES/
├── LISTENING_TRAPS/
├── READING_KEYWORDS/
├── GRAMMAR_TARGETS/
└── SIMULATION_BANK/
```

Every processed item needs trace:

```text
Source Test:
Source File:
Source Page:
Source Section:
Confidence:
```

---

## Step 7 — QA Report

Create:

```text
extracted/qa_report.md
```

Checklist:

```text
[ ] all sections classified
[ ] writing tasks extracted
[ ] speaking tasks extracted
[ ] listening traps identified
[ ] reading keywords identified
[ ] chunks extracted
[ ] uncertain items marked
```

---

## Step 8 — Session Asset Generation

Generate:

- context pack
- live worksheet
- tutor instructions
- student prework
- post-session report template
- Anki CSV

Store in:

```text
SESSIONS/YYYY-MM-DD_Session_XXX/
```

---

## Operating Rule

```text
RAW = immutable source
PROCESSED = generated knowledge
SESSIONS = usage and feedback
STUDENT = personalization memory
```
