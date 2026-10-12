import { afterEach, describe, expect, it } from "vitest";
import { dropExpiredAccessToken, saveAccessToken } from "./accessToken";

afterEach(() => {
  localStorage.clear();
});

describe("access token expiry", () => {
  it("keeps a token until expires_in elapses", () => {
    saveAccessToken("abc", 90, 1_000);
    expect(dropExpiredAccessToken(1_000 + 89_000)).toBe(false);
    expect(localStorage.getItem("access_token")).toBe("abc");
  });

  it("drops a token after expires_in", () => {
    saveAccessToken("abc", 90, 1_000);
    expect(dropExpiredAccessToken(1_000 + 90_000)).toBe(true);
    expect(localStorage.getItem("access_token")).toBeNull();
  });

  it("leaves tokens that have no expiry stamp", () => {
    saveAccessToken("abc");
    expect(dropExpiredAccessToken(Date.now() + 10_000_000)).toBe(false);
    expect(localStorage.getItem("access_token")).toBe("abc");
  });
});
