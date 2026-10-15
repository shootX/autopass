import { afterEach, describe, expect, it } from "vitest";
import { clearAccessToken, dropExpiredAccessToken, readAccessToken, refreshDelayMs, saveAccessToken } from "./accessToken";

afterEach(() => {
  clearAccessToken();
  localStorage.clear();
});

describe("access token storage", () => {
  it("keeps the token in memory and never in localStorage", () => {
    localStorage.setItem("access_token", "leftover");
    localStorage.setItem("access_token_expires_at", "1");
    saveAccessToken("abc", 90, 1_000);
    expect(readAccessToken()).toBe("abc");
    expect(localStorage.getItem("access_token")).toBeNull();
    expect(localStorage.getItem("access_token_expires_at")).toBeNull();
    expect(dropExpiredAccessToken(1_000 + 89_000)).toBe(false);
    expect(readAccessToken()).toBe("abc");
  });

  it("drops a token after expires_in", () => {
    saveAccessToken("abc", 90, 1_000);
    expect(dropExpiredAccessToken(1_000 + 90_000)).toBe(true);
    expect(readAccessToken()).toBeNull();
    expect(localStorage.getItem("access_token")).toBeNull();
  });

  it("leaves tokens that have no expiry stamp", () => {
    saveAccessToken("abc");
    expect(dropExpiredAccessToken(Date.now() + 10_000_000)).toBe(false);
    expect(readAccessToken()).toBe("abc");
    expect(localStorage.getItem("access_token")).toBeNull();
  });

  it("refreshes before the one-day expires_in window", () => {
    const day = 1440 * 60;
    const delay = refreshDelayMs(day);
    expect(delay).toBeGreaterThan(0);
    expect(delay).toBeLessThan(day * 1000);
    expect(day * 1000 - delay).toBeLessThanOrEqual(60_000);
  });
});
