import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const LOCALES = ["en", "ka", "ru"];

function walk(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) walk(p, out);
    else if (/\.(ts|tsx|js|jsx)$/.test(ent.name) && !/\.test\.(ts|tsx|js|jsx)$/.test(ent.name)) out.push(p);
  }
  return out;
}

export function collectTranslationKeys(srcDir) {
  const tCallRe = /\bt\(\s*(["'])([^"']+?)\1\s*\)/g;
  const keys = new Set();
  for (const file of walk(srcDir)) {
    const txt = fs.readFileSync(file, "utf8");
    let match;
    while ((match = tCallRe.exec(txt))) keys.add(match[2]);
  }
  return [...keys].sort();
}

function lookup(obj, key) {
  const parts = key.split(".");
  let cur = obj;
  for (const part of parts) {
    if (!cur || typeof cur !== "object" || Array.isArray(cur)) return undefined;
    if (!Object.prototype.hasOwnProperty.call(cur, part)) return undefined;
    cur = cur[part];
  }
  return cur;
}

export function missingLocaleKeys(root) {
  const keys = collectTranslationKeys(path.join(root, "src"));
  const missing = [];
  for (const locale of LOCALES) {
    const file = path.join(root, `${locale}.json`);
    const data = JSON.parse(fs.readFileSync(file, "utf8"));
    for (const key of keys) {
      const value = lookup(data, key);
      if (typeof value !== "string" || value.trim() === "") missing.push(`${locale}:${key}`);
    }
  }
  return { keys, missing };
}

const isDirect = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isDirect) {
  const root = process.argv[2] ? path.resolve(process.argv[2]) : process.cwd();
  const { keys, missing } = missingLocaleKeys(root);
  console.log(`t() keys: ${keys.length}`);
  console.log(`missing: ${missing.length}`);
  if (missing.length) {
    console.log(missing.join("\n"));
    process.exit(1);
  }
}
