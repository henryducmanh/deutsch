# 08 — Notion Database Design

## Goal

Use Notion as:

- session dashboard
- knowledge hub
- tutor workspace
- student tracking system
- DTZ control center

---

# Main Databases

```text
1. Source Library
2. Pattern Database
3. Chunk Database
4. Session Database
5. Error Database
6. Student Profile
7. Simulation Bank
```

---

# Source Library

Stores:

- official documents
- model tests
- audio
- transcripts
- tutor notes

Properties:

```text
Name
Type
Skill
Source
Status
Priority
Link/File
Notes
```

---

# Pattern Database

Stores extracted patterns.

Properties:

```text
Name
Skill
Pattern Type
Topic
Source Test
Difficulty
Status
```

---

# Chunk Database

Stores reusable chunks.

Properties:

```text
Chunk
Meaning
Skill
Register
Use Case
Frequency
Difficulty
Anki Ready
```

---

# Session Database

Tracks all lessons.

Properties:

```text
Session ID
Date
Tutor
Skill Focus
Topic
Status
Context Pack
Worksheet
Report
Recording
```

---

# Error Database

Tracks recurring student problems.

Properties:

```text
Error
Correct Version
Skill
Type
Severity
Frequency
Retry Drill
Next Review
Resolved
```

---

# Student Profile

Stores long-term progress.

Properties:

```text
Student Name
Speaking Fluency
Weak Grammar
Strong Chunks
Listening Weaknesses
Reading Speed
Writing Issues
Next Priorities
```

---

# Simulation Bank

Stores reusable DTZ tasks.

Properties:

```text
Simulation Name
Skill
Topic
Difficulty
Required Chunks
Target Grammar
Tutor Instructions
Retry Plan
```

---

# Suggested Views

Tutor View:
- upcoming sessions
- student weaknesses
- retry drills

Student View:
- homework
- retry tasks
- strong chunks

Processing View:
- raw sources
- extraction status
- QA status
