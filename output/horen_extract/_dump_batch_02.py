"""Dump all transcripts and questions for batch_02 folders to a single file for review."""
import json
import os

BASE = r"C:\twv_share\app\deutsch"
FOLDERS_JSON = os.path.join(BASE, "output", "horen_extract", "batch_02_folders.json")
HOREN_DIR = os.path.join(BASE, "input", "html", "deutsch-vorbereitung", "horen")
OUT = os.path.join(BASE, "output", "horen_extract", "_batch_02_dump.txt")

with open(FOLDERS_JSON, "r", encoding="utf-8") as f:
    folders = json.load(f)

with open(OUT, "w", encoding="utf-8") as out:
    for folder in folders:
        out.write(f"\n========== FOLDER {folder} ==========\n")
        tpath = os.path.join(HOREN_DIR, folder, f"{folder}_transcript.md")
        qpath = os.path.join(HOREN_DIR, folder, f"{folder}_questions.md")
        for label, p in (("TRANSCRIPT", tpath), ("QUESTIONS", qpath)):
            out.write(f"\n--- {label} ({p}) ---\n")
            if os.path.exists(p):
                with open(p, "r", encoding="utf-8") as f:
                    out.write(f.read())
            else:
                out.write("(missing)\n")
        out.write("\n")

print(f"Wrote dump to {OUT}")
