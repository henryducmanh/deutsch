# 03 — Knowledge Base Structure

## Goal

The knowledge base is the central brain of the DTZ Training OS.

It stores:

- speaking patterns
- writing templates
- listening traps
- reading keywords
- grammar targets
- reusable chunks
- simulation tasks
- student-specific weaknesses

The system should optimize retrieval for:

- session generation
- tutor guidance
- retry drills
- personalization

---

# Root Structure

```text
PROCESSED/
├── CHUNKS/
├── SPEAKING_PATTERNS/
├── WRITING_TEMPLATES/
├── LISTENING_TRAPS/
├── READING_KEYWORDS/
├── GRAMMAR_TARGETS/
├── SIMULATION_BANK/
└── EXAM_PATTERN_NOTES/
```

---

# CHUNKS

Structure:

```text
CHUNKS/
├── OPINION/
├── AGREEMENT/
├── DISAGREEMENT/
├── REQUEST/
├── CLARIFICATION/
├── EMERGENCY/
├── FORMAL_WRITING/
└── CONVERSATION_CONTROL/
```

Chunk metadata:

```text
Chunk:
Meaning:
Skill:
Register:
Use Case:
Grammar Pattern:
Frequency:
Difficulty:
Example:
Source:
Anki Ready:
```

---

# SPEAKING PATTERNS

Structure:

```text
SPEAKING_PATTERNS/
├── SELBSTVORSTELLUNG/
├── BILDBESCHREIBUNG/
├── PLANEN/
├── MEINUNG/
├── ALLTAG/
├── ARBEIT/
├── ARZT/
├── WOHNUNG/
└── SCHULE/
```

Each item:

```text
Topic:
Exam Part:
Situation:
Student Role:
Tutor Role:
Task Mission:
Required Chunks:
Target Grammar:
Survival Phrases:
Likely Mistakes:
Retry Drill:
Source:
```

---

# WRITING TEMPLATES

Structure:

```text
WRITING_TEMPLATES/
├── BESCHWERDE/
├── ENTSCHULDIGUNG/
├── EINLADUNG/
├── ANFRAGE/
├── TERMIN/
├── SCHULE/
└── ARBEIT/
```

Each item:

```text
Task Type:
Formal/Informal:
Situation:
Required Points:
Skeleton:
Opening Chunks:
Problem Chunks:
Request Chunks:
Closing Chunks:
Common Mistakes:
Timed Drill:
```

---

# LISTENING TRAPS

Structure:

```text
LISTENING_TRAPS/
├── DATE_CHANGE/
├── TIME_CHANGE/
├── NEGATION/
├── CORRECTION/
├── SIMILAR_NUMBERS/
├── PLACE_CONFUSION/
├── PRICE_CONFUSION/
└── FAST_ANSWER/
```

Each item:

```text
Topic:
Trap Type:
Trap Sentence:
Correct Information:
Prediction Vocabulary:
Shadowing Segment:
Retry Drill:
Source:
```

---

# READING KEYWORDS

Structure:

```text
READING_KEYWORDS/
├── ANZEIGEN/
├── EMAIL/
├── FORUM/
├── NOTICE/
├── INVITATION/
└── INFORMATION/
```

Each item:

```text
Text Type:
Question Type:
Target Keyword:
Synonyms:
Distractors:
Correct Evidence:
Scanning Strategy:
Time Limit:
```

---

# GRAMMAR TARGETS

Only include high ROI grammar.

```text
- weil
- deshalb/deswegen
- modal verbs
- Perfekt
- Konjunktiv II requests
- separable verbs
- Akkusativ/Dativ chunks
```

Each item:

```text
Grammar:
Rule:
Exam Use:
Common Mistakes:
Correction Pattern:
Retry Drill:
```

---

# SIMULATION BANK

Contains reusable session tasks.

Structure:

```text
SIMULATION_BANK/
├── SPRECHEN/
├── SCHREIBEN/
├── HÖREN/
└── LESEN/
```

Each simulation:

```text
Session Goal:
Skill:
Difficulty:
Topic:
Time Limit:
Required Chunks:
Target Grammar:
Likely Errors:
Tutor Instructions:
Retry Plan:
```
