import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");

const LOCKS = [
  "gewash/package-lock.json",
  "gewash/voucher-checker/package-lock.json",
  "backend/package-lock.json",
];

function resolveDep(packages, parentPath, depName) {
  const candidates = [];
  let cursor = parentPath;
  while (cursor) {
    candidates.push(`${cursor}/node_modules/${depName}`);
    const idx = cursor.lastIndexOf("/node_modules/");
    if (idx === -1) break;
    cursor = cursor.slice(0, idx);
  }
  candidates.push(`node_modules/${depName}`);
  return candidates.find((candidate) => Object.prototype.hasOwnProperty.call(packages, candidate)) ?? null;
}

export function lockfileGaps(lockPath) {
  const lock = JSON.parse(fs.readFileSync(lockPath, "utf8"));
  const packages = lock.packages ?? {};
  const missingOptional = [];
  const missingPeers = [];
  for (const [name, pkg] of Object.entries(packages)) {
    for (const [dep, ver] of Object.entries(pkg.optionalDependencies ?? {})) {
      if (!resolveDep(packages, name, dep)) missingOptional.push(`${name || "(root)"} -> ${dep}@${ver}`);
    }
    const meta = pkg.peerDependenciesMeta ?? {};
    for (const [dep, ver] of Object.entries(pkg.peerDependencies ?? {})) {
      if (meta[dep]?.optional) continue;
      if (!resolveDep(packages, name, dep)) missingPeers.push(`${name || "(root)"} -> ${dep}@${ver}`);
    }
  }
  return { missingOptional, missingPeers };
}

const isDirect = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (isDirect) {
  let failed = false;
  for (const rel of LOCKS) {
    const file = path.join(root, rel);
    const { missingOptional, missingPeers } = lockfileGaps(file);
    const count = missingOptional.length + missingPeers.length;
    console.log(`${rel}: optional ${missingOptional.length}, peers ${missingPeers.length}`);
    if (count) {
      failed = true;
      console.log([...missingOptional, ...missingPeers].join("\n"));
    }
  }
  if (failed) process.exit(1);
}
