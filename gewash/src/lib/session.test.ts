import { afterEach, describe, expect, it, vi } from "vitest";
import {
  bearerToken,
  expireSession,
  loginPathForRole,
  rememberSessionRole,
  setSessionRedirect,
} from "./session";

afterEach(() => {
  localStorage.clear();
  sessionStorage.clear();
  window.history.pushState({}, "", "/");
  setSessionRedirect(() => {});
});

describe("expireSession", () => {
  it("clears the token and opens /auth", () => {
    localStorage.setItem("access_token", "abc");
    localStorage.setItem("access_token_expires_at", "99");
    localStorage.setItem("autopass.me", "{\"id\":1}");
    sessionStorage.setItem("autopass.me", "{\"id\":1}");
    window.history.pushState({}, "", "/my-packages");
    const redirect = vi.fn();
    setSessionRedirect(redirect);

    expireSession();

    expect(localStorage.getItem("access_token")).toBeNull();
    expect(localStorage.getItem("access_token_expires_at")).toBeNull();
    expect(localStorage.getItem("autopass.me")).toBeNull();
    expect(sessionStorage.getItem("autopass.me")).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/auth");
  });

  it("sends a manager to the manager login and drops the remembered role", () => {
    localStorage.setItem("access_token", "abc");
    rememberSessionRole("manager");
    window.history.pushState({}, "", "/manager-qr-page");
    const redirect = vi.fn();
    setSessionRedirect(redirect);

    expireSession();

    expect(sessionStorage.getItem("autopass.session_role")).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/manager-auth");
    expect(loginPathForRole("customer")).toBe("/auth");
  });

  it("does not redirect when already on a login screen", () => {
    localStorage.setItem("access_token", "abc");
    window.history.pushState({}, "", "/auth");
    const redirect = vi.fn();
    setSessionRedirect(redirect);

    expireSession();

    expect(localStorage.getItem("access_token")).toBeNull();
    expect(redirect).not.toHaveBeenCalled();

    window.history.pushState({}, "", "/manager-auth");
    rememberSessionRole("manager");
    expireSession();
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
