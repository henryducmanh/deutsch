#!/usr/bin/env python3
"""
aggregate_horen_batches.py — merge 6 batch CSVs from agents into vocab_master.csv.

Reads:
  output/horen_extract/batch_NN_vocab.csv  (10 cols, no header)
    folder,wort,wortart,formen,bedeutung,beispiel,uebersetzung,thema,tags,notes
  data/03_unified/vocab_master.csv         (14 cols, with header — dedupe source)

Writes:
  data/03_unified/vocab_master.csv         (append unique rows)
  data/processed_files.csv                 (mark transcript+questions as processed)
  output/horen_extract/aggregate_report.json  (summary)
"""

import csv
import json
import re
import sys
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
EXTRACT_DIR = ROOT / "output" / "horen_extract"
VOCAB_CSV   = ROOT / "data" / "03_unified" / "vocab_master.csv"
PROC_CSV    = ROOT / "data" / "processed_files.csv"
HOREN_DIR   = ROOT / "input" / "html" / "deutsch-vorbereitung" / "horen"

VOCAB_HEADER = [
    "id","wort","wortart","formen","bedeutung","beispiel","uebersetzung",
    "thema","lerndatum","level","quelle","source_type","tags","notes",
]

TODAY = datetime.now().strftime("%Y-%m-%d")
TODAY_COMPACT = datetime.now().strftime("%Y%m%d")


