import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import {
  AA_NORMAL,
  AUTH_FIELD,
  CANVAS,
  GOLD,
  GOLD_TEXT,
  GRAY_100,
  INK,
  LIGHT_SURFACES,
  NAVY,
  PREFIX_ON_DARK,
  WHITE,
  contrastRatio,
} from "./paletteContrast";

const here = dirname(fileURLToPath(import.meta.url));

function source(path: string): string {
  return readFileSync(resolve(here, path), "utf8");
}

describe("palette contrast", () => {
  it("keeps the dark gold token in the styles that paint text", () => {
    expect(source("../index.css")).toContain(`--ap-gold-text: ${GOLD_TEXT}`);
    expect(source("../styles/_variables.scss")).toContain(`$goldText: ${GOLD_TEXT}`);
    expect(source("../assets/brand/tokens.css")).toContain(`--ap-gold-text: ${GOLD_TEXT}`);
    expect(source("../../voucher-checker/src/App.css")).toContain(`--ap-gold-text: ${GOLD_TEXT}`);
  });

  it("meets AA for gold text on every light surface", () => {
    for (const background of LIGHT_SURFACES) {
      expect(contrastRatio(GOLD_TEXT, background)).toBeGreaterThanOrEqual(AA_NORMAL);
    }
  });

  it("keeps bright gold for fills and for text on navy", () => {
    expect(contrastRatio(GOLD, WHITE)).toBeLessThan(AA_NORMAL);
    expect(contrastRatio(GOLD, CANVAS)).toBeLessThan(AA_NORMAL);
    expect(contrastRatio(GOLD, GRAY_100)).toBeLessThan(AA_NORMAL);
    expect(contrastRatio(GOLD, NAVY)).toBeGreaterThanOrEqual(AA_NORMAL);
    expect(contrastRatio(GOLD, INK)).toBeGreaterThanOrEqual(AA_NORMAL);
  });

  it("meets AA for the auth link and the phone prefix", () => {
    expect(contrastRatio(NAVY, WHITE)).toBeGreaterThanOrEqual(AA_NORMAL);
    expect(contrastRatio(GOLD, INK)).toBeGreaterThanOrEqual(AA_NORMAL);
    expect(contrastRatio(INK, WHITE)).toBeGreaterThanOrEqual(AA_NORMAL);
    expect(contrastRatio(PREFIX_ON_DARK, AUTH_FIELD)).toBeGreaterThanOrEqual(AA_NORMAL);
  });
});
