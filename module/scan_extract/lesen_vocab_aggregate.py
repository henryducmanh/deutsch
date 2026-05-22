"""Aggregate JSON outputs from Lesen Phase B subagents.

Reads alle JSON-Dateien aus output/lesen_extract/*.json (Format:
{"<bai>": {"vocab": [...], "chunks": [...]}}), dedupliziert gegen
existierendes vocab_master.csv + chunks_master.csv, vergibt neue IDs,
appendet CSVs, markiert processed_files.csv.

Usage:
  python lesen_vocab_aggregate.py --files teil1_batch1.json teil3_batch1.json ...
  python lesen_vocab_aggregate.py --all          # alle output/lesen_extract/*.json
  python lesen_vocab_aggregate.py --all --dry-run
"""
from __future__ import annotations

import argparse
import csv
import io
import json
import re
import sys
from datetime import date, datetime
from pathlib import Path

if sys.stdout.encoding and sys.stdout.encoding.lower() != "utf-8":
    try:
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace", line_buffering=True)
    except Exception:
        pass

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
EXTRACT_DIR = ROOT / "output" / "lesen_extract"
VOCAB_CSV = ROOT / "data" / "03_unified" / "vocab_master.csv"
CHUNKS_CSV = ROOT / "data" / "chunks_master.csv"
PROC_CSV = ROOT / "data" / "processed_files.csv"
LESEN_REL_BASE = "input/html/deutsch-vorbereitung/lesen"

VOCAB_COLUMNS = [
    "id", "wort", "wortart", "formen", "bedeutung", "beispiel",
    "uebersetzung", "thema", "lerndatum", "level", "quelle", "source_type", "tags", "notes",
]
CHUNK_COLUMNS = [
    "id", "chunk_de", "chunk_vn", "type", "topic", "level", "source",
    "first_seen", "last_practiced", "frequency", "note",
]

CHUNK_TYPE_MAP = {
    "redemittel": "redemittel",
    "wendung": "idiom",
    "kollokation": "collocation",
    "idiom": "idiom",
    "phrasal": "phrasal",
    "formula": "formula",
    "grammatik": "redemittel",
    "collocation": "collocation",
}
CHUNK_TOPIC_ENUM = {
    "Arbeit", "Wohnen", "Gesundheit", "Behörde", "Familie", "Freizeit",
    "Bildung", "Einkauf", "Verkehr", "Wetter", "general",
}
TOPIC_MAP = {
    # häufige Subagent-Themen → enum
    "konsum": "Einkauf",
    "einkauf": "Einkauf",
    "reklamation": "Einkauf",
    "brief-anrede": "Behörde",
    "brief-schluss": "Behörde",
    "brief-höflichkeit": "Behörde",
    "brief-hinweis": "Behörde",
    "antrag-einleitung": "Behörde",
    "antrag-abschluss": "Behörde",
    "reklamation-schluss": "Einkauf",
    "behörde": "Behörde",
    "verkehr": "Verkehr",
    "wohnen": "Wohnen",
    "gesundheit": "Gesundheit",
    "familie": "Familie",
    "freizeit": "Freizeit",
    "bildung": "Bildung",
    "arbeit": "Arbeit",
    "wetter": "Wetter",
}


def slug_topic(s: str) -> str:
    """Map freier Topic-String auf chunks_master enum."""
    s_low = (s or "").strip().lower()
    if s in CHUNK_TOPIC_ENUM:
        return s
    return TOPIC_MAP.get(s_low, "general")


def slug_chunk_type(s: str) -> str:
    s_low = (s or "").strip().lower()
    return CHUNK_TYPE_MAP.get(s_low, "redemittel")


def load_existing_vocab_keys() -> set[tuple[str, str]]:
    keys: set[tuple[str, str]] = set()
    if not VOCAB_CSV.exists():
        return keys
    with VOCAB_CSV.open(encoding="utf-8", newline="") as f:
        for row in csv.DictReader(f):
            keys.add((row.get("wort", "").strip().lower(), row.get("wortart", "").strip().lower()))
    return keys


