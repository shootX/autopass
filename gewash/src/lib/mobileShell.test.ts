import { readFileSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

const root = process.cwd();
const gewashIndex = readFileSync(path.join(root, "index.html"), "utf8");
const authScss = readFileSync(path.join(root, "src/styles/customer_styles/auth.scss"), "utf8");
const lendingCss = readFileSync(path.join(root, "../lending/styles.css"), "utf8");
const navScss = readFileSync(path.join(root, "src/styles/autopass.scss"), "utf8");

describe("mobile shell", () => {
  it("allows pinch zoom", () => {
    expect(gewashIndex).not.toContain("user-scalable=no");
    expect(gewashIndex).not.toContain("maximum-scale");
    expect(gewashIndex).toContain("width=device-width");
  });

  it("wraps the register consent row", () => {
    expect(authScss).toMatch(/\.auth-accept-policy\s*\{[^}]*flex-wrap:\s*wrap/);
  });

  it("keeps the lending form fields at 16px or more", () => {
    expect(lendingCss).toMatch(/--f-s:clamp\(16px,/);
    expect(lendingCss).toMatch(/\.field input\{[^}]*font-size:var\(--f-s\)/);
  });

  it("pads the bottom nav for the home indicator", () => {
    expect(navScss).toMatch(/\.nav-bar-wrapper\s*\{[^}]*safe-area-inset-bottom/);
  });
});
