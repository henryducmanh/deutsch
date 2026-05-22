#!/usr/bin/env python3
"""
scan_extract.py — Deutsch HTML Vocab Pipeline
==============================================
Phần plumbing: tìm file chưa xử lý, HTML→Markdown, ghi CSV.
Phần AI (bóc vocab thông minh): do Claude trong scheduled task đảm nhận.

Usage:
  python scan_extract.py --scan                        # in JSON danh sách file chưa xử lý
  python scan_extract.py --to-md FILE                  # HTML→Markdown, in ra stdout
  python scan_extract.py --mark FILE [--rows N] [--chunks K] [--note TEXT]
                                                       # đánh dấu processed_files.csv
  python scan_extract.py --next-id vocab               # sinh ID tiếp theo VOC-YYYYMMDD-NNN
  python scan_extract.py --next-id chunk               # sinh ID tiếp theo CH-YYYYMMDD-NNN

Paths (tất cả relative đến ROOT = thư mục cha của module/scan_extract/):
  input/html/<book>/<skill>/<exercise-id>.html   → nguồn
  output/lessons/<book>/<skill>/<exercise-id>.md → lesson đã trích xuất
  data/03_unified/vocab_master.csv               → vocab (append)
  data/chunks_master.csv                         → chunks (append)
  data/processed_files.csv                       → log (append)
"""

import argparse
import csv
import json
import os
import re
import sys
from datetime import datetime
from pathlib import Path

# ── Paths ──────────────────────────────────────────────────────────────────────
HERE = Path(__file__).resolve().parent          # module/scan_extract/
ROOT = HERE.parent.parent                        # deutsch/

INPUT_DIR   = ROOT / "input" / "html"
OUTPUT_DIR  = ROOT / "output" / "lessons"
VOCAB_CSV   = ROOT / "data" / "03_unified" / "vocab_master.csv"
CHUNKS_CSV  = ROOT / "data" / "chunks_master.csv"
PROC_CSV    = ROOT / "data" / "processed_files.csv"


# ── Helpers ────────────────────────────────────────────────────────────────────

