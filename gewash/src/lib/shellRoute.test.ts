import { describe, expect, it } from "vitest";
import { privacyLang, shellRoute } from "./shellRoute";

describe("web shell routes", () => {
  it("keeps the privacy page the app links to", () => {
    expect(shellRoute("/privacy")).toBe("privacy");
    expect(shellRoute("/privacy/")).toBe("privacy");
  });

  it("sends every old panel address to the landing page", () => {
    for (const path of ["/home", "/auth", "/register", "/customer-qr-page", "/manager-qr-page", "/payment-success", "/shop/discounts/4"]) {
      expect(shellRoute(path)).toBe("leave");
    }
  });

  it("does not loop on the root", () => {
    expect(shellRoute("/")).toBe("home");
  });
});

describe("privacy language", () => {
  it("prefers ?lang, then the saved language, then Georgian", () => {
    expect(privacyLang("?lang=en", "ru")).toBe("en");
    expect(privacyLang("", "ru")).toBe("ru");
    expect(privacyLang("", "ge")).toBe("ka");
    expect(privacyLang("?lang=xx", null)).toBe("ka");
  });
});
