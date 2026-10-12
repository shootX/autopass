import { readFileSync, existsSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

const root = process.cwd();

describe("PWA", () => {
  it("ships png icons and a service worker", () => {
    const manifest = JSON.parse(readFileSync(path.join(root, "public/manifest.webmanifest"), "utf8"));
    const icons = manifest.icons as { src: string; sizes: string; purpose: string }[];
    expect(icons.some((icon) => icon.sizes === "192x192" && icon.purpose === "any")).toBe(true);
    expect(icons.some((icon) => icon.sizes === "512x512" && icon.purpose === "any")).toBe(true);
    expect(icons.some((icon) => icon.purpose === "maskable")).toBe(true);
    for (const icon of icons) {
      expect(existsSync(path.join(root, "public", icon.src.replace(/^\//, "")))).toBe(true);
    }

    const worker = readFileSync(path.join(root, "public/sw.js"), "utf8");
    expect(worker).toContain("install");
    expect(worker).toContain("fetch");
    expect(worker).toContain("/api");
  });

  it("does not keep expo-camera or the EAS app.json", () => {
    const pkg = JSON.parse(readFileSync(path.join(root, "package.json"), "utf8"));
    expect(pkg.dependencies["expo-camera"]).toBeUndefined();
    expect(existsSync(path.join(root, "app.json"))).toBe(false);
  });
});
