"""Hören scraper — deutsch-vorbereitung.com (Phase A).

Per Aufgabe `docs/ai/tasks/HOREN_SCRAPE_PROMPT.md`:
  - GET /uebung-XXXX.html  -> audio src + 6 Aussage modals (mit richtig answer)
  - POST submit=1          -> transcript (div.inputmodal-transcription)
  - Download audio MP3 (fallback: ghi _audio_url.txt)
  - Output je Bài: <bai>.mp3 + <bai>_questions.md + <bai>_transcript.md
"""
from __future__ import annotations

import argparse
import csv
import logging
import re
import sys
import time
from dataclasses import dataclass
from pathlib import Path
from urllib.parse import urljoin

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


@dataclass
class Lesson:
    stt: str
    bai: str
    chu_de: str
    url: str


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
    for attempt, wait in enumerate([0, *RETRY_BACKOFF]):
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


def parse_audio_src(soup: BeautifulSoup) -> str | None:
    a = soup.select_one("audio[src]")
    return a["src"] if a else None


def parse_questions(soup: BeautifulSoup) -> list[dict]:
    """Eine Aussage = ein Block mit Label, 6 Optionen, Modal mit richtig answer + Erklärung."""
    labels = soup.select("p.label.p2_semibold")
    modals = soup.select("div.inputmodal:not(.inputmodal-transcription)")

    # Map modal-id (76851) -> modal element
    modal_by_id: dict[str, BeautifulSoup] = {}
    for m in modals:
        for cls in m.get("class", []):
            if cls.startswith("inputmodal-") and cls != "inputmodal-transcription":
                mid = cls.split("-", 1)[1]
                modal_by_id[mid] = m
                break

    questions: list[dict] = []
    for i, label in enumerate(labels, start=1):
        # tìm radio đầu tiên sau label -> name = "antwortXXXXX"
        radio = label.find_next("input", {"type": "radio"})
        if not radio:
            continue
        name = radio.get("name", "")
        modal_id = name.replace("antwort", "")

        # collect alle radios cùng name (= 6 options cho Aussage này)
        options: list[dict] = []
        for r in soup.select(f"input[type=radio][name='{name}']"):
            box = r.find_parent("span", class_="input__box")
            classes = box.get("class", []) if box else []
            options.append(
                {
                    "value": r.get("value", ""),
                    "label": r.get("aria-label", "").strip(),
                    "marked_green": "green" in classes,
                    "marked_red": "red" in classes,
                }
            )

        # richtig answer + erklärung từ modal
        modal = modal_by_id.get(modal_id)
        richtig = ""
        erklaerung = ""
        if modal:
            h3 = modal.select_one("h3")
            if h3:
                richtig = h3.get_text(" ", strip=True)
            content = modal.select_one(".inputmodal__content")
            if content:
                # bỏ h3, lấy phần còn lại làm erklärung
                erk_parts: list[str] = []
                for child in content.children:
                    if getattr(child, "name", None) == "h3":
                        continue
                    txt = (
                        child.get_text(" ", strip=True)
                        if hasattr(child, "get_text")
                        else str(child).strip()
                    )
                    if txt and txt.lower() != "schließen":
                        erk_parts.append(txt)
                erklaerung = "\n\n".join(erk_parts)

        questions.append(
            {
                "nummer": i,
                "label": label.get_text(strip=True),
                "options": options,
                "richtig": richtig,
                "erklaerung": erklaerung,
            }
        )
    return questions


def parse_transcript(soup: BeautifulSoup) -> str:
    box = soup.select_one("div.inputmodal-transcription .inputmodal__content")
    if not box:
        return ""
    parts: list[str] = []
    for el in box.children:
        if not hasattr(el, "get_text"):
            continue
        txt = el.get_text(" ", strip=True)
        if not txt or txt.lower() == "schließen":
            continue
        parts.append(txt)
    return "\n\n".join(parts)


def _format_loesung(q: dict) -> str:
    """Bevorzuge die grün markierte Option (= richtig), fallback auf h3-Text."""
    for j, opt in enumerate(q["options"]):
        if opt["marked_green"]:
            letter = chr(ord("a") + j)
            return f"{letter}) {opt['label']}"
    return q["richtig"] or ""


def render_questions_md(lesson: Lesson, questions: list[dict]) -> str:
    lines = [
        f"# Aufgabe {lesson.bai} — {lesson.chu_de}",
        "",
        f"Source: {lesson.url}",
        "",
    ]
    for q in questions:
        lines.append(f"## {q['label']}")
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


def render_transcript_md(lesson: Lesson, transcript: str) -> str:
    return (
        f"# Transcript — {lesson.bai} {lesson.chu_de}\n\n"
        f"Source: {lesson.url}\n\n"
        f"{transcript}\n"
    )


def download_audio(session: requests.Session, audio_url: str, dest: Path) -> bool:
    try:
        r = session.get(audio_url, timeout=60, stream=True)
        r.raise_for_status()
        with dest.open("wb") as f:
            for chunk in r.iter_content(chunk_size=64 * 1024):
                if chunk:
                    f.write(chunk)
        return True
    except requests.RequestException:
        return False


