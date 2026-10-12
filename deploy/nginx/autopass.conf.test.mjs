import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";

const example = readFileSync(new URL("./autopass.conf.example", import.meta.url), "utf8");

test("SPA routes, including /home, fall through to the SPA index", () => {
  assert.match(example, /try_files\s+\$uri\s+\$uri\/\s+\/index\.html\s*;/);
  assert.match(example, /\/home/);
  assert.match(example, /\/auth/);
});
