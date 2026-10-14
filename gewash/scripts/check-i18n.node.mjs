import assert from "node:assert/strict";
import { test } from "node:test";
import { missingKeys } from "./check-i18n.mjs";

test("missing, empty and placeholder translations fail validation", () => {
  assert.deepEqual(missingKeys({ ui: { good: "Hello", empty: "", placeholder: "ui.placeholder", group: {} } },
    ["ui.good", "ui.missing", "ui.empty", "ui.placeholder", "ui.group"]),
    ["ui.missing", "ui.empty", "ui.placeholder", "ui.group"]);
});

