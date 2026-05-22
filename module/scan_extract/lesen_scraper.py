"""Lesen scraper — deutsch-vorbereitung.com (Phase A).

Per Aufgabe `docs/ai/tasks/LESEN_SCRAPE_VOCAB_PROMPT.md`:
  - GET /uebung-XXXX.html  -> reading text + 5 Aufgaben + options + explanation modals
  - POST submit=1          -> markiert .green class auf richtige Option
  - Kein Audio.
  - Output je Bài: <bai>_text.md (mit YAML frontmatter) + <bai>_questions.md
"""
from __future__ import annotations

import argparse
import csv
import io
import logging
import re
import sys
import time
from dataclasses import dataclass
from datetime import date
from pathlib import Path

# UTF-8 stdout für Windows-Konsole (cp1252 default → UnicodeEncodeError bei Vietnamese/de chars)
if sys.stdout.encoding and sys.stdout.encoding.lower() != "utf-8":
    try:
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace", line_buffering=True)
    except Exception:
        pass

import requests
from bs4 import BeautifulSoup
from tqdm import tqdm

BASE_URL = "https://deutsch-vorbereitung.com"
USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36"
)
SLEEP_BETWEEN = 1.5
RETRY_BACKOFF = [2, 4, 8]
CLOUDFLARE_ABORT_THRESHOLD = 5

# Test mode: 1 Bài pro Teil (1.1, 2.1, 3.1, 4.1, 5.1)
TEST_BAI = ["1.1", "2.1", "3.1", "4.1", "5.1"]


@dataclass
class Lesson:
    stt: str
    bai: str
    chu_de: str
    url: str
    teil: str
    teil_desc: str


def make_session() -> requests.Session:
    s = requests.Session()
    s.headers.update(
        {
            "User-Agent": USER_AGENT,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "de-DE,de;q=0.9,en;q=0.8",
        }
    )
    return s


def http_get(session: requests.Session, url: str) -> requests.Response:
    last_exc: Exception | None = None
    for wait in [0, *RETRY_BACKOFF]:
        if wait:
            time.sleep(wait)
        try:
            r = session.get(url, allow_redirects=True, timeout=30)
            if r.status_code in (429, 503):
                last_exc = RuntimeError(f"HTTP {r.status_code}")
                continue
            r.raise_for_status()
            return r
        except requests.RequestException as e:
            last_exc = e
    raise RuntimeError(f"GET failed: {url} ({last_exc})")


def http_post(session: requests.Session, url: str) -> requests.Response:
    last_exc: Exception | None = None
    for wait in [0, *RETRY_BACKOFF]:
        if wait:
            time.sleep(wait)
        try:
            r = session.post(url, data={"submit": "1"}, allow_redirects=True, timeout=30)
            if r.status_code in (429, 503):
                last_exc = RuntimeError(f"HTTP {r.status_code}")
                continue
            r.raise_for_status()
            return r
        except requests.RequestException as e:
            last_exc = e
    raise RuntimeError(f"POST failed: {url} ({last_exc})")


def parse_reading_text(soup: BeautifulSoup) -> str:
    """Reading text = 1. div.box_border.back__width.

    Für Lückentexte (Teil 5): Dropdown-Optionen `span.ul > span.li` durch
    `___(N)___` Platzhalter ersetzen, damit der Lesetext sauber bleibt.
    """
    boxes = soup.select("div.box_border.back__width")
    if not boxes:
        boxes = soup.select("div.box_border")
    if not boxes:
        return ""
    box = boxes[0]

    # Lückentext-Modus: ersetze span.ul mit Platzhalter (in-place auf Kopie)
    from copy import copy
    box_clone = BeautifulSoup(str(box), "lxml").select_one("div") or box
    luecken = box_clone.select("span.ul")
    for idx, sp in enumerate(luecken, start=1):
        sp.string = f"___({idx})___"
        sp.unwrap() if False else sp.replace_with(f" ___({idx})___ ")

    return box_clone.get_text(separator="\n", strip=True)


