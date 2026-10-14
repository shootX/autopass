import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { lockfileGaps } from "../../../scripts/check-lockfiles.mjs";

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../..");

describe("lockfiles", () => {
  it("lists every optional platform package and required peer", () => {
    for (const rel of [
      "gewash/package-lock.json",
      "gewash/voucher-checker/package-lock.json",
      "backend/package-lock.json",
    ]) {
      const gaps = lockfileGaps(path.join(repoRoot, rel));
      expect(gaps.missingOptional, rel).toEqual([]);
      expect(gaps.missingPeers, rel).toEqual([]);
    }
  });

  it("declares the node and npm versions the apps were installed with", () => {
    for (const rel of ["gewash/package.json", "gewash/voucher-checker/package.json"]) {
      const pkg = JSON.parse(fs.readFileSync(path.join(repoRoot, rel), "utf8")) as {
        engines?: { node?: string; npm?: string };
      };
      expect(pkg.engines?.node, rel).toMatch(/22/);
      expect(pkg.engines?.npm, rel).toMatch(/10/);
      expect(pkg.engines?.npm, rel).toMatch(/11/);
    }
  });
});
