#!/usr/bin/env python3
"""Generate PWA assets from resources/brand/ SVG sources.

This script reads the master mark + wordmark SVGs and produces all the
PNG variants required for the Solar PWA:

  - public/pwa/icon-{192,256,384,512}.png  (maskable, transparent BG)
  - public/pwa/apple-touch-icon.png        (180x180, opaque #FAFAF7 fill)
  - public/pwa/favicon-{16,32}.png         (transparent BG)
  - public/pwa/splash-*.png                (3 iOS sizes, opaque #FAFAF7 fill)
  - public/pwa/screenshot-540x720.png      (PWA store mock with brand)

The SVGs are rasterized with sips (macOS native, ships with the OS).
Pillow is used to flatten alpha for assets that need an opaque fill.

Run:  python3 scripts/generate-pwa-assets.py
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent.parent
BRAND = ROOT / "resources" / "brand"
OUT = ROOT / "public" / "pwa"
OUT.mkdir(parents=True, exist_ok=True)

# Color tokens
WARM_BG = (250, 250, 247)   # #FAFAF7
SOLAR_ORANGE = (255, 138, 61)   # #FF8A3D
SOLAR_YELLOW = (255, 201, 60)   # #FFC93C
SLATE_900 = (15, 23, 42)        # #0F172A
SLATE_500 = (100, 116, 139)
SLATE_400 = (148, 163, 184)


def sips_png(src: Path, size: int, dest: Path) -> None:
    """Render an SVG to PNG at the given square size using macOS sips."""
    dest.parent.mkdir(parents=True, exist_ok=True)
    tmp = dest.with_suffix(".tmp.png")
    cmd = [
        "sips", "-s", "format", "png",
        "--resampleHeightWidth", str(size), str(size),
        str(src), "--out", str(tmp),
    ]
    subprocess.run(cmd, check=True, capture_output=True)
    shutil.move(tmp, dest)


def flatten_alpha(src: Path, dest: Path, bg: tuple[int, int, int] = WARM_BG) -> None:
    """Flatten an RGBA PNG to RGB on top of the given background color."""
    img = Image.open(src).convert("RGBA")
    bg_img = Image.new("RGB", img.size, bg)
    bg_img.paste(img, mask=img.split()[3])
    bg_img.save(dest, "PNG", optimize=True)


def make_apple_touch_icon() -> Path:
    src = BRAND / "solar-mark-filled.svg"
    dest = OUT / "apple-touch-icon.png"
    sips_png(src, 180, dest.with_suffix(".rgba.png"))
    flatten_alpha(dest.with_suffix(".rgba.png"), dest)
    dest.with_suffix(".rgba.png").unlink()
    return dest


def make_app_icon(size: int) -> Path:
    src = BRAND / "solar-mark.svg"
    dest = OUT / f"icon-{size}.png"
    sips_png(src, size, dest)
    return dest


def make_favicon(size: int) -> Path:
    src = BRAND / "solar-mark.svg"
    dest = OUT / f"favicon-{size}.png"
    sips_png(src, size, dest)
    return dest


def make_splash(width: int, height: int, name: str) -> Path:
    dest = OUT / f"splash-{name}.png"
    canvas = Image.new("RGB", (width, height), WARM_BG)

    wm_src = BRAND / "solar-wordmark.svg"
    wm_tmp = dest.with_suffix(".wm.png")
    target_w = int(width * 0.65)
    target_h = int(target_w / 3.6)
    sips_png(wm_src, target_w, wm_tmp)
    wm_img = Image.open(wm_tmp).convert("RGB")
    if wm_img.size != (target_w, target_h):
        wm_img = wm_img.resize((target_w, target_h), Image.LANCZOS)

    x = (width - target_w) // 2
    y = (height - target_h) // 2
    canvas.paste(wm_img, (x, y))
    wm_tmp.unlink()

    canvas.save(dest, "PNG", optimize=True)
    return dest


def make_screenshot() -> Path:
    dest = OUT / "screenshot-540x720.png"
    width, height = 540, 720
    canvas = Image.new("RGB", (width, height), WARM_BG)

    grad = Image.new("RGB", (width, 280), SOLAR_ORANGE)
    for y in range(280):
        t = y / 279
        r = int(SOLAR_ORANGE[0] * (1 - t) + SOLAR_YELLOW[0] * t)
        g = int(SOLAR_ORANGE[1] * (1 - t) + SOLAR_YELLOW[1] * t)
        b = int(SOLAR_ORANGE[2] * (1 - t) + SOLAR_YELLOW[2] * t)
        for x in range(width):
            grad.putpixel((x, y), (r, g, b))
    canvas.paste(grad, (0, 0))

    draw = ImageDraw.Draw(canvas)

    def font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
        for path in [
            "/System/Library/Fonts/Helvetica.ttc",
            "/System/Library/Fonts/SFNSDisplay.ttf",
            "/Library/Fonts/Inter-Regular.ttf",
            "/opt/homebrew/share/fonts/Inter-Regular.ttf",
        ]:
            if os.path.exists(path):
                try:
                    return ImageFont.truetype(path, size)
                except Exception:
                    pass
        return ImageFont.load_default()

    mark_size = 96
    sips_png(BRAND / "solar-mark-filled.svg", mark_size, dest.with_suffix(".mk.png"))
    mark = Image.open(dest.with_suffix(".mk.png")).convert("RGB")
    canvas.paste(mark, ((width - mark_size) // 2, 70))
    dest.with_suffix(".mk.png").unlink()

    title_font = font(38)
    sub_font = font(16)
    draw.text((width // 2, 195), "Solar", fill=SLATE_900, font=title_font, anchor="mm")
    draw.text(
        (width // 2, 232),
        "Finanças pessoais",
        fill=SLATE_900,
        font=sub_font,
        anchor="mm",
    )

    card_top = 300
    card_h = 110
    card_pad = 24
    for i in range(3):
        y0 = card_top + i * (card_h + 16)
        draw.rounded_rectangle(
            [(card_pad, y0), (width - card_pad, y0 + card_h)],
            radius=16,
            fill=(255, 255, 255),
        )
        dot_color = [SOLAR_ORANGE, (16, 163, 74), (220, 38, 38)][i]
        draw.ellipse(
            [(card_pad + 16, y0 + 24), (card_pad + 16 + 24, y0 + 24 + 24)],
            fill=dot_color,
        )
        draw.rectangle(
            [(card_pad + 56, y0 + 26), (card_pad + 200, y0 + 36)],
            fill=SLATE_900,
        )
        draw.rectangle(
            [(card_pad + 56, y0 + 50), (card_pad + 140, y0 + 58)],
            fill=SLATE_400,
        )
        draw.text(
            (width - card_pad - 16, y0 + card_h // 2),
            ["R$ 1.240", "R$ 4.500", "-R$ 320"][i],
            fill=SLATE_900,
            font=font(16),
            anchor="rm",
        )

    draw.text(
        (width // 2, height - 40),
        "Metas · Assinaturas · PIX · Multi-moeda",
        fill=SLATE_500,
        font=font(12),
        anchor="mm",
    )

    canvas.save(dest, "PNG", optimize=True)
    return dest


def main() -> int:
    print(">> Generating PWA assets into", OUT)

    for size in (192, 256, 384, 512):
        p = make_app_icon(size)
        print(f"   icon-{size}.png ({size}x{size})  ->  {p.name}")

    p = make_apple_touch_icon()
    print(f"   apple-touch-icon.png (180x180, opaque)  ->  {p.name}")

    for size in (16, 32):
        p = make_favicon(size)
        print(f"   favicon-{size}.png ({size}x{size})  ->  {p.name}")

    splashes = [
        (1125, 2436, "1125x2436"),
        (1242, 2688, "1242x2688"),
        (1536, 2048, "1536x2048"),
    ]
    for w, h, name in splashes:
        p = make_splash(w, h, name)
        print(f"   splash-{name}.png ({w}x{h})  ->  {p.name}")

    p = make_screenshot()
    print(f"   screenshot-540x720.png  ->  {p.name}")

    print(">> Done.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