def parse_luecken_questions(soup: BeautifulSoup, post_soup: BeautifulSoup | None) -> list[dict]:
    """Lückentext (Teil 5): jeder `span.ul` = eine Lücke mit 3 `span.li[data-num]` Optionen.

    Korrekte Antwort steht im aggregate inputmodal (`inputmodal-2` oder ähnlich) —
    Format: `✅ N. Richtig: „TEXT" ... ❌ x) wrongA ... ❌ y) wrongB ...`
    """
    box = soup.select_one("div.box_border.back__width") or soup.select_one("div.box_border")
    if not box:
        return []
    luecken = box.select("span.ul")
    if not luecken:
        return []

    # Aggregate explanation modal — finde ihn im POST falls vorhanden, sonst GET.
    explanation_text = ""
    for src_soup in (post_soup, soup):
        if src_soup is None:
            continue
        # Pick modal mit den meisten "Richtig:"-Markern
        best = ""
        for m in src_soup.select("div.inputmodal__content"):
            txt = m.get_text("\n", strip=True)
            if "Richtig" in txt and txt.count("Richtig") >= best.count("Richtig"):
                best = txt
        if best:
            explanation_text = best
            break

    # Split nach ✅ N. Richtig: oder ✅ N.
    blocks: dict[int, str] = {}
    if explanation_text:
        # Pattern: "✅ <num>. Richtig: ..." bis zum nächsten "✅ <num>."
        pattern = re.compile(r"✅\s*(\d+)\.\s*Richtig[:：]?\s*[„\"']?([^\n„\"]*?)[\"„'»\s]", re.MULTILINE)
        # einfacher: split nach "✅" und schaue auf die Nummer
        parts = re.split(r"(?=✅\s*\d+\.)", explanation_text)
        for part in parts:
            m = re.match(r"✅\s*(\d+)\.\s*", part)
            if m:
                blocks[int(m.group(1))] = part.strip()

    questions: list[dict] = []
    for i, sp_ul in enumerate(luecken, start=1):
        opts_raw = sp_ul.select("span.li[data-num]")
        options: list[dict] = []
        # finde richtig-text aus block[i]
        richtig_text = ""
        erklaerung = ""
        if i in blocks:
            block = blocks[i]
            m = re.match(r"✅\s*\d+\.\s*Richtig[:：]?\s*[„\"']([^\"„»]+)[\"„»']", block)
            if m:
                richtig_text = m.group(1).strip()
            erklaerung = block

        for opt in opts_raw:
            label = (opt.get("data-num") or opt.get_text(" ", strip=True)).strip()
            options.append(
                {
                    "value": "",
                    "label": label,
                    "marked_green": (richtig_text and label == richtig_text),
                    "marked_red": False,
                }
            )

        # Frage-Label: kurzer Kontext rund um die Lücke
        # Vereinfachung: "Lücke N"
        label_text = f"Lücke {i}"
        # Versuche, die nächsten ~10 Wörter Text rund um den Span als Kontext zu extrahieren
        prev = sp_ul.previous_sibling
        nxt = sp_ul.next_sibling
        prev_txt = prev.strip() if isinstance(prev, str) else ""
        nxt_txt = nxt.strip() if isinstance(nxt, str) else ""
        ctx = " ".join(prev_txt.split()[-6:] + ["___"] + nxt_txt.split()[:6]).strip()
        if ctx:
            label_text = f"Lücke {i}: …{ctx}…"

        questions.append(
            {
                "nummer": i,
                "label": label_text,
                "options": options,
                "richtig": f"{i} – Richtige Antwort: „{richtig_text}“" if richtig_text else "",
                "erklaerung": erklaerung,
            }
        )
    return questions


def parse_questions(soup: BeautifulSoup) -> list[dict]:
    """Parse 5 Aufgaben: Label + 3 Optionen + (POST) green = richtig + Erklärung modal."""
    labels = soup.select("p.label.p2_semibold")
    if not labels:
        labels = soup.select("p.label")

    # Explanation modals — sequentiell in der Reihenfolge der Aufgaben.
    # Filter: nur modals mit content "<num> – Richtige Antwort"
    all_modals = soup.select("div.inputmodal__content")
    aufgabe_modals: list[BeautifulSoup] = []
    for m in all_modals:
        txt = m.get_text(" ", strip=True)
        if re.match(r"^\d+\s*[–-]\s*Richtige Antwort", txt):
            aufgabe_modals.append(m)

    questions: list[dict] = []
    for i, label in enumerate(labels, start=1):
        radio = label.find_next("input", {"type": "radio"})
        if not radio:
            continue
        name = radio.get("name", "")

        options: list[dict] = []
        for r in soup.select(f"input[type=radio][name='{name}']"):
            box = r.find_parent("span", class_="input__box")
            classes = box.get("class", []) if box else []
            options.append(
                {
                    "value": r.get("value", ""),
                    "label": (r.get("aria-label", "") or "").strip(),
                    "marked_green": "green" in classes,
                    "marked_red": "red" in classes,
                }
            )

        # Match modal nach Index (i-1)
        richtig = ""
        erklaerung = ""
        if i - 1 < len(aufgabe_modals):
            modal = aufgabe_modals[i - 1]
            full = modal.get_text("\n", strip=True)
            # erste Zeile = "i – Richtige Antwort: x (label)"
            lines = [ln.strip() for ln in full.split("\n") if ln.strip() and ln.strip().lower() != "schließen"]
            if lines:
                richtig = lines[0]
                erklaerung = "\n\n".join(lines[1:])

        questions.append(
            {
                "nummer": i,
                "label": label.get_text(" ", strip=True),
                "options": options,
                "richtig": richtig,
                "erklaerung": erklaerung,
            }
        )
    return questions


