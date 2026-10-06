import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, "..");

const en = JSON.parse(fs.readFileSync(path.join(root, "en.json"), "utf8"));
const set = new Set();
function walk(v) {
  if (typeof v === "string") set.add(v);
  else if (v && typeof v === "object" && !Array.isArray(v)) Object.values(v).forEach(walk);
}
walk(en);
const sorted = [...set].sort();
fs.writeFileSync(
  path.join(__dirname, "_en-unique.json"),
  JSON.stringify(sorted, null, 2) + "\n",
  "utf8"
);
console.log(`Wrote scripts/_en-unique.json (${sorted.length} unique strings)`);
