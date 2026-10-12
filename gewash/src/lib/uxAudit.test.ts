import { createRequire } from "node:module";
import path from "node:path";
import { describe, expect, it, vi } from "vitest";
import { AUTO_RENEWAL_DEFAULT, shouldShowPackageForm } from "./packageForm";
import { languageNativeName } from "./languageLabel";
import { scrollForLocation, scrollToHash } from "./scrollForLocation";
import { avatarLabel, greetingName } from "./greetingName";
import { VOUCHER_CATEGORY_KEYS, voucherCategoryLabelKey } from "./voucherCategories";

const require = createRequire(import.meta.url);
const redirectIfLoggedIn = require(path.join(process.cwd(), "../lending/logged-in-redirect.js")) as (options: {
  token?: string;
  appBase?: string;
  replace?: (url: string) => void;
}) => boolean;

describe("package form", () => {
  it("keeps auto-renew off until the user opts in", () => {
    expect(AUTO_RENEWAL_DEFAULT).toBe(false);
  });

  it("shows the buy form only while creating or editing", () => {
    expect(shouldShowPackageForm(false, false)).toBe(false);
    expect(shouldShowPackageForm(true, false)).toBe(true);
    expect(shouldShowPackageForm(false, true)).toBe(true);
  });
});

describe("language labels", () => {
  it("uses native names", () => {
    expect(languageNativeName("ka")).toBe("ქართული");
    expect(languageNativeName("en")).toBe("English");
    expect(languageNativeName("ru")).toBe("Русский");
  });
});

describe("scroll", () => {
  it("resets scroll when the route has no hash", () => {
    const scrollTo = vi.spyOn(window, "scrollTo").mockImplementation(() => undefined);
    scrollForLocation("");
    expect(scrollTo).toHaveBeenCalledWith(0, 0);
    scrollTo.mockClear();
    scrollForLocation("#vehicles");
    expect(scrollTo).not.toHaveBeenCalled();
    scrollTo.mockRestore();
  });

  it("scrolls to a hash target", () => {
    const node = document.createElement("div");
    node.id = "vehicles";
    node.scrollIntoView = vi.fn();
    document.body.appendChild(node);
    scrollToHash("#vehicles");
    expect(node.scrollIntoView).toHaveBeenCalled();
    node.remove();
  });
});

describe("greeting and avatar", () => {
  it("falls back to the phone when the name is empty", () => {
    expect(greetingName("", "995555112233")).toBe("+995 555 11 22 33");
    expect(greetingName("ნინო", "995555112233")).toBe("ნინო");
  });

  it("uses initials, then the last phone digits", () => {
    expect(avatarLabel("ნინო", "ბერიძე", "995555112233")).toBe("ნბ");
    expect(avatarLabel("", "", "995555112233")).toBe("33");
  });
});

describe("voucher chips", () => {
  it("maps category keys to translation paths", () => {
    expect(VOUCHER_CATEGORY_KEYS).toContain("All");
    expect(voucherCategoryLabelKey("All")).toBe("Discounts.categories.All");
  });
});

describe("logged-in marketing redirect", () => {
  it("sends a stored token to /home and ignores a missing token", () => {
    const replace = vi.fn();
    expect(redirectIfLoggedIn({ token: "", appBase: "https://app.example", replace })).toBe(false);
    expect(replace).not.toHaveBeenCalled();
    expect(redirectIfLoggedIn({ token: "abc", appBase: "https://app.example/", replace })).toBe(true);
    expect(replace).toHaveBeenCalledWith("https://app.example/home");
  });
});