def _format_loesung(q: dict) -> str:
    """Grün > richtig-Text fallback."""
    for j, opt in enumerate(q["options"]):
        if opt["marked_green"]:
            letter = chr(ord("a") + j)
            return f"{letter}) {opt['label']}"
    return q["richtig"] or ""


def render_text_md(lesson: Lesson, reading: str) -> str:
    today = date.today().isoformat()
    # YAML-safe chu_de: escape double quotes
    chu_de_safe = lesson.chu_de.replace('"', '\\"')
    teil_desc_safe = lesson.teil_desc.replace('"', '\\"')
    front = (
        f"---\n"
        f"bai: {lesson.bai}\n"
        f"teil: {lesson.teil}\n"
        f"teil_desc: \"{teil_desc_safe}\"\n"
        f"chu_de: \"{chu_de_safe}\"\n"
        f"url: {lesson.url}\n"
        f"extracted_at: {today}\n"
        f"---\n\n"
    )
    body = f"# {lesson.chu_de}\n\n{reading.strip()}\n"
    return front + body


def render_questions_md(lesson: Lesson, questions: list[dict]) -> str:
    lines = [
        f"# Aufgabe {lesson.bai} — {lesson.chu_de}",
        "",
        f"Source: {lesson.url}",
        f"Teil: {lesson.teil} ({lesson.teil_desc})",
        "",
    ]
    for q in questions:
        lines.append(f"## Frage {q['nummer']}")
        lines.append("")
        lines.append(q["label"])
        lines.append("")
        for j, opt in enumerate(q["options"]):
            letter = chr(ord("a") + j)
            mark = ""
            if opt["marked_green"]:
                mark = "  **(richtig)**"
            elif opt["marked_red"]:
                mark = "  *(falsch)*"
            lines.append(f"- {letter}) {opt['label']}{mark}")
        lines.append("")
        loesung = _format_loesung(q)
        if loesung:
            lines.append(f"**Lösung:** {loesung}")
            lines.append("")
        if q["erklaerung"]:
            lines.append("**Erklärung:**")
            lines.append("")
            lines.append(q["erklaerung"])
            lines.append("")
    return "\n".join(lines).rstrip() + "\n"


def process_lesson(
    session: requests.Session,
    lesson: Lesson,
    out_root: Path,
    logger: logging.Logger,
    force: bool = False,
) -> str:
    folder = out_root / lesson.bai
    text_path = folder / f"{lesson.bai}_text.md"
    q_path = folder / f"{lesson.bai}_questions.md"

    if text_path.exists() and q_path.exists() and not force:
        logger.info("skip\t%s\t(already exists)", lesson.bai)
        return "skip"

    folder.mkdir(parents=True, exist_ok=True)

    try:
        get_resp = http_get(session, lesson.url)
    except Exception as e:
        (folder / "_error.txt").write_text(f"GET error: {e}\n", encoding="utf-8")
        logger.error("error\t%s\tGET %s", lesson.bai, e)
        return "error"

    soup_get = BeautifulSoup(get_resp.text, "lxml")
    reading_text = parse_reading_text(soup_get)

    # Lückentext-Detektion (Teil 5): kein p.label.p2_semibold aber span.ul vorhanden
    is_luecken = bool(soup_get.select("span.ul")) and not soup_get.select("p.label.p2_semibold")

    soup_post = None
    questions: list[dict]
    try:
        post_resp = http_post(session, get_resp.url)
        soup_post = BeautifulSoup(post_resp.text, "lxml")
    except Exception as e:
        (folder / "_error.txt").write_text(f"POST error: {e}\n", encoding="utf-8")
        logger.error("error\t%s\tPOST %s", lesson.bai, e)

    if is_luecken:
        questions = parse_luecken_questions(soup_get, soup_post)
    elif soup_post is not None:
        questions = parse_questions(soup_post)
    else:
        questions = parse_questions(soup_get)

    text_path.write_text(render_text_md(lesson, reading_text), encoding="utf-8")
    q_path.write_text(render_questions_md(lesson, questions), encoding="utf-8")

    logger.info(
        "ok\t%s\ttext_len=%d\tquestions=%d",
        lesson.bai, len(reading_text), len(questions),
    )
    return "ok"


