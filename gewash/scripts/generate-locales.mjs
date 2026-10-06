import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

import ru1 from "./locale-values/ru-part1.mjs";
import ru2 from "./locale-values/ru-part2.mjs";
import ru3 from "./locale-values/ru-part3.mjs";
import ru4 from "./locale-values/ru-part4.mjs";
import ka1 from "./locale-values/ka-part1.mjs";
import ka2 from "./locale-values/ka-part2.mjs";
import ka3 from "./locale-values/ka-part3.mjs";
import ka4 from "./locale-values/ka-part4.mjs";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, "..");

const keys = JSON.parse(fs.readFileSync(path.join(__dirname, "_en-unique.json"), "utf8"));
const ruValues = [...ru1, ...ru2, ...ru3, ...ru4];
const kaValues = [...ka1, ...ka2, ...ka3, ...ka4];

if (keys.length !== ruValues.length || keys.length !== kaValues.length) {
  throw new Error(
    `Length mismatch: keys=${keys.length} ru=${ruValues.length} ka=${kaValues.length}`
  );
}

function buildMap(values) {
  return Object.fromEntries(keys.map((k, i) => [k, values[i]]));
}

function mapStrings(obj, map) {
  if (typeof obj === "string") {
    if (!Object.prototype.hasOwnProperty.call(map, obj)) {
      console.warn(`[i18n] missing translation for: ${JSON.stringify(obj).slice(0, 120)}`);
    }
    return map[obj] ?? obj;
  }
  if (obj && typeof obj === "object" && !Array.isArray(obj)) {
    const out = {};
    for (const k of Object.keys(obj)) out[k] = mapStrings(obj[k], map);
    return out;
  }
  return obj;
}

const en = JSON.parse(fs.readFileSync(path.join(root, "en.json"), "utf8"));
const ruJson = mapStrings(en, buildMap(ruValues));
const kaJson = mapStrings(en, buildMap(kaValues));

fs.writeFileSync(path.join(root, "ru.json"), JSON.stringify(ruJson, null, 4) + "\n", "utf8");
fs.writeFileSync(path.join(root, "ka.json"), JSON.stringify(kaJson, null, 4) + "\n", "utf8");

console.log("Wrote ru.json and ka.json");
