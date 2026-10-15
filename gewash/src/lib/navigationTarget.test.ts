import { describe, expect, it } from "vitest";
import { queryRedirectPath, referralShareHref, safeCheckoutUrl } from "./navigationTarget";

const ORIGIN = "https://wash.socialsave.cc";

describe("query redirects", () => {
  it("never follows redirect, next, or return parameters", () => {
    expect(queryRedirectPath("?redirect=https://evil.example")).toBeNull();
    expect(queryRedirectPath("?next=https://evil.example/phish")).toBeNull();
    expect(queryRedirectPath("?return=//evil.example")).toBeNull();
    expect(queryRedirectPath("?returnTo=/help")).toBeNull();
    expect(queryRedirectPath("?url=javascript:alert(1)")).toBeNull();
  });
});

describe("checkout navigation", () => {
  it("rejects script and protocol-relative checkout URLs", () => {
    expect(safeCheckoutUrl("javascript:alert(1)", ORIGIN)).toBeNull();
    expect(safeCheckoutUrl("data:text/html,hi", ORIGIN)).toBeNull();
    expect(safeCheckoutUrl("//evil.example/pay", ORIGIN)).toBeNull();
    expect(safeCheckoutUrl("https://user:pass@pay.flitt.com/c", ORIGIN)).toBeNull();
  });

  it("allows https provider URLs and the same-origin sandbox", () => {
    expect(safeCheckoutUrl("https://pay.flitt.com/checkout/1", ORIGIN)).toBe(
      "https://pay.flitt.com/checkout/1",
    );
    expect(safeCheckoutUrl("https://api.tbcbank.ge/checkout", ORIGIN)).toBe(
      "https://api.tbcbank.ge/checkout",
    );
    expect(
      safeCheckoutUrl("https://wash.socialsave.cc/payment/sandbox?order=T1&access=abc", ORIGIN),
    ).toBe("https://wash.socialsave.cc/payment/sandbox?order=T1&access=abc");
  });

  it("does not treat an arbitrary same-origin path as checkout", () => {
    expect(safeCheckoutUrl("https://wash.socialsave.cc/auth", ORIGIN)).toBeNull();
    expect(safeCheckoutUrl("/auth", ORIGIN)).toBeNull();
  });
});

describe("referral share links", () => {
  it("does not send the visitor through an open redirect", () => {
    const link = "https://wash.socialsave.cc/r/abc";
    for (const network of ["telegram", "whatsapp", "viber", "facebook"] as const) {
      const href = referralShareHref(network, link);
      expect(href).not.toMatch(/redirect\.html/i);
      expect(href).not.toMatch(/[?&](redirect|next)=/i);
    }
    expect(referralShareHref("viber", link)).toBe(
      `viber://forward?text=${encodeURIComponent(link)}`,
    );
  });
});