def load_lessons(csv_path: Path) -> list[Lesson]:
    rows: list[Lesson] = []
    with csv_path.open("r", encoding="utf-8", newline="") as f:
        for row in csv.DictReader(f):
            rows.append(
                Lesson(
                    stt=row["stt"].strip(),
                    bai=row["bai"].strip(),
                    chu_de=row["chu_de"].strip(),
                    url=row["url"].strip(),
                    teil=row.get("teil", "").strip(),
                    teil_desc=row.get("teil_desc", "").strip(),
                )
            )
    return rows


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", required=True, type=Path)
    ap.add_argument("--out", required=True, type=Path)
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--test", action="store_true", help="1 Bài / Teil = 1.1, 2.1, 3.1, 4.1, 5.1")
    g.add_argument("--all", action="store_true", help="toàn bộ")
    ap.add_argument("--limit", type=int, default=None)
    ap.add_argument("--force", action="store_true", help="re-scrape bài đã có file")
    args = ap.parse_args()

    args.out.mkdir(parents=True, exist_ok=True)
    log_path = args.out.parent / "lesen_scrape.log"

    logger = logging.getLogger("lesen")
    logger.setLevel(logging.INFO)
    fh = logging.FileHandler(log_path, encoding="utf-8")
    fh.setFormatter(logging.Formatter("%(asctime)s\t%(levelname)s\t%(message)s"))
    logger.addHandler(fh)
    sh = logging.StreamHandler(sys.stdout)
    sh.setFormatter(logging.Formatter("%(message)s"))
    logger.addHandler(sh)

    lessons = load_lessons(args.csv)
    if args.test:
        lessons = [l for l in lessons if l.bai in TEST_BAI]
    if args.limit is not None:
        lessons = lessons[: args.limit]

    logger.info("start\tcount=%d\tmode=%s", len(lessons), "test" if args.test else "all")

    session = make_session()
    counts = {"ok": 0, "skip": 0, "error": 0}
    consecutive_403 = 0
    errors: list[str] = []

    iterator = tqdm(lessons, desc="Lesen", unit="bài") if args.all else lessons
    for i, lesson in enumerate(iterator, start=1):
        try:
            status = process_lesson(session, lesson, args.out, logger, force=args.force)
        except requests.HTTPError as e:
            status = "error"
            if e.response is not None and e.response.status_code == 403:
                consecutive_403 += 1
            errors.append(f"{lesson.bai}: {e}")
        except Exception as e:
            status = "error"
            errors.append(f"{lesson.bai}: {e}")

        counts[status] = counts.get(status, 0) + 1
        if status == "ok":
            consecutive_403 = 0

        if consecutive_403 >= CLOUDFLARE_ABORT_THRESHOLD:
            logger.error("Cloudflare-Block? %d 403 in a row. Stoppe.", consecutive_403)
            break

        if args.all and i % 20 == 0:
            logger.info(
                "progress\t%d/%d\tok=%d skip=%d err=%d",
                i, len(lessons), counts["ok"], counts["skip"], counts["error"],
            )

        if i < len(lessons):
            time.sleep(SLEEP_BETWEEN)

    print()
    print("=== LESEN SCRAPE — Phase A ===")
    print(f"CSV: {args.csv} ({len(load_lessons(args.csv))} bài)")
    print(f"Mode: {'--test' if args.test else '--all'}")
    print("---")
    print(f"Processed : {sum(counts.values())} bài")
    print(f"  ok       : {counts['ok']}")
    print(f"  skip     : {counts['skip']}")
    print(f"  error    : {counts['error']}")
    print(f"Log        : {log_path}")
    print("---")
    if errors:
        print("Bài lỗi:")
        for e in errors:
            print(f"  - {e}")
    else:
        print("Bài lỗi: (không có)")
    return 0 if counts["error"] == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
