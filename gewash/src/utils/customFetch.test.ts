import { afterEach, describe, expect, it, vi } from "vitest";
import { setSessionRedirect } from "@/lib/session";
import { customFetch } from "./customFetch";

afterEach(() => {
  vi.unstubAllGlobals();
  localStorage.clear();
  sessionStorage.clear();
  window.history.pushState({}, "", "/");
  setSessionRedirect(() => {});
});

describe("customFetch 401", () => {
  it("ends the session when an authorized request is rejected", async () => {
    localStorage.setItem("access_token", "abc");
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
    expect(localStorage.getItem("access_token")).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/auth");
  });

  it("sends a remembered manager to /manager-auth", async () => {
    localStorage.setItem("access_token", "abc");
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
});
