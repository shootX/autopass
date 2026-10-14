import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

export function scanKeys(dir) {
  const keys = new Set();
  function walk(folder) {
    for (const ent of fs.readdirSync(folder, { withFileTypes: true })) {
      const file = path.join(folder, ent.name);
      if (ent.isDirectory()) walk(file);
      else if (/\.(ts|tsx|js|jsx)$/.test(ent.name) && !/\.(test|spec)\./.test(ent.name)) {
        for (const match of fs.readFileSync(file, "utf8").matchAll(/\bt\(\s*(["'])([^"']+?)\1\s*\)/g)) keys.add(match[2]);
      }
    }
  }
  walk(dir);
  return [...keys].sort();
}

export function missingKeys(translations, keys) {
  return keys.filter(key => {
    let value = translations;
    for (const part of key.split(".")) value = value && typeof value === "object" ? value[part] : undefined;
    return typeof value !== "string" || !value.trim() || value === key;
  });
}

export function check(root) {
  const keys = scanKeys(path.join(root, "src"));
  let missing = 0;
  for (const language of ["ka", "en", "ru"]) {
    for (const file of [path.join(root, language + ".json"), path.join(root, "../backend/public/lang", language + ".json")]) {
      const absent = missingKeys(JSON.parse(fs.readFileSync(file, "utf8")), keys);
      console.log(path.relative(root, file) + ": " + absent.length + " missing");
      if (absent.length) console.log(absent.join("\n"));
      missing += absent.length;
    }
  }
  console.log("t() keys: " + keys.length);
  return missing === 0 ? 0 : 1;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try { process.exitCode = check(process.cwd()); }
  catch (error) { console.error(error.message); process.exitCode = 1; }
}

