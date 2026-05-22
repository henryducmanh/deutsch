"""Helper für Phase B Vocab Extract — Lesen.

Liest alle `input/html/deutsch-vorbereitung/lesen/<bai>/<bai>_text.md` +
`_questions.md`, gibt JSON je Bài mit:
  - bai, teil, teil_desc, chu_de, url
  - text_md (Reading)
  - questions_md (Aufgaben)
  - candidate_substantive (capitalized tokens, dedupe, mit Häufigkeit + 1 Kontextsatz)

Nutzbar als Eingabe für LLM-Subagents: liest 1 JSON-Liste, gibt CSV-Rows zurück.

Usage:
  python lesen_vocab_helper.py --dump-json all          # alles als JSON-Liste
  python lesen_vocab_helper.py --dump-json --bai 1.1    # nur Bài 1.1
  python lesen_vocab_helper.py --dump-json --teil 5     # alle Bài von Teil 5
  python lesen_vocab_helper.py --batch N START          # JSON-Slice (N Bài ab Index START)
"""
from __future__ import annotations

import argparse
import io
import json
import re
import sys
from collections import Counter
from pathlib import Path

if sys.stdout.encoding and sys.stdout.encoding.lower() != "utf-8":
    try:
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace", line_buffering=True)
    except Exception:
        pass

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
LESEN_DIR = ROOT / "input" / "html" / "deutsch-vorbereitung" / "lesen"
VOCAB_CSV = ROOT / "data" / "03_unified" / "vocab_master.csv"

# Stopwords (klein, A1-Basics, Artikel, Konjunktionen) — vermeiden in Kandidaten
STOPLIKE_SUBSTANTIV = {
    "Der", "Die", "Das", "Den", "Dem", "Des",
    "Sie", "Er", "Es", "Ihr", "Ihre", "Ihren", "Ihrem", "Ihres",
    "Mein", "Meine", "Meinen", "Meinem",
    "Ich", "Wir", "Uns", "Mich", "Mir", "Dich", "Dir",
    "Herr", "Frau", "Dame", "Damen", "Herren",  # häufig in formellen Briefen
    "Sehr", "Lieber", "Liebe", "Hallo",
    "Berlin", "Hamburg", "München", "Köln",
}


def slurp(p: Path) -> str:
    if not p.exists():
        return ""
    try:
        return p.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return p.read_text(encoding="latin-1")


def parse_frontmatter(text: str) -> dict:
    """Sehr einfacher YAML-Parser: nur key: value pairs in --- … --- Block."""
    out: dict = {}
    m = re.match(r"^---\s*\n(.*?)\n---\s*\n", text, re.DOTALL)
    if not m:
        return out
    for line in m.group(1).splitlines():
        if ":" in line:
            k, _, v = line.partition(":")
            out[k.strip()] = v.strip().strip('"').strip("'")
    return out


def extract_candidate_substantive(text: str) -> list[dict]:
    """Sammle capitalized Tokens (außer Satzanfänge + Stopwords) mit Häufigkeit + 1 Kontextsatz."""
    # Sätze ungefähr per . ? ! splitten
    sentences = re.split(r"(?<=[.!?])\s+", text.replace("\n", " "))

    freq: Counter[str] = Counter()
    contexts: dict[str, str] = {}
    for sent in sentences:
        tokens = re.findall(r"[A-ZÄÖÜ][a-zäöüß]+(?:-[A-ZÄÖÜa-zäöüß]+)?", sent)
        for i, tok in enumerate(tokens):
            # Satzanfang skip (außer wenn auch weiter unten capitalized → Substantiv)
            if i == 0 and len(tokens) > 1:
                continue
            if tok in STOPLIKE_SUBSTANTIV:
                continue
            if len(tok) < 4:
                continue
            freq[tok] += 1
            if tok not in contexts:
                contexts[tok] = sent.strip()
    out: list[dict] = []
    for tok, n in freq.most_common():
        out.append({"wort": tok, "freq": n, "context": contexts.get(tok, "")})
    return out


def load_lesson(folder: Path) -> dict | None:
    bai = folder.name
    text_path = folder / f"{bai}_text.md"
    q_path = folder / f"{bai}_questions.md"
    if not text_path.exists() or not q_path.exists():
        return None
    text_raw = slurp(text_path)
    q_raw = slurp(q_path)
    fm = parse_frontmatter(text_raw)
    # Body = nach 2. ---
    parts = text_raw.split("---", 2)
    text_body = parts[2].strip() if len(parts) >= 3 else text_raw

    return {
        "bai": bai,
        "teil": fm.get("teil", ""),
        "teil_desc": fm.get("teil_desc", ""),
        "chu_de": fm.get("chu_de", ""),
        "url": fm.get("url", ""),
        "text_md": text_body,
        "questions_md": q_raw,
        "candidate_substantive": extract_candidate_substantive(text_body + "\n" + q_raw)[:40],
    }


def load_existing_vocab_keys() -> set[tuple[str, str]]:
    keys: set[tuple[str, str]] = set()
    if not VOCAB_CSV.exists():
        return keys
    import csv as csvm
    with VOCAB_CSV.open(encoding="utf-8", newline="") as f:
        for row in csvm.DictReader(f):
            keys.add((row.get("wort", "").lower(), row.get("wortart", "").lower()))
    return keys


def cmd_dump_json(args) -> None:
    folders = sorted([p for p in LESEN_DIR.iterdir() if p.is_dir()], key=lambda p: (
        int(p.name.split(".")[0]), int(p.name.split(".")[1])))
    if args.bai:
        folders = [p for p in folders if p.name == args.bai]
    if args.teil:
        folders = [p for p in folders if p.name.startswith(f"{args.teil}.")]
    if args.batch is not None:
        start = args.start or 0
        folders = folders[start : start + args.batch]

    lessons = []
    for folder in folders:
        lesson = load_lesson(folder)
        if lesson:
            lessons.append(lesson)
    print(json.dumps(lessons, ensure_ascii=False, indent=2))


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dump-json", action="store_true")
    ap.add_argument("--bai", default=None, help="z.B. 1.1")
    ap.add_argument("--teil", type=int, default=None, help="z.B. 5")
    ap.add_argument("--batch", type=int, default=None, help="N Bài je Batch")
    ap.add_argument("--start", type=int, default=None, help="Start-Index für --batch")
    args = ap.parse_args()
    if args.dump_json:
        cmd_dump_json(args)
    return 0


if __name__ == "__main__":
    sys.exit(main())
