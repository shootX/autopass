import { afterEach, describe, expect, it, vi } from "vitest";
import { bearerToken, expireSession, setSessionRedirect } from "./session";

afterEach(() => {
  localStorage.clear();
  window.history.pushState({}, "", "/");
  setSessionRedirect(() => {});
});

describe("expireSession", () => {
  it("clears the token and opens /auth", () => {
    localStorage.setItem("access_token", "abc");
    window.history.pushState({}, "", "/my-packages");
    const redirect = vi.fn();
    setSessionRedirect(redirect);

    expireSession();

    expect(localStorage.getItem("access_token")).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/auth");
  });

  it("does not redirect when already on /auth", () => {
    localStorage.setItem("access_token", "abc");
    window.history.pushState({}, "", "/auth");
    const redirect = vi.fn();
    setSessionRedirect(redirect);

    expireSession();

    expect(localStorage.getItem("access_token")).toBeNull();
    expect(redirect).not.toHaveBeenCalled();
  });

  it("redirects only once", () => {
    window.history.pushState({}, "", "/profile");
    const redirect = vi.fn();
    setSessionRedirect(redirect);

    expireSession();
    expireSession();

    expect(redirect).toHaveBeenCalledTimes(1);
  });
});

describe("bearerToken", () => {
  it("reads a real bearer token", () => {
    const headers = new Headers({ Authorization: "Bearer abc" });
    expect(bearerToken(headers)).toBe("abc");
  });

  it("ignores a missing token", () => {
    expect(bearerToken(new Headers({ Authorization: "Bearer null" }))).toBeNull();
    expect(bearerToken(new Headers())).toBeNull();
  });
});
