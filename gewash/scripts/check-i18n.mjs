import fs from "node:fs";
import path from "node:path";

const root = process.cwd();

function walk(dir, out = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) walk(p, out);
    else if (/\.(ts|tsx|js|jsx)$/.test(ent.name)) out.push(p);
  }
  return out;
}

const en = JSON.parse(fs.readFileSync(path.join(root, "en.json"), "utf8"));

function has(obj, key) {
  const parts = key.split(".");
  let cur = obj;
  for (const k of parts) {
    if (!cur || typeof cur !== "object" || Array.isArray(cur)) return false;
    if (!Object.prototype.hasOwnProperty.call(cur, k)) return false;
    cur = cur[k];
  }
  return cur !== undefined;
}

const srcDir = path.join(root, "src");
const files = walk(srcDir);

const tCallRe = /\bt\(\s*(["'])([^"']+?)\1\s*\)/g;
const keys = new Set();

for (const f of files) {
  const txt = fs.readFileSync(f, "utf8");
  let m;
  while ((m = tCallRe.exec(txt))) keys.add(m[2]);
}

const all = [...keys].sort();
const missing = all.filter((k) => !has(en, k));

console.log(`t() keys: ${keys.size}`);
console.log(`missing: ${missing.length}`);
if (missing.length) {
  console.log(missing.join("\n"));
}

