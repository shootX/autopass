const BYTE = 255;

function expand(hex: string): string {
  const raw = hex.replace("#", "");
  if (raw.length === 3) {
    return raw
      .split("")
      .map((part) => part + part)
      .join("");
  }
  return raw;
}

function channel(hex: string, index: number): number {
  return Number.parseInt(expand(hex).slice(index * 2, index * 2 + 2), 16);
}

function linear(value: number): number {
  const unit = value / BYTE;
  if (unit <= 0.04045) return unit / 12.92;
  return ((unit + 0.055) / 1.055) ** 2.4;
}

export function relativeLuminance(hex: string): number {
  return (
    0.2126 * linear(channel(hex, 0)) +
    0.7152 * linear(channel(hex, 1)) +
    0.0722 * linear(channel(hex, 2))
  );
}

export function contrastRatio(foreground: string, background: string): number {
  const lighter = Math.max(relativeLuminance(foreground), relativeLuminance(background));
  const darker = Math.min(relativeLuminance(foreground), relativeLuminance(background));
  return (lighter + 0.05) / (darker + 0.05);
}

export const GOLD = "#e4b23c";
export const GOLD_TEXT = "#7a560c";
export const NAVY = "#12284b";
export const INK = "#14233c";
export const WHITE = "#ffffff";
export const CANVAS = "#f6f3ec";
export const GRAY_100 = "#efeae2";
export const GRAY_200 = "#e4ddd0";
export const MINT = "#fff6df";
export const TILE = "#f3f0e8";
export const AUTH_FIELD = "#152844";
export const PREFIX_ON_DARK = "#97a29a";

export const LIGHT_SURFACES = [WHITE, CANVAS, GRAY_100, GRAY_200, MINT, TILE] as const;

export const AA_NORMAL = 4.5;