def load_existing_keys() -> set:
    keys = set()
    if not VOCAB_CSV.exists():
        return keys
    with open(VOCAB_CSV, encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            w = (row.get("wort") or "").strip().lower()
            wa = (row.get("wortart") or "").strip().lower()
            if w:
                keys.add((w, wa))
    return keys


def next_vocab_id_seed() -> int:
    """Find current max NNN in VOC-<TODAY>-NNN."""
    if not VOCAB_CSV.exists():
        return 0
    pat = re.compile(rf"^VOC-{TODAY_COMPACT}-(\d+)")
    mx = 0
    with open(VOCAB_CSV, encoding="utf-8") as f:
        for line in f:
            m = pat.match(line.strip())
            if m:
                mx = max(mx, int(m.group(1)))
    return mx


def load_processed_filepaths() -> set:
    s = set()
    if not PROC_CSV.exists():
        return s
    with open(PROC_CSV, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line.startswith("#") or not line:
                continue
            parts = line.split(",")
            if parts and parts[0]:
                s.add(parts[0].strip())
    return s


def main():
    existing = load_existing_keys()
    print(f"Existing vocab keys: {len(existing)}")

    # Collect all rows from batch CSVs
    all_rows = []  # list of dicts: folder, wort, wortart, formen, bedeutung, beispiel, uebersetzung, thema, tags, notes
    batch_files = sorted(EXTRACT_DIR.glob("batch_*_vocab.csv"))
    folders_touched = set()
    batch_stats = {}
    for bf in batch_files:
        n_in = 0
        with open(bf, encoding="utf-8", newline="") as f:
            reader = csv.reader(f)
            for row in reader:
                if len(row) < 10:
                    continue
                folder, wort, wortart, formen, bedeutung, beispiel, uebersetzung, thema, tags, notes = row[:10]
                wort = wort.strip()
                wortart = wortart.strip()
                if not wort:
                    continue
                all_rows.append({
                    "folder": folder.strip(),
                    "wort": wort,
                    "wortart": wortart,
                    "formen": formen.strip(),
                    "bedeutung": bedeutung.strip(),
                    "beispiel": beispiel.strip(),
                    "uebersetzung": uebersetzung.strip(),
                    "thema": thema.strip() or "Allgemein",
                    "tags": tags.strip() or "B1;DTZ;Allgemein",
                    "notes": notes.strip(),
                })
                folders_touched.add(folder.strip())
                n_in += 1
        batch_stats[bf.name] = n_in
        print(f"{bf.name}: read {n_in} rows")
    print(f"Total rows from batches: {len(all_rows)} across {len(folders_touched)} folders")

    # Dedupe vs existing AND within-incoming
    seen_incoming = set()
    new_rows = []
    skipped_existing = 0
    skipped_intra = 0
    for r in all_rows:
        key = (r["wort"].lower(), r["wortart"].lower())
        if key in existing:
            skipped_existing += 1
            continue
        if key in seen_incoming:
            skipped_intra += 1
            continue
        seen_incoming.add(key)
        new_rows.append(r)

    print(f"After dedupe: {len(new_rows)} new (skipped {skipped_existing} dup-existing, {skipped_intra} dup-intra)")

    # Assign IDs and append
    seed = next_vocab_id_seed()
    print(f"ID seed: VOC-{TODAY_COMPACT}-{seed+1:03d}")

    by_wortart = {}
    appended_full_rows = []
    with open(VOCAB_CSV, "a", encoding="utf-8", newline="") as f:
        writer = csv.writer(f)
        for i, r in enumerate(new_rows, 1):
            vid = f"VOC-{TODAY_COMPACT}-{seed + i:03d}"
            row = [
                vid, r["wort"], r["wortart"], r["formen"], r["bedeutung"],
                r["beispiel"], r["uebersetzung"], r["thema"], TODAY, "B1",
                f"horen/{r['folder']}", "horen", r["tags"], r["notes"],
            ]
            writer.writerow(row)
            appended_full_rows.append(row)
            by_wortart[r["wortart"]] = by_wortart.get(r["wortart"], 0) + 1

    # Mark all transcript + questions as processed (only those not yet logged)
    already_processed = load_processed_filepaths()
    now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    marked = 0
    with open(PROC_CSV, "a", encoding="utf-8") as f:
        for folder in sorted(folders_touched, key=lambda n: tuple(int(x) for x in n.split("."))):
            for kind in ("_transcript.md", "_questions.md"):
                fname = folder + kind
                src = HOREN_DIR / folder / fname
                if not src.exists():
                    continue
                rel = src.relative_to(ROOT).as_posix()
                if rel in already_processed:
                    continue
                # count vocab from this folder for output_rows column
                vocab_n = sum(1 for x in new_rows if x["folder"] == folder)
                # only count once (on transcript)
                if kind == "_transcript.md":
                    rows_col = vocab_n
                else:
                    rows_col = 0
                line = f"{rel},{now_str},vocab-extractor,{rows_col},vocab_master.csv,ok,horen/{folder} batch-extract\n"
                f.write(line)
                marked += 1

    # Also mark folders that had zero new vocab (after dedupe) so they aren't reprocessed
    # — iterate over ALL folders touched by any batch input json
    all_input_folders = set()
    for jf in sorted(EXTRACT_DIR.glob("batch_*_folders.json")):
        all_input_folders.update(json.loads(jf.read_text(encoding="utf-8")))

    folders_with_no_vocab = all_input_folders - folders_touched
    if folders_with_no_vocab:
        with open(PROC_CSV, "a", encoding="utf-8") as f:
            for folder in sorted(folders_with_no_vocab, key=lambda n: tuple(int(x) for x in n.split("."))):
                for kind in ("_transcript.md", "_questions.md"):
                    fname = folder + kind
                    src = HOREN_DIR / folder / fname
                    if not src.exists():
                        continue
                    rel = src.relative_to(ROOT).as_posix()
                    if rel in already_processed:
                        continue
                    line = f"{rel},{now_str},vocab-extractor,0,vocab_master.csv,ok,horen/{folder} batch-extract (no new vocab)\n"
                    f.write(line)
                    marked += 1

    # Top 5 most "interesting" — pick longest bedeutung or first 5 Verbs
    interesting = []
    verbs = [r for r in new_rows if r["wortart"].lower() == "verb"][:5]
    if verbs:
        interesting = verbs
    else:
        interesting = new_rows[:5]

    report = {
        "total_rows_from_batches": len(all_rows),
        "folders_touched": len(folders_touched),
        "all_input_folders": len(all_input_folders),
        "folders_with_no_vocab_after_dedupe": len(folders_with_no_vocab),
        "new_vocab_appended": len(new_rows),
        "skipped_dup_existing": skipped_existing,
        "skipped_dup_intra_batch": skipped_intra,
        "by_wortart": by_wortart,
        "batch_stats": batch_stats,
        "processed_files_marked": marked,
        "top_picks": [{"wort": r["wort"], "wortart": r["wortart"], "bedeutung": r["bedeutung"], "folder": r["folder"]} for r in interesting],
    }

    out_json = EXTRACT_DIR / "aggregate_report.json"
    out_json.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
