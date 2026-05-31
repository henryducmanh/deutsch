"""Hören Lesson JSON Generator — Phase A (series 4.x).

Detect bài Hören 4.x đã scrape → generate lesson JSON cho deutsch_web.

Input  : input/html/deutsch-vorbereitung/horen/4.*/
         ├── {id}_questions.md    (aussagen + đáp án **(richtig)**)
         ├── {id}_transcript.md   (transcript, marker Aussage/Nummer/Nr. N)
         ├── url.md               (LingQ meta — có hoặc không)
         └── {id}.mp3             (audio local)
Output : module/deutsch_web/lessons/{id}.json
Schema : deutsch_web_lesson_v1 (canonical: module/deutsch_web/lessons/4.31.json)

Audio / LingQ meta lookup priority: url.md → data/lingq_lessons.csv → null.

Flags:
    --dry-run     in plan, KHÔNG ghi file (default)
    --apply       ghi JSON thật
    --id 4.2      chỉ 1 bài (debug)
    --force       overwrite JSON đã tồn tại (mặc định skip)
    --series 4    series xử lý (default 4, scope Phase A)

KHÔNG sửa lessons_push.php / lingq_client.php / deutsch_web/*.php.
data/lingq_lessons.csv chỉ READ. vocab = [] (Vocab Extractor điền sau).
"""
from __future__ import annotations

import argparse
import csv
import json
import logging
import os
import re
import sys
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
HOREN_ROOT = ROOT / "input" / "html" / "deutsch-vorbereitung" / "horen"
LESSONS_DIR = ROOT / "module" / "deutsch_web" / "lessons"
LINGQ_CSV = ROOT / "data" / "lingq_lessons.csv"
LOG_DIR = Path(__file__).resolve().parent / "logs"

INSTRUCTIONS = (
    "Sie hören Aussagen zu einem Thema. Welcher der Sätze a–f passt zu den "
    "Aussagen? Lesen Sie jetzt die Sätze a–f. Dazu haben Sie eine Minute Zeit. "
    "Danach hören Sie die Aussagen."
)
NOTE_VOCAB_ID = (
    "null = chưa link vocab_master. Vai Vocab Extractor / deutsch_web_sync điền "
    "sau khi match wort trong data/03_unified/vocab_master.csv. KHÔNG bịa ID."
)
NOTE_LV = (
    "lv = trạng thái panel (new/ok/hard) ánh xạ lv-new/lv-ok/lv-hard trong drill.css."
)

# Marker tách segment transcript: "Aussage 1" / "Nummer 1" / "Nr. 1"
TRANSCRIPT_MARKER = re.compile(r"(?:Aussage|Nummer|Nr\.)\s+(\d+)\b")
OPTION_LINE = re.compile(r"^-\s*([a-f])\)\s*(.+?)\s*$", re.MULTILINE)
RICHTIG = re.compile(r"\*{0,2}\(\s*richtig\s*\)\*{0,2}", re.IGNORECASE)

# Windows console mặc định cp1252 → ép UTF-8 cho ký tự Đức/Việt + mũi tên
# (đặt ở top-level để --help / argparse cũng in được).
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8")
    except (AttributeError, ValueError):
        pass

logger = logging.getLogger("horen_json")
logger.setLevel(logging.INFO)


def setup_logging() -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    date = datetime.now().strftime("%Y%m%d")
    fh = logging.FileHandler(LOG_DIR / f"horen_lesson_json_{date}.log", encoding="utf-8")
    fh.setFormatter(logging.Formatter("%(asctime)s\t%(levelname)s\t%(message)s"))
    logger.addHandler(fh)


def clean(text: str) -> str:
    """Collapse whitespace + strip."""
    return re.sub(r"\s+", " ", text).strip()