def load_processed() -> set:
    """Trả về set filepath đã được xử lý thành công."""
    processed = set()
    if not PROC_CSV.exists():
        return processed
    with open(PROC_CSV, newline="", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line.startswith("#") or not line:
                continue
            parts = line.split(",")
            if parts and parts[0]:
                processed.add(parts[0].strip())
    return processed


def html_to_md(html_path: Path) -> str:
    """Chuyển HTML → Markdown thuần (không dùng thư viện nặng)."""
    try:
        import html as html_module
        content = html_path.read_text(encoding="utf-8", errors="replace")

        # Bỏ script / style / head
        content = re.sub(r"<script[^>]*>.*?</script>", "", content, flags=re.DOTALL | re.IGNORECASE)
        content = re.sub(r"<style[^>]*>.*?</style>", "", content, flags=re.DOTALL | re.IGNORECASE)
        content = re.sub(r"<head[^>]*>.*?</head>", "", content, flags=re.DOTALL | re.IGNORECASE)
        content = re.sub(r"<!--.*?-->", "", content, flags=re.DOTALL)

        # Headings
        for i in range(6, 0, -1):
            content = re.sub(rf"<h{i}[^>]*>(.*?)</h{i}>",
                             lambda m, lvl=i: "\n" + "#" * lvl + " " + _strip_tags(m.group(1)) + "\n",
                             content, flags=re.DOTALL | re.IGNORECASE)

        # Block elements → newline
        content = re.sub(r"<(p|div|li|tr|br|hr)[^>]*>", "\n", content, flags=re.IGNORECASE)
        content = re.sub(r"</(p|div|li|tr)>", "\n", content, flags=re.IGNORECASE)

        # Bold / italic
        content = re.sub(r"<(strong|b)[^>]*>(.*?)</(strong|b)>",
                         lambda m: f"**{_strip_tags(m.group(2))}**", content, flags=re.DOTALL | re.IGNORECASE)
        content = re.sub(r"<(em|i)[^>]*>(.*?)</(em|i)>",
                         lambda m: f"_{_strip_tags(m.group(2))}_", content, flags=re.DOTALL | re.IGNORECASE)

        # Strip remaining tags
        content = re.sub(r"<[^>]+>", "", content)

        # HTML entities
        content = html_module.unescape(content)

        # Clean whitespace
        lines = [ln.rstrip() for ln in content.splitlines()]
        result_lines = []
        blank = 0
        for ln in lines:
            if ln == "":
                blank += 1
                if blank <= 2:
                    result_lines.append("")
            else:
                blank = 0
                result_lines.append(ln)

        return "\n".join(result_lines).strip()

    except Exception as e:
        return f"[ERROR converting HTML: {e}]"


def _strip_tags(text: str) -> str:
    return re.sub(r"<[^>]+>", "", text).strip()


def parse_file_path(html_path: Path):
    """
    Lấy (book, skill, exercise_id) từ đường dẫn.
    Ví dụ: input/html/deutsch-vorbereitung/horen/B1-DTZ-H-001.html
      → book='deutsch-vorbereitung', skill='horen', exercise_id='B1-DTZ-H-001'
    """
    try:
        rel = html_path.relative_to(INPUT_DIR)
        parts = rel.parts
        if len(parts) >= 3:
            return parts[0], parts[1], html_path.stem
        elif len(parts) == 2:
            return parts[0], "misc", html_path.stem
        else:
            return "unknown", "misc", html_path.stem
    except Exception:
        return "unknown", "misc", html_path.stem


def next_id(prefix: str, csv_path: Path) -> str:
    """Sinh ID tiếp theo: VOC-YYYYMMDD-NNN hoặc CH-YYYYMMDD-NNN."""
    today = datetime.now().strftime("%Y%m%d")
    pattern = re.compile(rf"^{re.escape(prefix)}-{today}-(\d+)")
    max_n = 0
    if csv_path.exists():
        with open(csv_path, encoding="utf-8", errors="replace") as f:
            for line in f:
                m = pattern.match(line.strip())
                if m:
                    max_n = max(max_n, int(m.group(1)))
    return f"{prefix}-{today}-{max_n + 1:03d}"


# ── Commands ───────────────────────────────────────────────────────────────────

def cmd_scan():
    """In JSON: danh sách file HTML chưa xử lý."""
    processed = load_processed()
    unprocessed = []

    for html_file in sorted(INPUT_DIR.rglob("*.html")):
        rel = str(html_file.relative_to(ROOT)).replace("\\", "/")
        if rel not in processed:
            book, skill, ex_id = parse_file_path(html_file)
            unprocessed.append({
                "filepath": rel,
                "abs_path": str(html_file),
                "book": book,
                "skill": skill,
                "exercise_id": ex_id,
                "lesson_out": str(OUTPUT_DIR / book / skill / (ex_id + ".md")).replace("\\", "/"),
            })

    print(json.dumps({"count": len(unprocessed), "files": unprocessed}, ensure_ascii=False, indent=2))


def cmd_to_md(html_file_str: str):
    """HTML → Markdown, in ra stdout. Cũng ghi file lesson output."""
    html_path = Path(html_file_str).resolve()
    if not html_path.exists():
        # Thử resolve từ ROOT
        html_path = ROOT / html_file_str
    if not html_path.exists():
        print(f"[ERROR] File not found: {html_file_str}", file=sys.stderr)
        sys.exit(1)

    book, skill, ex_id = parse_file_path(html_path)
    md_content = html_to_md(html_path)

    # Header metadata
    header = f"""---
source: {html_path.relative_to(ROOT).as_posix() if html_path.is_relative_to(ROOT) else html_file_str}
book: {book}
skill: {skill}
exercise_id: {ex_id}
extracted_at: {datetime.now().strftime("%Y-%m-%d %H:%M")}
---

# [{book}] {skill.capitalize()} — {ex_id}

"""
    full_md = header + md_content

    # Ghi file lesson
    out_path = OUTPUT_DIR / book / skill / (ex_id + ".md")
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(full_md, encoding="utf-8")

    # In ra stdout (Claude đọc để bóc vocab)
    print(full_md)
    print(f"\n[LESSON_OUT] {out_path.relative_to(ROOT).as_posix()}", file=sys.stderr)


def cmd_mark(html_file_str: str, rows: int, chunks: int, note: str):
    """Append processed_files.csv."""
    rel = html_file_str.replace("\\", "/")
    # Chuẩn hoá về relative từ ROOT
    try:
        abs_p = Path(html_file_str).resolve()
        rel = str(abs_p.relative_to(ROOT)).replace("\\", "/")
    except Exception:
        pass

    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    line = f"{rel},{now},vocab-extractor,{rows},{rows + chunks},ok,{note}\n"

    # Tạo header nếu file chưa có
    if not PROC_CSV.exists():
        PROC_CSV.parent.mkdir(parents=True, exist_ok=True)
        with open(PROC_CSV, "w", encoding="utf-8") as f:
            f.write("# schema_version: 2026-05-18_v1\n")
            f.write("# columns: filepath, processed_at, agent, output_rows, output_files, status, note\n")
            f.write("# agent enum: vocab-extractor | mistake-auditor | listening-coach | speaking-coach | other\n")
            f.write("# status enum: ok | partial | failed\n")
            f.write("# Append-only.\n")

    with open(PROC_CSV, "a", encoding="utf-8") as f:
        f.write(line)

    print(f"[MARKED] {rel} → processed_files.csv (vocab_rows={rows}, chunk_rows={chunks})")


def cmd_next_id(kind: str):
    if kind == "vocab":
        print(next_id("VOC", VOCAB_CSV))
    elif kind == "chunk":
        print(next_id("CH", CHUNKS_CSV))
    else:
        print(f"[ERROR] kind phải là 'vocab' hoặc 'chunk'", file=sys.stderr)
        sys.exit(1)


# ── Main ───────────────────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(description="Deutsch HTML Vocab Pipeline — plumbing")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--scan",    action="store_true", help="In JSON danh sách file chưa xử lý")
    group.add_argument("--to-md",   metavar="FILE",      help="HTML → Markdown, ghi lesson output")
    group.add_argument("--mark",    metavar="FILE",      help="Đánh dấu file đã processed")
    group.add_argument("--next-id", metavar="KIND",      help="Sinh next ID (vocab|chunk)")

    parser.add_argument("--rows",   type=int, default=0, help="Số vocab rows đã thêm (dùng với --mark)")
    parser.add_argument("--chunks", type=int, default=0, help="Số chunk rows đã thêm (dùng với --mark)")
    parser.add_argument("--note",   default="",          help="Note tự do (dùng với --mark)")

    args = parser.parse_args()

    if args.scan:
        cmd_scan()
    elif args.to_md:
        cmd_to_md(args.to_md)
    elif args.mark:
        cmd_mark(args.mark, args.rows, args.chunks, args.note)
    elif args.next_id:
        cmd_next_id(args.next_id)


if __name__ == "__main__":
    main()
