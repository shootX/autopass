#!/usr/bin/env python3
"""Rebuild lending/img rasters and lending/fonts/*.woff2.

Needs Pillow (WebP + AVIF), fontTools with brotli, and numpy.
Source cut-outs live in lending/src-photos/. Brand fonts are read from the repo.
"""
from __future__ import annotations

import math
from pathlib import Path

import numpy as np
from fontTools.ttLib import TTFont
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
REPO = ROOT.parent
SRC = ROOT / "src-photos"
IMG = ROOT / "img"
FONTS = ROOT / "fonts"


def css_recolor(im: Image.Image) -> Image.Image:
    """Same chain as the approved mock: hue-rotate(118) saturate(.62) brightness(.68) contrast(1.08)."""
    arr = np.asarray(im.convert("RGBA")).astype(np.float32)
    rgb = arr[..., :3] / 255.0
    alpha = arr[..., 3]

    ang = math.radians(118)
    c, s = math.cos(ang), math.sin(ang)
    hue = np.array(
        [
            [0.213 + c * 0.787 - s * 0.213, 0.715 - c * 0.715 - s * 0.715, 0.072 - c * 0.072 + s * 0.928],
            [0.213 - c * 0.213 + s * 0.143, 0.715 + c * 0.285 + s * 0.140, 0.072 - c * 0.072 - s * 0.283],
            [0.213 - c * 0.213 - s * 0.787, 0.715 - c * 0.715 + s * 0.715, 0.072 + c * 0.928 + s * 0.072],
        ],
        dtype=np.float32,
    )
    sat = 0.62
    sat_m = np.array(
        [
            [0.213 + 0.787 * sat, 0.715 - 0.715 * sat, 0.072 - 0.072 * sat],
            [0.213 - 0.213 * sat, 0.715 + 0.285 * sat, 0.072 - 0.072 * sat],
            [0.213 - 0.213 * sat, 0.715 - 0.715 * sat, 0.072 + 0.928 * sat],
        ],
        dtype=np.float32,
    )
    out = np.tensordot(np.tensordot(rgb, hue.T, axes=1), sat_m.T, axes=1)
    out = out * 0.68
    out = out * 1.08 + (1 - 1.08) * 0.5
    out = np.clip(out, 0, 1)
    clean = np.zeros_like(out)
    mask = alpha > 0
    clean[mask] = out[mask]
    rgba = np.dstack([(clean * 255 + 0.5).astype(np.uint8), np.clip(alpha + 0.5, 0, 255).astype(np.uint8)])
    return Image.fromarray(rgba, "RGBA")


def resize(im: Image.Image, width: int) -> Image.Image:
    if im.width <= width:
        return im
    height = round(im.height * width / im.width)
    return im.resize((width, height), Image.Resampling.LANCZOS)


def save_variants(im: Image.Image, name: str, *, png_colors: int | None, write_png: bool = True) -> None:
    avif = IMG / f"{name}.avif"
    webp = IMG / f"{name}.webp"
    png = IMG / f"{name}.png"
    im.save(avif, quality=56, speed=6)
    im.save(webp, quality=80, method=6)
    if not write_png:
        print(f"{name}: avif {avif.stat().st_size} webp {webp.stat().st_size}")
        return
    if png_colors and im.mode == "RGBA":
        alpha = im.getchannel("A")
        q = im.convert("RGB").quantize(colors=png_colors, method=Image.Quantize.FASTOCTREE, dither=Image.Dither.FLOYDSTEINBERG)
        q = q.convert("RGBA")
        q.putalpha(alpha)
        q.save(png, optimize=True, compress_level=9)
    else:
        im.save(png, optimize=True, compress_level=9)
    print(f"{name}: avif {avif.stat().st_size} webp {webp.stat().st_size} png {png.stat().st_size}")


def write_fonts() -> None:
    pairs = [
        (REPO / "gewash/src/assets/brand/PlusJakartaSans.ttf", FONTS / "PlusJakartaSans.woff2"),
        (REPO / "gewash/public/fonts/NotoSansGeorgian.ttf", FONTS / "NotoSansGeorgian.woff2"),
    ]
    for src, dst in pairs:
        font = TTFont(src)
        font.flavor = "woff2"
        font.save(str(dst))
        print(dst.name, dst.stat().st_size)


def rounded(draw: ImageDraw.ImageDraw, box, radius, fill) -> None:
    draw.rounded_rectangle(box, radius=radius, fill=fill)


def make_og() -> None:
    canvas = Image.new("RGBA", (1200, 630), (247, 249, 244, 255))
    draw = ImageDraw.Draw(canvas)
    rounded(draw, (760, 48, 1152, 582), 40, "#14482F")
    for row in range(2):
        for col in range(4):
            x = 980 + col * 30
            y = 78 + row * 30
            color = "#B5DD3A" if (row, col) in {(0, 2), (1, 1)} else (255, 255, 255, 40)
            draw.rounded_rectangle((x, y, x + 18, y + 18), radius=5, fill=color)

    logo = Image.open(SRC / "logo-lockup.png").convert("RGBA")
    logo.thumbnail((280, 62), Image.Resampling.LANCZOS)
    canvas.alpha_composite(logo, (64, 56))

    noto = str(REPO / "gewash/public/fonts/NotoSansGeorgian.ttf")
    head = ImageFont.truetype(noto, 48)
    body = ImageFont.truetype(noto, 22)
    head.set_variation_by_axes([800, 100])
    body.set_variation_by_axes([500, 100])
    y = 168
    for line in ("სუფთა მანქანა", "ყოველ კვირას,", "ერთი აბონემენტით"):
        draw.text((64, y), line, font=head, fill="#0E1712")
        y += 62
    draw.rounded_rectangle((64, y + 6, 108, y + 14), radius=4, fill="#B5DD3A")
    draw.text((64, y + 32), "რეცხვის აბონემენტი.\nდაჯავშნე და აჩვენე QR.", font=body, fill="#5E6A62", spacing=8)

    car = resize(Image.open(SRC / "hero-car.png").convert("RGBA"), 560)
    canvas.alpha_composite(car, (640, 250))
    canvas.convert("RGB").save(IMG / "og.jpg", quality=82, optimize=True)
    print("og.jpg", (IMG / "og.jpg").stat().st_size)


def main() -> None:
    IMG.mkdir(exist_ok=True)
    hero = Image.open(SRC / "hero-car.png").convert("RGBA")
    benefit = Image.open(SRC / "benefit-car.png").convert("RGBA")
    top = css_recolor(Image.open(SRC / "top-car.png"))
    app = Image.open(SRC / "app-home.png").convert("RGB")

    save_variants(resize(hero, 1280), "hero-car-1280", png_colors=128)
    save_variants(resize(hero, 740), "hero-car-740", png_colors=None, write_png=False)
    save_variants(resize(benefit, 1117), "benefit-car-1117", png_colors=128)
    save_variants(resize(benefit, 760), "benefit-car-760", png_colors=None, write_png=False)
    save_variants(resize(top, 840), "top-car-840", png_colors=96)
    save_variants(resize(app, 780), "app-home-780", png_colors=None)

    for extra in ("hero-car-740.png", "benefit-car-760.png"):
        path = IMG / extra
        if path.exists():
            path.unlink()
            print("removed", extra)
    write_fonts()
    make_og()


if __name__ == "__main__":
    main()
