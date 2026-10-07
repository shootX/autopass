# autopass — brand kit v1.0

Based on concept #8: a 2×2 grid of rounded squares (QR-pass blocks) with a water drop
knocked out of the bottom-right square, plus a lowercase geometric wordmark.

## Files
| File | Use |
|---|---|
| `mark.svg` | Symbol only (100×100 grid) |
| `logo-horizontal.svg` / `logo-horizontal-dark.svg` | Mark + wordmark, light / dark backgrounds |
| `logo-stacked.svg` / `logo-stacked-dark.svg` | Mark above wordmark (primary lockup) |
| `app-icon.svg` | 1024 tile, Forest background, Foam + Lime squares |
| `app-icon-light.svg` | 1024 tile, Foam background, full-colour mark (alternate) |
| `favicon.svg` | 16-px pixel-grid mark; auto-lightens in dark mode via `prefers-color-scheme` |
| `palette.json`, `tokens.css`, `tailwind-colors.js` | Colour tokens |
| `png/` | PNG renders (transparent) · `brand-sheet.png` (+ `@2x`) overview |

## Geometry
- Square 45, gap 10, corner radius 8 on a 100-unit grid (gap ≈ 22 % of a square, radius ≈ 18 %).
- Drop: r = 9, tip 15.5 above the circle centre; horizontally centred, bbox nudged ~0.8 units up for optical centring.
- Light versions knock the drop out (transparent). Dark versions use Moss squares and a solid white drop.
- Favicon uses a 7/2/7 px grid with a proportionally larger drop for 16 px legibility.

## Wordmark
Plus Jakarta Sans ExtraBold (wght 800), tracking −1.5 %, outlined to paths (no font needed).
Font: © 2020 The Plus Jakarta Sans Project Authors (github.com/tokotype/PlusJakartaSans), SIL Open Font License 1.1 — see FONT-LICENSE-OFL.txt.

## Clear space & minimum size
Clear space ≥ half a square on all sides. Minimum: mark 24 px (use favicon.svg below that), horizontal logo 96 px wide.

## Regenerate
`python3 src/build.py && src/render.sh && python3 src/sheet.py` then render `src/brand-sheet.html` with headless Chrome at 1800×1180.
