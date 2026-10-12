import { readFileSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

const root = path.resolve(process.cwd(), "..");

describe("landing art", () => {
  const html = readFileSync(path.join(root, "lending/index.html"), "utf8");
  const css = readFileSync(path.join(root, "lending/styles.css"), "utf8");

  it("uses the top-down car and the app screenshot", () => {
    expect(html).toContain("img/top-car-840.png");
    expect(html).toContain("img/app-home-780.png");
  });

  it("paints the phone screenshot inside the mockup", () => {
    expect(css).not.toContain("picture{display:contents}");
    expect(css).toContain(".phone-screen picture{display:block;width:100%;height:100%}");
    expect(css).toContain(".phone-screen img{width:100%;height:100%;object-fit:cover;object-position:top");
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