def load_existing_chunk_keys() -> set[str]:
    keys: set[str] = set()
    if not CHUNKS_CSV.exists():
        return keys
    with CHUNKS_CSV.open(encoding="utf-8", newline="") as f:
        for line in f:
            if line.startswith("#") or line.startswith("id,"):
                continue
            parts = next(csv.reader([line]), [])
            if len(parts) >= 2:
                keys.add(parts[1].strip().lower())
    return keys


def get_next_seq(prefix: str, csv_path: Path, day: str) -> int:
    """Find the max numerical suffix for IDs <prefix>-<day>-<N> in csv_path."""
    if not csv_path.exists():
        return 1
    rx = re.compile(rf"^{re.escape(prefix)}-{re.escape(day)}-(\d+)")
    max_n = 0
    with csv_path.open(encoding="utf-8") as f:
        for line in f:
            m = rx.match(line)
            if m:
                n = int(m.group(1))
                if n > max_n:
                    max_n = n
    return max_n + 1


def determine_thema(raw: str) -> str:
    """Erlaubt freie thema, ohne strict enum (vocab_master hat freies thema)."""
    return (raw or "").strip() or "Allgemein"


def aggregate(files: list[Path], dry_run: bool) -> dict:
    today = date.today().isoformat()
    today_compact = today.replace("-", "")

    voc_keys = load_existing_vocab_keys()
    ch_keys = load_existing_chunk_keys()

    voc_seq = get_next_seq("VOC", VOCAB_CSV, today_compact)
    ch_seq = get_next_seq("CH", CHUNKS_CSV, today_compact)

    new_vocab_rows: list[list[str]] = []
    new_chunk_rows: list[list[str]] = []
    processed_files: list[tuple[str, int, int]] = []  # (rel_path, vocab_count, chunk_count)
    skip_voc = 0
    skip_ch = 0
    teil_counts: dict[str, int] = {}

    for jf in files:
        try:
            data = json.loads(jf.read_text(encoding="utf-8"))
        except json.JSONDecodeError as e:
            print(f"[WARN] {jf.name}: JSON parse fehlgeschlagen: {e}", file=sys.stderr)
            continue

        for bai, payload in data.items():
            teil = bai.split(".", 1)[0]
            teil_counts.setdefault(teil, 0)
            bai_vocab_count = 0
            bai_chunk_count = 0

            for v in payload.get("vocab", []) or []:
                # Tolerant schema: subagents used different keys
                wort = (v.get("wort") or v.get("de") or "").strip()
                wortart = (v.get("wortart") or "").strip()
                if not wort or not wortart:
                    continue
                key = (wort.lower(), wortart.lower())
                if key in voc_keys:
                    skip_voc += 1
                    continue
                voc_keys.add(key)

                row_id = f"VOC-{today_compact}-{voc_seq:03d}"
                voc_seq += 1
                thema_raw = v.get("thema") or v.get("topic") or ""
                thema = determine_thema(thema_raw)
                tags = f"B1;DTZ;{thema}"
                quelle = f"lesen/{bai}"

                formen = v.get("formen") or v.get("form") or v.get("grundform") or ""
                bedeutung = v.get("bedeutung") or v.get("vn") or ""
                beispiel = v.get("beispiel") or v.get("beispiel_de") or ""
                uebersetzung = v.get("uebersetzung") or v.get("beispiel_vn") or v.get("beispiel_vi") or ""
                notes = v.get("notes") or v.get("note") or ""

                new_vocab_rows.append([
                    row_id, wort, wortart,
                    formen, bedeutung, beispiel, uebersetzung,
                    thema, today, "B1", quelle, "lesen", tags, notes,
                ])
                bai_vocab_count += 1

            for c in payload.get("chunks", []) or []:
                chunk_de = (c.get("chunk_de") or c.get("chunk") or "").strip()
                # subagent 5 verwendete "bedeutung" als chunk_vn wenn key heißt "chunk"
                chunk_vn = (c.get("chunk_vn") or c.get("bedeutung") or "").strip()
                if not chunk_de:
                    continue
                key = chunk_de.lower()
                if key in ch_keys:
                    skip_ch += 1
                    continue
                ch_keys.add(key)

                row_id = f"CH-{today_compact}-{ch_seq:03d}"
                ch_seq += 1
                ctype = slug_chunk_type(c.get("type") or c.get("typ") or "")
                topic = slug_topic(c.get("topic") or c.get("thema") or "")
                source = f"lesen/{bai}"
                note = c.get("note") or c.get("notes") or c.get("grammatik") or ""

                new_chunk_rows.append([
                    row_id, chunk_de, chunk_vn, ctype, topic, "B1",
                    source, today, today, "1", note,
                ])
                bai_chunk_count += 1

            teil_counts[teil] += bai_vocab_count

            # Tracking dateiweise für processed_files.csv
            text_rel = f"{LESEN_REL_BASE}/{bai}/{bai}_text.md"
            q_rel = f"{LESEN_REL_BASE}/{bai}/{bai}_questions.md"
            processed_files.append((text_rel, bai_vocab_count, bai_chunk_count))
            processed_files.append((q_rel, 0, 0))

    # Write CSVs
    if not dry_run and new_vocab_rows:
        with VOCAB_CSV.open("a", encoding="utf-8", newline="") as f:
            w = csv.writer(f, quoting=csv.QUOTE_MINIMAL, lineterminator="\n")
            for r in new_vocab_rows:
                w.writerow(r)

    if not dry_run and new_chunk_rows:
        with CHUNKS_CSV.open("a", encoding="utf-8", newline="") as f:
            w = csv.writer(f, quoting=csv.QUOTE_MINIMAL, lineterminator="\n")
            for r in new_chunk_rows:
                w.writerow(r)

    if not dry_run and processed_files:
        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        with PROC_CSV.open("a", encoding="utf-8") as f:
            for rel, vc, cc in processed_files:
                note = f"lesen batch-extract"
                if vc or cc:
                    note += f" v={vc} c={cc}"
                f.write(f"{rel},{now},vocab-extractor,{vc},{vc+cc},ok,{note}\n")

    return {
        "vocab_added": len(new_vocab_rows),
        "chunks_added": len(new_chunk_rows),
        "vocab_skip_dedupe": skip_voc,
        "chunks_skip_dedupe": skip_ch,
        "files_processed": len(processed_files),
        "teil_vocab_counts": teil_counts,
        "dry_run": dry_run,
    }


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--files", nargs="+", default=None, help="JSON-Dateien (in output/lesen_extract/)")
    ap.add_argument("--all", action="store_true", help="alle JSON in output/lesen_extract/")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    if args.all:
        files = sorted(EXTRACT_DIR.glob("*.json"))
    elif args.files:
        files = []
        for name in args.files:
            p = Path(name)
            if not p.exists():
                p = EXTRACT_DIR / name
            if not p.exists():
                print(f"[ERROR] not found: {name}", file=sys.stderr)
                return 1
            files.append(p)
    else:
        print("Need --all or --files", file=sys.stderr)
        return 1

    if not files:
        print("[ERROR] no JSON files found", file=sys.stderr)
        return 1

    print(f"Aggregating {len(files)} JSON file(s):")
    for f in files:
        print(f"  - {f.relative_to(ROOT)}")

    result = aggregate(files, args.dry_run)
    print()
    print("=== AGGREGATE RESULT ===")
    print(f"Vocab added       : {result['vocab_added']}")
    print(f"Chunks added      : {result['chunks_added']}")
    print(f"Vocab dedupe skip : {result['vocab_skip_dedupe']}")
    print(f"Chunks dedupe skip: {result['chunks_skip_dedupe']}")
    print(f"Files marked      : {result['files_processed']}")
    print(f"Vocab per Teil    : {result['teil_vocab_counts']}")
    print(f"Mode              : {'DRY-RUN (no writes)' if result['dry_run'] else 'LIVE (CSV updated)'}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
