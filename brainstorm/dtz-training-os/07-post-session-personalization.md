# 07 — Post-Session Personalization

## Goal

Every session must improve the next session.

The system should:

```text
capture → analyze → personalize → retry
```

The student profile becomes smarter over time.

---

# Required Session Outputs

Minimum:

```text
- worksheet
- tutor notes
- error list
- retry drills
- next-session suggestions
```

Preferred:

```text
- audio recording
- transcript
- speaking timestamps
```

---

# Student Profile Structure

```text
STUDENT/
├── OVERVIEW/
├── ERROR_DATABASE/
├── WEAK_GRAMMAR/
├── STRONG_CHUNKS/
├── SPEAKING_FLUENCY/
├── WRITING_ISSUES/
├── LISTENING_TRAPS/
├── READING_SPEED/
└── NEXT_PRIORITIES/
```

---

# Error Database

Each error:

```text
Date:
Skill:
Context:
Student Error:
Correct Version:
Error Type:
Frequency:
Severity:
Retry Drill:
Next Review Date:
Resolved:
```

Example:

```text
Student Error:
Ich kann nicht kommen, weil ich muss arbeiten.

Correct Version:
Ich kann nicht kommen, weil ich arbeiten muss.

Error Type:
weil sentence order
```

---

# Strong Chunk Tracking

Store chunks the student uses successfully.

Examples:

```text
- Das kommt darauf an.
- Meiner Meinung nach ...
- Könnten Sie das bitte wiederholen?
```

Purpose:

- build confidence
- reinforce automatic output

---

# Weak Grammar Tracking

Track only high ROI grammar.

Examples:

```text
- weil sentence order
- modal verb placement
- separable verbs
- Perfekt forms
- formal requests
```

Avoid tracking rare grammar.

---

# Listening Trap Tracking

Track recurring failures:

```text
- date changes
- negation traps
- similar numbers
- corrected information
```

Generate retry listening drills.

---

# Personalization Logic

Next session should be:

```text
70% weak-point repair
20% new DTZ patterns
10% confidence building
```

---

# Retry System

Every major error must create:

```text
- retry sentence
- retry roleplay
- retry writing task
- retry listening drill
```

Goal:

Immediate correction reinforcement.

---

# Anki Export Rules

Only export:

- recurring errors
- high-frequency chunks
- survival phrases
- correction patterns
- writing templates
- listening trap phrases

Avoid exporting every unknown word.

CSV format:

```text
Front,Back,Tag
```