def key_phrase(text: str) -> str:
    """Câu đầu tiên + '.', cắt ở từ cuối nếu > 120 chars."""
    first = text.split(".", 1)[0].strip()
    if not first:
        return ""
    first = first + "."
    if len(first) > 120:
        first = first[:120].rsplit(" ", 1)[0].rstrip(",;:") + "…"
    return first


# ── CSV lookup (đọc 1 lần) ────────────────────────────────────────────────
def load_lingq_csv() -> dict[str, dict]:
    """Map source_local → row. utf-8-sig để bỏ BOM."""
    table: dict[str, dict] = {}
    if not LINGQ_CSV.exists():
        logger.warning("lingq_lessons.csv không tồn tại: %s", LINGQ_CSV)
        return table
    with open(LINGQ_CSV, encoding="utf-8-sig", newline="") as f:
        for row in csv.DictReader(f):
            sl = (row.get("source_local") or "").strip()
            if sl:
                table[sl] = row
    return table


def get_audio_url(lesson_id: str, url_md: str | None, csv_row: dict | None):
    """(url, host) — ưu tiên url.md → csv → (None, 'none')."""
    if url_md:
        m = re.search(r"^-\s*Audio:\s*(https://\S+)", url_md, re.MULTILINE)
        if m:
            return m.group(1), "lingq_s3"
    if csv_row and csv_row.get("audio_url"):
        url = csv_row["audio_url"].strip()
        host = "lingq_s3" if "s3.amazonaws.com" in url else "lingq"
        return url, host
    return None, "none"


def get_lingq_meta(lesson_id: str, url_md: str | None, csv_row: dict | None):
    """(lesson_id, course_id, reader_url) — ưu tiên url.md → csv → (None,)*3."""
    if url_md:
        lid = re.search(r"^-\s*lesson_id:\s*(\d+)", url_md, re.MULTILINE)
        cid = re.search(r"^-\s*course_id:\s*(\d+)", url_md, re.MULTILINE)
        reader = re.search(r"^-\s*Lesson:\s*(https://\S+)", url_md, re.MULTILINE)
        if lid:
            lesson = int(lid.group(1))
            course = int(cid.group(1)) if cid else None
            reader_url = reader.group(1) if reader else (
                f"https://www.lingq.com/en/learn/de/web/reader/{lesson}/"
            )
            return lesson, course, reader_url
    if csv_row and csv_row.get("lesson_id"):
        lesson = int(csv_row["lesson_id"])
        course = int(csv_row["course_id"]) if csv_row.get("course_id") else None
        reader_url = f"https://www.lingq.com/en/learn/de/web/reader/{lesson}/"
        return lesson, course, reader_url
    return None, None, None


# ── Parse questions ───────────────────────────────────────────────────────
def parse_questions(text: str, lesson_id: str):
    """(thema, origin_url, aussagen[])."""
    # Header: "# Aufgabe {id} — {thema}"  (tách bằng em-dash, thema có thể chứa '-')
    thema = ""
    hm = re.search(r"^#\s*Aufgabe\b[^\n]*?—\s*(.+?)\s*$", text, re.MULTILINE)
    if hm:
        thema = hm.group(1).strip()
    else:  # fallback: bất kỳ dash nào
        hm2 = re.search(r"^#\s*Aufgabe\s+[\d.]+\s*[-–—]\s*(.+?)\s*$", text, re.MULTILINE)
        if hm2:
            thema = hm2.group(1).strip()

    sm = re.search(r"^Source:\s*(\S+)", text, re.MULTILINE)
    origin_url = sm.group(1).strip() if sm else ""

    # Split theo "## Aussage N"
    headers = list(re.finditer(r"^##\s*Aussage\s+(\d+)\b", text, re.MULTILINE))

    # Pass 1: parse từng block (correct + options block đó thấy).
    # Các Aussage chia chung 1 ngân hàng câu a–f; nguồn scrape đôi khi
    # bỏ sót option ở block đầu (vd 4.4 thiếu f) → gom union để mỗi
    # Aussage đều có đủ a–f giống canonical 4.31.json.
    blocks = []  # (n, correct)
    bank: dict[str, str] = {}
    for i, h in enumerate(headers):
        n = h.group(1)
        block_start = h.end()
        block_end = headers[i + 1].start() if i + 1 < len(headers) else len(text)
        block = text[block_start:block_end]

        correct = ""
        for om in OPTION_LINE.finditer(block):
            key = om.group(1)
            raw = om.group(2)
            if RICHTIG.search(raw):
                correct = key
            opt_text = clean(RICHTIG.sub("", raw))
            if key not in bank and opt_text:
                bank[key] = opt_text
        if not correct:
            logger.warning("[%s] Aussage %s: không tìm thấy **(richtig)**", lesson_id, n)
        blocks.append((n, correct))

    options = [{"key": k, "text": bank[k]} for k in sorted(bank)]
    aussagen = [
        {
            "id": f"{lesson_id}-{n}",
            "label": f"Aussage {n}",
            "correct": correct,
            "options": options,
        }
        for n, correct in blocks
    ]
    return thema, origin_url, aussagen


