import { afterEach, describe, expect, it, vi } from "vitest";
import { setSessionRedirect } from "@/lib/session";
import { customFetch } from "./customFetch";
import { readAccessToken, saveAccessToken } from "@/lib/accessToken";

afterEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
  sessionStorage.clear();
  window.history.pushState({}, "", "/");
  setSessionRedirect(() => {});
});

describe("customFetch 401", () => {
  it("ends the session when an authorized request is rejected", async () => {
    saveAccessToken("abc");
    window.history.pushState({}, "", "/customer-my-data");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response("{}", { status: 401 })),
    );

    const response = await customFetch("https://api.example/me", {
      headers: { Authorization: "Bearer abc" },
    });

    expect(response.status).toBe(401);
    expect(readAccessToken()).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/auth");
  });

  it("sends a remembered manager to /manager-auth", async () => {
    saveAccessToken("abc");
    sessionStorage.setItem("autopass.session_role", "manager");
    sessionStorage.setItem("autopass.me", "{\"role\":1}");
    window.history.pushState({}, "", "/booking");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response("{}", { status: 401 })),
    );

    await customFetch("https://api.example/me", {
      headers: { Authorization: "Bearer abc" },
    });

    expect(sessionStorage.getItem("autopass.session_role")).toBeNull();
    expect(sessionStorage.getItem("autopass.me")).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/manager-auth");
  });

  it("leaves a logged-out 401 alone", async () => {
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response("{}", { status: 401 })),
    );

    await customFetch("https://api.example/login", {
      method: "POST",
      headers: { Authorization: "Bearer null" },
    });

    expect(redirect).not.toHaveBeenCalled();
  });

  it("includes cookies on login, set_password, refresh and logout", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => new Response("{}", { status: 200 })));
    for (const path of ["/login", "/register/set_password", "/refresh", "/logout"]) {
      await customFetch(`https://api.example${path}`, { method: "POST" });
    }
    await customFetch("https://api.example/me");
    const fetchMock = vi.mocked(fetch);
    const credentialed = fetchMock.mock.calls.slice(0, 4).map((call) => (call[1] as RequestInit).credentials);
    expect(credentialed).toEqual(["include", "include", "include", "include"]);
    expect((fetchMock.mock.calls[4][1] as RequestInit | undefined)?.credentials).toBeUndefined();
  });
});
