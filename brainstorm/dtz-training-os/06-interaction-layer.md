# 06 — Interaction Layer

## Goal

The live lesson must run on a platform that:

- supports tutor + student interaction
- captures session data
- allows note-taking
- exports data back to Cowork

The interaction layer is the bridge between:

```text
session generation
↔
live lesson
↔
personalization loop
```

---

# Recommended Stack

```text
Google Meet / Zoom
+ Google Docs Live Worksheet
+ Notion Session Page
+ Optional recording/transcript
+ Cowork post-processing
```

---

# Live Session Requirements

During the lesson:

- tutor and student speak in Meet/Zoom
- both open the live worksheet
- tutor notes errors directly
- optional audio recording
- worksheet must be completed before session ends

---

# Live Worksheet Structure

```text
Session ID:
Date:
Tutor:
Student:
Skill Focus:

Today Goal
Required Chunks
Target Grammar

Task 1
Tutor Corrections

Task 2
Tutor Corrections

Mistakes
Corrected Versions

Good Sentences
Freeze Moments
Retry Tasks
Homework
Next Session Suggestions
```

---

# Data Capture

At minimum, save:

```text
- worksheet
- tutor notes
- error list
- retry drills
```

Preferred:

```text
- audio recording
- transcript
- speaking timestamps
```

---

# Session Folder

Store each session in:

```text
SESSIONS/YYYY-MM-DD_Session_XXX/
```

Example:

```text
2026-05-29_Session_001/
├── 01_Context_Pack.md
├── 02_Live_Worksheet.md
├── 03_Recording_Link.txt
├── 04_Transcript.txt
├── 05_Tutor_Notes.md
├── 06_Error_Report.md
├── 07_Anki_Export.csv
└── 08_Next_Session_Input.md
```

---

# Interaction Philosophy

The lesson should feel like:

```text
DTZ training gym
```

not:

```text
traditional grammar lecture
```

Student output must dominate the session.
