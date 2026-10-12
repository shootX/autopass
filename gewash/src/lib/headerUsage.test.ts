globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { readdirSync, readFileSync, statSync } from "node:fs";
import path from "node:path";
import { act, createElement, type ReactNode } from "react";
import { createRoot, type Root } from "react-dom/client";
import { describe, expect, it, afterEach } from "vitest";
import Header from "@/components/Header";

const ALLOWED_RAW_HEADER = new Set([
  "components/Header.tsx",
  "components/Skeletons/PageSkeleton.tsx",
]);

function walk(dir: string): string[] {
  const out: string[] = [];
  for (const name of readdirSync(dir)) {
    const full = path.join(dir, name);
    if (statSync(full).isDirectory()) out.push(...walk(full));
    else out.push(full);
  }
  return out;
}

describe("shared Header", () => {
  it("page headers go through Header", () => {
    const src = path.join(process.cwd(), "src");
    const offenders: string[] = [];
    for (const file of walk(src)) {
      if (!file.endsWith(".tsx")) continue;
      const rel = path.relative(src, file);
      if (ALLOWED_RAW_HEADER.has(rel)) continue;
      const text = readFileSync(file, "utf8");
      if (/<header[\s>]/.test(text) || /className=["']v4-hd["']/.test(text)) {
        offenders.push(rel);
      }
    }
    expect(offenders).toEqual([]);
  });

  let root: Root | null = null;
  let host: HTMLDivElement | null = null;

  afterEach(() => {
    act(() => {
      root?.unmount();
    });
    host?.remove();
    root = null;
    host = null;
  });

  function render(node: ReactNode) {
    host = document.createElement("div");
    document.body.appendChild(host);
    root = createRoot(host);
    act(() => {
      root?.render(node);
    });
    return host;
  }

  it("keeps the bar header element and the title tag", () => {
    const view = render(createElement(Header, { variant: "bar", title: "Scanner", titleAs: "h3" }));
    const header = view.querySelector("header");
    expect(header?.tagName).toBe("HEADER");
    expect(header?.querySelector("h3")?.textContent).toBe("Scanner");
    expect(view.querySelector(".v4-hd")).toBeNull();
  });

  it("keeps the v4 shell as a div.v4-hd with an h1", () => {
    const view = render(createElement(Header, { variant: "v4", title: "QR" }));
    expect(view.querySelector("div.v4-hd h1")?.textContent).toBe("QR");
    expect(view.querySelector("header")).toBeNull();
  });

  it("keeps labeled class names on the header and the title", () => {
    const view = render(
      createElement(Header, {
        variant: "labeled",
        className: "shop-header",
        title: "Shop",
        titleAs: "span",
        titleClassName: "shop-header__title",
      }),
    );
    expect(view.querySelector("header.shop-header span.shop-header__title")?.textContent).toBe("Shop");
  });
});