# ── Parse transcript ──────────────────────────────────────────────────────
def parse_transcript(text: str, lesson_id: str):
    """transcript[] + flag warn (split failed).

    Format output:
      - Nếu có intro (trước marker đầu tiên) → entry đầu label="Einleitung", key_phrase=""
      - Mỗi Aussage: label="Aussage N", text = FULL raw text của segment đó (không tóm tắt),
        key_phrase = câu đầu tiên (cho UI bôi đậm)
    Mục tiêu: giữ nguyên gốc transcript để học từ vựng + đối chiếu âm thanh.
    """
    # Bỏ header "# Transcript ..." + "Source: ..." → body
    body = text
    body = re.sub(r"^#\s*Transcript[^\n]*\n", "", body, count=1)
    body = re.sub(r"^Source:[^\n]*\n", "", body, count=1, flags=re.MULTILINE)
    body = body.strip()

    markers = list(TRANSCRIPT_MARKER.finditer(body))
    if not markers:
        logger.warning("transcript split failed for %s, using full text", lesson_id)
        return [{"label": "Transkription", "text": clean(body), "key_phrase": ""}], True

    segments = []

    # Intro: phần text TRƯỚC marker đầu tiên (phần giới thiệu chủ đề)
    intro_text = clean(body[: markers[0].start()])
    if intro_text:
        segments.append({"label": "Einleitung", "text": intro_text, "key_phrase": ""})

    # Từng Aussage: lấy text từ SAU marker đến trước marker tiếp theo
    # (marker bản thân — "Nr. 1", "Aussage 2" — không đưa vào text vì label đã có)
    for i, m in enumerate(markers):
        n = m.group(1)
        seg_start = m.end()
        seg_end = markers[i + 1].start() if i + 1 < len(markers) else len(body)
        seg_text = clean(body[seg_start:seg_end])
        segments.append({
            "label": f"Aussage {n}",
            "text": seg_text,
            "key_phrase": key_phrase(seg_text),
        })
    return segments, False


