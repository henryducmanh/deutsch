"""Refetch transcripts cho bài có _transcript.md body empty.

Behält MP3 + _questions.md, schreibt nur _transcript.md neu.
Erkennt 'echte' Empty (bài ohne Transcript) durch Markierung in der Header.
"""
from __future__ import annotations

import csv
import logging
import sys
import time
from pathlib import Path

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).parent))
from horen_scraper import (  # noqa: E402
    Lesson,
    SLEEP_BETWEEN,
    http_get,
    http_post,
    make_session,
    parse_transcript,
    render_transcript_md,
)

OUT_ROOT = Path(r"C:\twv_share\app\deutsch\input\html\deutsch-vorbereitung\horen")
CSV_PATH = Path(
    r"C:\twv_share\app\deutsch\input\html\deutsch-vorbereitung\horen_lessons.csv"
)
LOG_PATH = OUT_ROOT.parent / "horen_fix_transcripts.log"

logger = logging.getLogger("fix")
logger.setLevel(logging.INFO)
fh = logging.FileHandler(LOG_PATH, encoding="utf-8")
fh.setFormatter(logging.Formatter("%(asctime)s\t%(levelname)s\t%(message)s"))
logger.addHandler(fh)
sh = logging.StreamHandler(sys.stdout)
sh.setFormatter(logging.Formatter("%(message)s"))
logger.addHandler(sh)


def transcript_body_len(path: Path) -> int:
    """Return Länge des transcript-Body (ohne Header)."""
    if not path.exists():
        return -1
    body = path.read_text(encoding="utf-8")
    # Header pattern: "# Transcript — ...\n\nSource: ...\n\n<body>"
    parts = body.split("\n\n", 2)
    return len(parts[-1].strip()) if len(parts) >= 3 else 0


def find_empty(lessons: list[Lesson]) -> list[Lesson]:
    return [
        l for l in lessons
        if 0 <= transcript_body_len(OUT_ROOT / l.bai / f"{l.bai}_transcript.md") < 20
    ]


def load_lessons() -> list[Lesson]:
    with CSV_PATH.open("r", encoding="utf-8") as f:
        return [
            Lesson(stt=r["stt"], bai=r["bai"], chu_de=r["chu_de"], url=r["url"])
            for r in csv.DictReader(f)
        ]


def fix_one(session, lesson: Lesson) -> tuple[str, int]:
    """Return (status, transcript_body_len)."""
    try:
        r1 = http_get(session, lesson.url)
        r2 = http_post(session, r1.url)
    except Exception as e:
        logger.error("error\t%s\t%s", lesson.bai, e)
        return "error", 0
    soup = BeautifulSoup(r2.text, "lxml")
    transcript = parse_transcript(soup)
    out_path = OUT_ROOT / lesson.bai / f"{lesson.bai}_transcript.md"
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(render_transcript_md(lesson, transcript), encoding="utf-8")
    status = "filled" if len(transcript) >= 20 else "still_empty"
    logger.info("%s\t%s\tlen=%d", status, lesson.bai, len(transcript))
    return status, len(transcript)


SESSION_RESET_EVERY = 15   # Neue Session alle N bài (Cookie-Reset)
SLEEP_PER_BAI = 4.0        # Statt 1.5s -- größerer Abstand
LONG_PAUSE_AFTER_EMPTY = 120  # Pause nach 10 consecutive empty


def main() -> int:
    lessons = load_lessons()
    empty = find_empty(lessons)
    logger.info("start\ttotal=%d\tempty=%d\tsleep=%.1fs\treset_every=%d",
                len(lessons), len(empty), SLEEP_PER_BAI, SESSION_RESET_EVERY)
    print(f"Empty transcripts to fix: {len(empty)}")
    print(f"Sleep: {SLEEP_PER_BAI}s, session reset every {SESSION_RESET_EVERY} bài")
    if not empty:
        return 0

    session = make_session()
    counts = {"filled": 0, "still_empty": 0, "error": 0}
    consecutive_empty = 0
    for i, l in enumerate(empty, start=1):
        # Neue Session alle SESSION_RESET_EVERY bài
        if i > 1 and (i - 1) % SESSION_RESET_EVERY == 0:
            session.close()
            session = make_session()
            logger.info("session_reset\tafter %d bài", i - 1)

        status, n = fix_one(session, l)
        counts[status] = counts.get(status, 0) + 1
        if status == "still_empty":
            consecutive_empty += 1
        else:
            consecutive_empty = 0
        if i % 25 == 0:
            print(
                f"progress {i}/{len(empty)} filled={counts['filled']} "
                f"still_empty={counts['still_empty']} err={counts['error']}"
            )
        if consecutive_empty >= 10:
            logger.warning(
                "10 still_empty in a row -- pause %ds + fresh session",
                LONG_PAUSE_AFTER_EMPTY,
            )
            time.sleep(LONG_PAUSE_AFTER_EMPTY)
            session.close()
            session = make_session()
            consecutive_empty = 0
        elif i < len(empty):
            time.sleep(SLEEP_PER_BAI)

    print()
    print("=== HOREN FIX TRANSCRIPTS ===")
    print(f"Empty input : {len(empty)}")
    print(f"Filled      : {counts['filled']}")
    print(f"Still empty : {counts['still_empty']}")
    print(f"Errors      : {counts['error']}")
    print(f"Log         : {LOG_PATH}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
