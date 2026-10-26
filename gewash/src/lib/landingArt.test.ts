import { readFileSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

const root = path.resolve(process.cwd(), "..");

describe("landing art", () => {
  const html = readFileSync(path.join(root, "lending/index.html"), "utf8");
  const business = readFileSync(path.join(root, "lending/business.html"), "utf8");
  const css = readFileSync(path.join(root, "lending/styles.css"), "utf8");

  it("uses the mockup's car, logo and fonts as files", () => {
    expect(html).toContain("img/hero-car.webp");
    expect(html).toContain("img/logo.svg");
    expect(business).toContain("img/logo.svg");
    expect(css).toContain("url(fonts/FiraGO-400.woff2)");
    expect(html + business + css).not.toContain("base64,");
  });

  it("has no inline scripts, so script-src 'self' holds", () => {
    for (const page of [html, business]) {
      expect(page).not.toMatch(/<script>/);
      expect(page).not.toMatch(/\son[a-z]+="/);
    }
  });
});

describe("sidebar overlap", () => {
  const scss = readFileSync(path.join(process.cwd(), "src/styles/autopass.scss"), "utf8");

  it("lets a tall promo scroll instead of covering the profile", () => {
    const media = scss.slice(scss.indexOf("@media (min-width: 1024px)"));
    const side = media.slice(media.indexOf(".ap-side {"));
    const block = side.slice(0, side.indexOf("}"));
    expect(block).toContain("overflow-y: auto");
    expect(block).toContain("overflow-x: hidden");
    expect(media).toContain(".ap-side .promo");
    expect(media).toContain(".ap-side .usr");
    const promo = media.slice(media.indexOf(".ap-side .promo"));
    expect(promo.slice(0, promo.indexOf("}"))).toContain("flex: none");
    const user = media.slice(media.indexOf(".ap-side .usr"));
    expect(user.slice(0, user.indexOf("}"))).toContain("flex: none");
  });
});
