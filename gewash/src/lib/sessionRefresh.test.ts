import { afterEach, describe, expect, it, vi } from "vitest";
import { clearAccessToken, readAccessToken, refreshDelayMs, saveAccessToken } from "./accessToken";
import { setSessionRedirect } from "./session";
import { refreshAccessToken, scheduleAccessRefresh } from "./sessionRefresh";

afterEach(() => {
  clearAccessToken();
  vi.useRealTimers();
  vi.unstubAllGlobals();
  vi.unstubAllEnvs();
  setSessionRedirect(() => {});
});

describe("POST /api/refresh", () => {
  it("replaces the in-memory token before expires_in", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    vi.useFakeTimers();
    const fetchMock = vi.fn(async () =>
      new Response(JSON.stringify({ access_token: "rotated", expires_in: 86400 }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      }),
    );
    vi.stubGlobal("fetch", fetchMock);
    saveAccessToken("current", 10);
    scheduleAccessRefresh(10);

    await vi.advanceTimersByTimeAsync(refreshDelayMs(10));

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe("https://api.example/api/refresh");
    expect(init.method).toBe("POST");
    expect(new Headers(init.headers).get("Authorization")).toBe("Bearer current");
    expect(readAccessToken()).toBe("rotated");
    expect(localStorage.getItem("access_token")).toBeNull();
  });

  it("retries a 409 once and then keeps the new token", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    let calls = 0;
    const fetchMock = vi.fn(async () => {
      calls += 1;
      if (calls === 1) {
        return new Response(JSON.stringify({ error: "Refresh already in progress" }), { status: 409 });
      }
      return new Response(JSON.stringify({ access_token: "rotated", expires_in: 86400 }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      });
    });
    vi.stubGlobal("fetch", fetchMock);
    saveAccessToken("current", 86400);

    const ok = await refreshAccessToken();

    expect(ok).toBe(true);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    const second = fetchMock.mock.calls[1] as [string, RequestInit];
    expect(new Headers(second[1].headers).get("Authorization")).toBe("Bearer current");
    expect(readAccessToken()).toBe("rotated");
    expect(redirect).not.toHaveBeenCalled();
  });

  it("logs out on 401 without a second refresh", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({ error: "Unauthorized" }), { status: 401 }));
    vi.stubGlobal("fetch", fetchMock);
    saveAccessToken("current", 86400);

    const ok = await refreshAccessToken();

    expect(ok).toBe(false);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(readAccessToken()).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/auth");
  });

  it("stays signed in when a 409 is still busy after one retry", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    const fetchMock = vi.fn(async () => new Response(JSON.stringify({ error: "Refresh already in progress" }), { status: 409 }));
    vi.stubGlobal("fetch", fetchMock);
    saveAccessToken("current", 86400);

    const ok = await refreshAccessToken();

    expect(ok).toBe(false);
    expect(fetchMock).toHaveBeenCalledTimes(2);
    expect(readAccessToken()).toBe("current");
    expect(redirect).not.toHaveBeenCalled();
  });

  it("logs out when refresh fails", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    saveAccessToken("current", 86400);
    vi.stubGlobal("fetch", vi.fn(async () => new Response("no", { status: 500 })));

    const ok = await refreshAccessToken();

    expect(ok).toBe(false);
    expect(readAccessToken()).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/auth");
  });
});