# ── Build 1 lesson ────────────────────────────────────────────────────────
def build_lesson(lesson_id: str, csv_table: dict[str, dict], date: str,
                 old_json: "dict | None" = None):
    """Build lesson dict.

    old_json: nội dung JSON cũ (đọc trước khi --force overwrite).
    Merge strategy khi old_json không None:
      - lingq_lesson_id/course_id/reader_url: nếu lookup mới trả None → giữ giá trị cũ
      - audio.url/host: nếu lookup mới trả None → giữ giá trị cũ
      - vocab: nếu old_json có vocab non-empty → giữ nguyên (ưu tiên curated data)
    Lý do: lingq_lessons.csv có thể mất entry 4.x sau khi lessons_sync.php rebuild,
    nhưng JSON cũ vẫn có đúng lesson_id + audio URL.
    """
    folder = HOREN_ROOT / lesson_id
    q_path = folder / f"{lesson_id}_questions.md"
    t_path = folder / f"{lesson_id}_transcript.md"
    url_path = folder / "url.md"

    if not q_path.exists():
        raise FileNotFoundError(f"thiếu {q_path}")

    q_text = q_path.read_text(encoding="utf-8")
    thema, origin_url, aussagen = parse_questions(q_text, lesson_id)

    transcript, warn = ([], False)
    if t_path.exists():
        transcript, warn = parse_transcript(t_path.read_text(encoding="utf-8"), lesson_id)
    else:
        logger.warning("[%s] thiếu transcript file", lesson_id)
        warn = True

    url_md = None
    if url_path.exists() and url_path.stat().st_size > 0:
        url_md = url_path.read_text(encoding="utf-8")
    source_local = f"input/html/deutsch-vorbereitung/horen/{lesson_id}/"
    csv_row = csv_table.get(source_local)

    audio_url, audio_host = get_audio_url(lesson_id, url_md, csv_row)
    lingq_lid, lingq_cid, reader_url = get_lingq_meta(lesson_id, url_md, csv_row)

    # ── Merge strategy: giữ giá trị cũ nếu lookup mới trả None ──────────
    if old_json:
        old_src = old_json.get("source", {})
        old_audio = old_json.get("audio", {})

        if lingq_lid is None and old_src.get("lingq_lesson_id"):
            lingq_lid = old_src["lingq_lesson_id"]
            lingq_cid = old_src.get("lingq_course_id")
            reader_url = old_src.get("lingq_reader_url")
            logger.info("[%s] merge: lingq_lesson_id=%s từ JSON cũ", lesson_id, lingq_lid)

        if audio_url is None and old_audio.get("url"):
            audio_url = old_audio["url"]
            audio_host = old_audio.get("host", "lingq_s3")
            logger.info("[%s] merge: audio.url từ JSON cũ", lesson_id)

    # Vocab: giữ nguyên nếu cũ đã có data curated (vocab: [] = trống = không giữ)
    vocab = []
    if old_json and old_json.get("vocab"):
        vocab = old_json["vocab"]
        logger.info("[%s] merge: vocab %d từ từ JSON cũ", lesson_id, len(vocab))

    lesson = {
        "schema_version": "deutsch_web_lesson_v1",
        "lesson_id": lesson_id,
        "aufgabe": lesson_id,
        "modul": "Hören",
        "niveau": "B1",
        "thema": thema,
        "title": thema,
        "instructions": INSTRUCTIONS,
        "source": {
            "origin_url": origin_url,
            "lingq_lesson_id": lingq_lid,
            "lingq_course_id": lingq_cid,
            "lingq_reader_url": reader_url,
        },
        "audio": {
            "url": audio_url,
            "host": audio_host,
            "local_path": f"input/html/deutsch-vorbereitung/horen/{lesson_id}/{lesson_id}.mp3",
        },
        "aussagen": aussagen,
        "transcript": transcript,
        "vocab": vocab,
        "_meta": {
            "note_vocab_id": NOTE_VOCAB_ID,
            "note_lv": NOTE_LV,
            "generated_by": f"horen_to_lesson_json.py Phase A {date}",
        },
    }
    info = {
        "audio_host": audio_host,
        "aussagen": len(aussagen),
        "transcript_ok": not warn,
    }
    return lesson, info


