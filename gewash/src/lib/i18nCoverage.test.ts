import { spawnSync } from "node:child_process";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { collectTranslationKeys, missingLocaleKeys } from "../../scripts/check-i18n.mjs";

const gewashRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

function readLocale(name: string): Record<string, unknown> {
  return JSON.parse(fs.readFileSync(path.join(gewashRoot, name), "utf8")) as Record<string, unknown>;
}

function lookup(obj: unknown, key: string): unknown {
  return key.split(".").reduce<unknown>((acc, part) => {
    if (!acc || typeof acc !== "object" || Array.isArray(acc)) return undefined;
    return (acc as Record<string, unknown>)[part];
  }, obj);
}

describe("i18n coverage", () => {
  it("has a real translation for every t() key in en, ka, and ru", () => {
    const keys = collectTranslationKeys(path.join(gewashRoot, "src"));
    const locales = {
      en: readLocale("en.json"),
      ka: readLocale("ka.json"),
      ru: readLocale("ru.json"),
    };
    const missing: string[] = [];
    for (const key of keys) {
      for (const [locale, data] of Object.entries(locales)) {
        const value = lookup(data, key);
        if (typeof value !== "string" || !value.trim()) missing.push(`${locale}:${key}`);
        if (locale === "en" && typeof value === "string" && /[\u10A0-\u10FF\u0400-\u04FF]/.test(value)) {
          missing.push(`en-not-english:${key}`);
        }
      }
    }
    expect(missing).toEqual([]);
    expect(missingLocaleKeys(gewashRoot).missing).toEqual([]);
  });

  it("exits non-zero when a locale key is missing", () => {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), "i18n-"));
    fs.mkdirSync(path.join(dir, "src"));
    fs.writeFileSync(path.join(dir, "src", "screen.tsx"), `export const label = t("Missing.Key");\n`);
    for (const locale of ["en", "ka", "ru"]) fs.writeFileSync(path.join(dir, `${locale}.json`), "{}\n");
    const result = spawnSync(process.execPath, [path.join(gewashRoot, "scripts/check-i18n.mjs"), dir], {
      encoding: "utf8",
    });
    expect(result.status).toBe(1);
    expect(result.stdout).toContain("en:Missing.Key");
    expect(result.stdout).toContain("ka:Missing.Key");
    expect(result.stdout).toContain("ru:Missing.Key");
  });
});
