#!/usr/bin/env python3
"""Crop staff/student photos to a 3:4 ID portrait on a pure white background.

Keeps the original pixels of the person (rembg). Used for every Wisdom
child-school picture import.
"""
from __future__ import annotations

import sys
from io import BytesIO
from pathlib import Path

from PIL import Image
from rembg import new_session, remove

OUT_W = 900
OUT_H = 1200
EXTS = {".jpg", ".jpeg", ".png", ".webp"}
SESSION = None


def session():
    global SESSION
    if SESSION is None:
        SESSION = new_session("u2net_human_seg")
    return SESSION


def crop_cover(im: Image.Image, out_w: int, out_h: int) -> Image.Image:
    sw, sh = im.size
    target = out_w / max(1, out_h)
    src_r = sw / max(1, sh)
    if src_r > target:
        crop_h = sh
        crop_w = max(1, int(round(sh * target)))
        sx = max(0, (sw - crop_w) // 2)
        sy = 0
    else:
        crop_w = sw
        crop_h = max(1, int(round(sw / target)))
        sx = 0
        sy = max(0, int((sh - crop_h) * 0.12))
    crop_w = max(1, min(sw - sx, crop_w))
    crop_h = max(1, min(sh - sy, crop_h))
    return im.crop((sx, sy, sx + crop_w, sy + crop_h)).resize(
        (out_w, out_h), Image.Resampling.LANCZOS
    )


def prepare_one(src: Path, dest: Path) -> None:
    original = Image.open(src).convert("RGBA")
    cut = remove(original, session=session())
    if not isinstance(cut, Image.Image):
        cut = Image.open(BytesIO(cut)).convert("RGBA")
    else:
        cut = cut.convert("RGBA")
    white = Image.new("RGBA", cut.size, (255, 255, 255, 255))
    composed = Image.alpha_composite(white, cut).convert("RGB")
    portrait = crop_cover(composed, OUT_W, OUT_H)
    dest.parent.mkdir(parents=True, exist_ok=True)
    portrait.save(dest, "JPEG", quality=92, optimize=True)


def prepare_dir(src_dir: Path, dest_dir: Path) -> int:
    count = 0
    for path in sorted(src_dir.iterdir()):
        if not path.is_file() or path.suffix.lower() not in EXTS:
            continue
        dest = dest_dir / (path.stem + ".jpg")
        print("PREP", path.name)
        prepare_one(path, dest)
        count += 1
    return count


def main() -> int:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else r"C:\methode\15 Wisdoms\6.WISDOM SCHOOL SUSA\staff pictures")
    dest = Path(sys.argv[2] if len(sys.argv) > 2 else r"C:\xampp7\htdocs\Xander-school\deploy\_wisdom_susa_photos_white")
    if src.is_file():
        out = dest if dest.suffix.lower() in EXTS else dest / (src.stem + ".jpg")
        prepare_one(src, out)
        print("WROTE", out)
        return 0
    n = prepare_dir(src, dest)
    print("WROTE", n, "photos ->", dest)
    return 0 if n else 1


if __name__ == "__main__":
    raise SystemExit(main())
