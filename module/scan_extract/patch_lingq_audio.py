"""patch_lingq_audio.py — Patch lingq_lesson_id + audio.url vào lesson JSON.

Đọc data/lingq_lessons.csv, match source_local với lesson JSON,
cập nhật CHỈ các field LingQ/audio. Giữ nguyên transcript, vocab, aussagen.

Chạy SAU KHI:
  1. lessons_push.php --batch ... --apply  (push bài lên LingQ)
  2. lessons_sync.php                       (cập nhật lingq_lessons.csv)

Flags:
  --dry-run    in plan, KHÔNG ghi file (default)
  --apply      ghi thật
  --id 1.5     chỉ patch 1 bài (debug)

Ghi an toàn: /tmp → shutil.copy2 (tránh Windows mount truncation cho file dài).
"""
from __future__ import annotations

import argparse
import csv
import json
import logging
import os
import shutil
import sys
import tempfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
LESSONS_DIR = ROOT / "module" / "deutsch_web" / "lessons"
LINGQ_CSV = ROOT / "data" / "lingq_lessons.csv"
LOG_DIR = Path(__file__).resolve().parent / "logs"

for _s in (sys.stdout, sys.stderr):
    try: _s.reconfigure(encoding="utf-8")
    except (AttributeError, ValueError): pass

logger = logging.getLogger("patch_lingq")
logger.setLevel(logging.INFO)


def setup_logging() -> None:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    date = datetime.now().strftime("%Y%m%d")
    fh = logging.FileHandler(LOG_DIR / f"patch_lingq_{date}.log", encoding="utf-8")
    fh.setFormatter(logging.Formatter("%(asctime)s\t%(levelname)s\t%(message)s"))
    logger.addHandler(fh)


def load_csv() -> dict[str, dict]:
    """Map source_local → CSV row."""
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


def safe_write(path: Path, data: dict) -> None:
    """Ghi JSON: temp trong cùng thư mục → os.replace() atomic.
    Không dùng cross-dir copy (shutil.copy2 từ AppData→FUSE mount bị truncate).
    """
    content = json.dumps(data, ensure_ascii=False, indent=2) + "\n"
    tmp = path.with_suffix(".json.tmp")
    tmp.write_text(content, encoding="utf-8")
    os.replace(tmp, path)


def patch_one(lesson_id: str, csv_table: dict, apply: bool) -> dict | None:
    """Patch 1 bài. Trả về dict mô tả thay đổi, hoặc None nếu không cần patch."""
    json_path = LESSONS_DIR / f"{lesson_id}.json"
    if not json_path.exists():
        logger.warning("[%s] JSON không tồn tại, skip", lesson_id)
        return None

    try:
        cur = json.loads(json_path.read_text(encoding="utf-8"))
    except Exception as e:
        logger.error("[%s] parse JSON lỗi: %s", lesson_id, e)
        return {"id": lesson_id, "status": "ERROR", "detail": str(e)}

    source_local = f"input/html/deutsch-vorbereitung/horen/{lesson_id}/"
    row = csv_table.get(source_local)
    if not row:
        return {"id": lesson_id, "status": "NO_CSV", "detail": "chưa có entry trong CSV"}

    changes = []
    src = cur.setdefault("source", {})
    aud = cur.setdefault("audio", {})

    # Patch lingq_lesson_id
    if not src.get("lingq_lesson_id") and row.get("lesson_id"):
        lid = int(row["lesson_id"])
        cid = int(row["course_id"]) if row.get("course_id") else None
        src["lingq_lesson_id"] = lid
        src["lingq_course_id"] = cid
        src["lingq_reader_url"] = f"https://www.lingq.com/en/learn/de/web/reader/{lid}/"
        changes.append(f"lingq_id={lid}")

    # Patch audio.url
    if not aud.get("url") and row.get("audio_url"):
        url = row["audio_url"].strip()
        aud["url"] = url
        aud["host"] = "lingq_s3" if "s3.amazonaws.com" in url else "lingq"
        changes.append(f"audio={'lingq_s3' if 's3' in url else 'lingq'}")

    if not changes:
        return {"id": lesson_id, "status": "SKIP", "detail": "đã đầy đủ hoặc CSV thiếu audio_url"}

    if apply:
        safe_write(json_path, cur)
        # Verify
        try:
            verify = json.loads(json_path.read_text(encoding="utf-8"))
            assert bool(verify.get("source", {}).get("lingq_lesson_id")), "lingq_id vẫn null sau ghi"
        except Exception as e:
            logger.error("[%s] verify fail: %s", lesson_id, e)
            return {"id": lesson_id, "status": "VERIFY_FAIL", "detail": str(e)}
        logger.info("[%s] patched: %s", lesson_id, ", ".join(changes))

    return {"id": lesson_id, "status": "PATCHED" if apply else "WOULD_PATCH", "changes": changes}


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="Patch lingq_lesson_id + audio.url vào lesson JSON")
    g = ap.add_mutually_exclusive_group()
    g.add_argument("--dry-run", action="store_true", help="in plan, không ghi (default)")
    g.add_argument("--apply", action="store_true", help="ghi thật")
    ap.add_argument("--id", help="chỉ patch 1 bài (vd 1.5)")
    args = ap.parse_args(argv)

    apply = args.apply
    setup_logging()
    csv_table = load_csv()
    print(f"CSV: {len(csv_table)} entries có source_local")

    # Discover lesson IDs
    if args.id:
        ids = [args.id]
    else:
        ids = sorted(
            [p.stem for p in LESSONS_DIR.glob("*.json")],
            key=lambda x: tuple(int(p) for p in x.split("."))
        )

    mode = "DRY RUN" if not apply else "APPLY"
    print(f"=== patch_lingq_audio — {mode} ({len(ids)} bài) ===")

    n_patched = n_skip = n_no_csv = n_err = 0
    results: list[dict] = []

    for lid in ids:
        r = patch_one(lid, csv_table, apply)
        if r is None:
            n_err += 1
            continue
        results.append(r)
        s = r["status"]
        if s in ("PATCHED", "WOULD_PATCH"):
            n_patched += 1
        elif s == "NO_CSV":
            n_no_csv += 1
        elif s in ("SKIP",):
            n_skip += 1
        else:
            n_err += 1

    # Summary
    print("---")
    print(f"Patched  : {n_patched}")
    print(f"Skip     : {n_skip} (đã đầy đủ)")
    print(f"No CSV   : {n_no_csv} (chưa push LingQ)")
    print(f"Errors   : {n_err}")

    if n_no_csv > 0 and not args.id:
        no_csv_ids = [r["id"] for r in results if r["status"] == "NO_CSV"]
        by_series: dict[str, list] = {}
        for lid in no_csv_ids:
            s = lid.split(".")[0]
            by_series.setdefault(s, []).append(lid)
        print("\nCòn thiếu LingQ (chưa push):")
        for s in sorted(by_series, key=int):
            ids_s = by_series[s]
            print(f"  Series {s}.x: {len(ids_s)} bài — push bằng:")
            print(f"    C:\\php\\php74\\php.exe module\\lingq_sync\\lessons_push.php "
                  f"--batch \"input\\html\\deutsch-vorbereitung\\horen\\{s}.*\" --apply --sleep 2.0")

    if not apply and n_patched > 0:
        print(f"\nRun với --apply để ghi {n_patched} bài.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