def atomic_write_json(path: Path, data: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(".json.tmp")
    tmp.write_text(
        json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    os.replace(tmp, path)


def discover_ids(series: str) -> list[str]:
    ids = []
    if not HOREN_ROOT.exists():
        return ids
    for d in HOREN_ROOT.iterdir():
        if d.is_dir() and d.name.startswith(f"{series}."):
            ids.append(d.name)

    def numkey(i: str):
        try:
            return tuple(int(p) for p in i.split("."))
        except ValueError:
            return (10**9,)

    return sorted(ids, key=numkey)


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="Hören Lesson JSON Generator (Phase A, 4.x)")
    g = ap.add_mutually_exclusive_group()
    g.add_argument("--dry-run", action="store_true", help="in plan, không ghi (default)")
    g.add_argument("--apply", action="store_true", help="ghi JSON thật")
    ap.add_argument("--id", help="chỉ xử lý 1 bài (vd 4.2)")
    ap.add_argument("--force", action="store_true", help="overwrite JSON đã tồn tại")
    ap.add_argument("--series", default="4", help="series xử lý (default 4)")
    args = ap.parse_args(argv)

    apply = args.apply
    dry = not apply  # default dry-run

    setup_logging()
    date = datetime.now().strftime("%Y-%m-%d")
    csv_table = load_lingq_csv()

    ids = [args.id] if args.id else discover_ids(args.series)

    if dry:
        print("=== HÖREN LESSON JSON GENERATOR — DRY RUN ===")
        print(f"Input : input/html/deutsch-vorbereitung/horen/{args.series}.*/")
        print(f"Output: module/deutsch_web/lessons/")
        print("---")
    else:
        print("=== HÖREN LESSON JSON GENERATOR ===")

    n_gen = n_skip = n_err = 0
    no_audio: list[str] = []
    warn_split: list[str] = []

    for lesson_id in ids:
        out_path = LESSONS_DIR / f"{lesson_id}.json"
        if out_path.exists() and not args.force:
            n_skip += 1
            if dry:
                rel = out_path.relative_to(ROOT).as_posix()
                print(f"{lesson_id:<6}→ SKIP      (already exists: {rel})")
            continue

        # Đọc JSON cũ trước khi overwrite (cho merge strategy)
        old_json = None
        if args.force and out_path.exists():
            try:
                old_json = json.loads(out_path.read_text(encoding="utf-8"))
            except Exception:
                pass  # JSON cũ lỗi → bỏ qua, không merge

        try:
            lesson, info = build_lesson(lesson_id, csv_table, date, old_json=old_json)
        except Exception as exc:  # noqa: BLE001
            n_err += 1
            logger.error("[%s] build lỗi: %s", lesson_id, exc)
            print(f"{lesson_id:<6}→ ERROR     {exc}")
            continue

        if info["audio_host"] == "none":
            no_audio.append(lesson_id)
        if not info["transcript_ok"]:
            warn_split.append(lesson_id)

        if dry:
            t_state = "ok" if info["transcript_ok"] else "WARN"
            print(
                f"{lesson_id:<6}→ GENERATE  (audio: {info['audio_host']} | "
                f"aussagen: {info['aussagen']} | transcript: {t_state})"
            )
            n_gen += 1
        else:
            atomic_write_json(out_path, lesson)
            rel = out_path.relative_to(ROOT).as_posix()
            print(f"{lesson_id:<6}→ OK    {rel}")
            n_gen += 1

    print("---")
    if dry:
        print(f"TOTAL: {n_gen} to generate, {n_skip} skip, {n_err} errors")
        if no_audio:
            print(f"  (audio.host=none: {', '.join(no_audio)})")
        if warn_split:
            print(f"  (transcript split fail: {', '.join(warn_split)})")
        print("Run with --apply to write files.")
    else:
        print(f"Generated: {n_gen}  Skipped: {n_skip}  Errors: {n_err}")
        if no_audio:
            print(f"  (audio.host=none: {', '.join(no_audio)})")
        if warn_split:
            print(f"  (transcript split fail: {', '.join(warn_split)})")
        print(
            "Next: git add module/deutsch_web/lessons/*.json && git commit "
            "&& git pull trên server"
        )

    return 1 if n_err else 0


if __name__ == "__main__":
    sys.exit(main())