def process_lesson(
    session: requests.Session,
    lesson: Lesson,
    out_root: Path,
    no_audio: bool,
    logger: logging.Logger,
) -> str:
    folder = out_root / lesson.bai
    mp3_path = folder / f"{lesson.bai}.mp3"
    url_only_path = folder / f"{lesson.bai}_audio_url.txt"
    q_path = folder / f"{lesson.bai}_questions.md"
    t_path = folder / f"{lesson.bai}_transcript.md"

    # Skip wenn texte schon da (mp3 ODER url_only zählt)
    if q_path.exists() and t_path.exists() and (mp3_path.exists() or url_only_path.exists() or no_audio):
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
    audio_src = parse_audio_src(soup_get)
    audio_full_url = urljoin(get_resp.url, audio_src) if audio_src else None

    # POST response = single source of truth für questions (grün = richtig)
    # UND für transcript. Fallback auf GET wenn POST scheitert.
    questions: list[dict]
    transcript = ""
    try:
        post_resp = http_post(session, get_resp.url)
        soup_post = BeautifulSoup(post_resp.text, "lxml")
        questions = parse_questions(soup_post)
        transcript = parse_transcript(soup_post)
    except Exception as e:
        (folder / "_error.txt").write_text(f"POST error: {e}\n", encoding="utf-8")
        logger.error("error\t%s\tPOST %s", lesson.bai, e)
        questions = parse_questions(soup_get)

    # Schreibe textuelle Outputs
    q_path.write_text(render_questions_md(lesson, questions), encoding="utf-8")
    t_path.write_text(render_transcript_md(lesson, transcript), encoding="utf-8")

    # Audio
    audio_status = "skipped"
    if audio_full_url:
        if no_audio:
            url_only_path.write_text(audio_full_url + "\n", encoding="utf-8")
            audio_status = "url_only"
        else:
            ok = download_audio(session, audio_full_url, mp3_path)
            if ok:
                audio_status = "downloaded"
            else:
                url_only_path.write_text(audio_full_url + "\n", encoding="utf-8")
                audio_status = "failed"
                logger.warning("audio_failed\t%s\t%s", lesson.bai, audio_full_url)

    logger.info("ok\t%s\taudio=%s\tquestions=%d\ttranscript_len=%d",
                lesson.bai, audio_status, len(questions), len(transcript))
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
                )
            )
    return rows


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", required=True, type=Path)
    ap.add_argument("--out", required=True, type=Path)
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--test", action="store_true", help="3 Bài đầu")
    g.add_argument("--all", action="store_true", help="toàn bộ")
    ap.add_argument("--no-audio", action="store_true", help="chỉ ghi _audio_url.txt")
    ap.add_argument("--limit", type=int, default=None, help="override số bài")
    args = ap.parse_args()

    args.out.mkdir(parents=True, exist_ok=True)
    log_path = args.out.parent / "horen_scrape.log"

    logger = logging.getLogger("horen")
    logger.setLevel(logging.INFO)
    fh = logging.FileHandler(log_path, encoding="utf-8")
    fh.setFormatter(logging.Formatter("%(asctime)s\t%(levelname)s\t%(message)s"))
    logger.addHandler(fh)
    sh = logging.StreamHandler(sys.stdout)
    sh.setFormatter(logging.Formatter("%(message)s"))
    logger.addHandler(sh)

    lessons = load_lessons(args.csv)
    if args.test:
        lessons = lessons[:3]
    if args.limit is not None:
        lessons = lessons[: args.limit]

    logger.info("start\tcount=%d\tmode=%s", len(lessons), "test" if args.test else "all")

    session = make_session()
    counts = {"ok": 0, "skip": 0, "error": 0}
    audio_counts = {"downloaded": 0, "url_only": 0, "failed": 0, "skipped": 0}
    consecutive_403 = 0
    errors: list[str] = []

    iterator = tqdm(lessons, desc="Hören", unit="bài") if args.all else lessons
    for i, lesson in enumerate(iterator, start=1):
        try:
            status = process_lesson(session, lesson, args.out, args.no_audio, logger)
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

        # audio status nachschauen (file existence)
        folder = args.out / lesson.bai
        if (folder / f"{lesson.bai}.mp3").exists():
            audio_counts["downloaded"] += 1
        elif (folder / f"{lesson.bai}_audio_url.txt").exists():
            if args.no_audio:
                audio_counts["url_only"] += 1
            else:
                audio_counts["failed"] += 1
        else:
            audio_counts["skipped"] += 1

        if consecutive_403 >= CLOUDFLARE_ABORT_THRESHOLD:
            logger.error("Cloudflare-Block? %d 403 in a row. Stoppe.", consecutive_403)
            break

        if args.all and i % 10 == 0:
            logger.info("progress\t%d/%d\tok=%d skip=%d err=%d",
                        i, len(lessons), counts["ok"], counts["skip"], counts["error"])

        if i < len(lessons):
            time.sleep(SLEEP_BETWEEN)

    # Report
    print()
    print("=== HOREN SCRAPE — Phase A ===")
    print(f"CSV: {args.csv} ({len(load_lessons(args.csv))} bài)")
    print(f"Mode: {'--test' if args.test else '--all'}{' --no-audio' if args.no_audio else ''}")
    print("---")
    print(f"Processed : {sum(counts.values())} bài")
    print(f"  ok       : {counts['ok']}")
    print(f"  skip     : {counts['skip']}")
    print(f"  error    : {counts['error']}")
    print(
        f"Audio      : {audio_counts['downloaded']} files downloaded "
        f"/ {audio_counts['url_only']} skipped (url_only) "
        f"/ {audio_counts['failed']} failed"
    )
    print(f"Log        : {log_path}")
    print("---")
    if errors:
        print("Bài lỗi:")
        for e in errors:
            print(f"  - {e}")
    else:
        print("Bài lỗi: (không có)")
    print('Next step: chạy --all khi --test pass, sau đó "đóng vai Vocab Extractor" để bóc vocab')

    return 0 if counts["error"] == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
