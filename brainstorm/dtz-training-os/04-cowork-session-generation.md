# 04 — Cowork Session Generation

## Goal

Cowork is the orchestration layer.

It transforms:

```text
knowledge base + student profile
```

into:

```text
personalized 90-minute DTZ session
```

Cowork should behave like a DTZ training coordinator.

---

# Inputs

Cowork uses:

```text
1. Official framework
2. Processed knowledge base
3. Student profile
4. Previous session reports
5. Tutor focus / availability
```

---

# Session Output

Cowork generates:

```text
- Session Context Pack
- Live Worksheet
- Tutor Instructions
- Student Prework
- Retry Drills
- Post-session Report Template
```

---

# Session Context Pack

Template:

```text
Session ID:
Date:
Duration:
Primary Skill:
Secondary Skill:
Weakness Focus:

Goal:
Topic:
Source Materials:
Required Chunks:
Target Grammar:
Survival Phrases:
Roleplay/Task:
Tutor Instructions:
Timing Plan:
Error Tracking Focus:
Retry Drill:
Output Required:
```

---

# Session Generation Logic

Cowork should prioritize:

```text
70% weak-point repair
20% new DTZ pattern
10% confidence building
```

---

# Speaking Session Logic

Cowork selects:

- roleplay topic
- examiner behavior
- required chunks
- target grammar
- likely freeze moments
- retry tasks

Examples:

```text
Topic: Arzttermin
Task: move appointment
Grammar: weil
Chunks:
- Leider kann ich nicht kommen, weil ...
- Könnten wir den Termin verschieben?
```

---

# Writing Session Logic

Cowork generates:

- email skeleton
- chunk list
- timed writing drill
- correction focus
- rewrite sprint

---

# Listening Session Logic

Cowork selects:

- prediction vocabulary
- trap type
- listening goal
- shadowing segment
- retry audio

---

# Reading Session Logic

Cowork generates:

- scan keywords
- distractor keywords
- timed reading tasks
- speed retry

---

# Tutor Instructions

Cowork should generate tutor guidance.

Tutor should:

- keep student speaking
- avoid over-correction
- note recurring errors
- create interaction pressure
- force retry loops

Tutor should not:

- lecture grammar for long periods
- interrupt constantly
- translate everything

---

# Session Difficulty Scaling

Difficulty should scale based on:

```text
- speaking fluency
- hesitation frequency
- chunk usage
- grammar accuracy
- listening success
- reading speed
- writing structure quality
```

---

# Session Completion Rule

A session is not complete unless it produces:

```text
- error report
- retry drills
- next-session priorities
- updated student profile
```
