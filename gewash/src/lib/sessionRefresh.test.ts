import { afterEach, describe, expect, it, vi } from "vitest";
import { clearAccessToken, readAccessToken, refreshDelayMs, saveAccessToken } from "./accessToken";
import { setSessionRedirect } from "./session";
import { logoutSession, refreshAccessToken, resetSessionBootForTests, scheduleAccessRefresh } from "./sessionRefresh";

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" },
  });
}

afterEach(() => {
  clearAccessToken();
  resetSessionBootForTests();
  vi.useRealTimers();
  vi.unstubAllGlobals();
  vi.unstubAllEnvs();
  setSessionRedirect(() => {});
});

describe("POST /api/refresh cookie", () => {
  it("restores a session with the cookie and no Authorization header", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    vi.useFakeTimers();
    const fetchMock = vi.fn(async () => json({ access_token: "rotated", expires_in: 86400 }));
    vi.stubGlobal("fetch", fetchMock);
    saveAccessToken("current", 10);
    scheduleAccessRefresh(10);

    await vi.advanceTimersByTimeAsync(refreshDelayMs(10));

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe("https://api.example/api/refresh");
    expect(init.method).toBe("POST");
    expect(init.credentials).toBe("include");
    expect(new Headers(init.headers).has("Authorization")).toBe(false);
    expect(readAccessToken()).toBe("rotated");
    expect(localStorage.getItem("access_token")).toBeNull();
  });

  it("retries a 409 once and keeps the session", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    let calls = 0;
    const fetchMock = vi.fn(async () => {
      calls += 1;
      if (calls === 1) return json({ error: "Refresh already in progress" }, 409);
      return json({ access_token: "rotated", expires_in: 86400 });
    });
    vi.stubGlobal("fetch", fetchMock);
    saveAccessToken("current", 86400);

    const outcome = await refreshAccessToken();

    expect(outcome).toBe("ok");
    expect(fetchMock).toHaveBeenCalledTimes(2);
    const second = fetchMock.mock.calls[1] as [string, RequestInit];
    expect(second[1].credentials).toBe("include");
    expect(new Headers(second[1].headers).has("Authorization")).toBe(false);
    expect(readAccessToken()).toBe("rotated");
    expect(redirect).not.toHaveBeenCalled();
  });

  it("sends a 401 to login", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    vi.stubGlobal("fetch", vi.fn(async () => json({ success: false }, 401)));
    saveAccessToken("current", 86400);

    const outcome = await refreshAccessToken();

    expect(outcome).toBe("unauthorized");
    expect(readAccessToken()).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/auth");
  });

  it("does not log out when a 409 is still busy after one retry", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    vi.stubGlobal("fetch", vi.fn(async () => json({ error: "Refresh already in progress" }, 409)));
    saveAccessToken("current", 86400);

    const outcome = await refreshAccessToken();

    expect(outcome).toBe("busy");
    expect(readAccessToken()).toBe("current");
    expect(redirect).not.toHaveBeenCalled();
  });

  it("does not log out when the network fails", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    vi.stubGlobal("fetch", vi.fn(async () => {
      throw new TypeError("offline");
    }));
    saveAccessToken("current", 86400);

    const outcome = await refreshAccessToken();

    expect(outcome).toBe("offline");
    expect(readAccessToken()).toBe("current");
    expect(redirect).not.toHaveBeenCalled();
  });

  it("shares one refresh between concurrent callers", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    let release: (response: Response) => void = () => {};
    const fetchMock = vi.fn(() => new Promise<Response>((resolve) => {
      release = resolve;
    }));
    vi.stubGlobal("fetch", fetchMock);

    const first = refreshAccessToken();
    const second = refreshAccessToken();
    expect(fetchMock).toHaveBeenCalledTimes(1);
    release(json({ access_token: "once", expires_in: 60 }));

    await expect(first).resolves.toBe("ok");
    await expect(second).resolves.toBe("ok");
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(readAccessToken()).toBe("once");
  });
});

describe("POST /api/logout", () => {
  it("sends the refresh cookie and then drops the memory token", async () => {
    vi.stubEnv("VITE_API_URL", "https://api.example/api");
    const redirect = vi.fn();
    setSessionRedirect(redirect);
    const fetchMock = vi.fn(async () => json({ success: true }));
    vi.stubGlobal("fetch", fetchMock);
    saveAccessToken("current", 86400);
    window.history.pushState({}, "", "/settings");

    await logoutSession();

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe("https://api.example/api/logout");
    expect(init.method).toBe("POST");
    expect(init.credentials).toBe("include");
    expect(new Headers(init.headers).get("Authorization")).toBe("Bearer current");
    expect(readAccessToken()).toBeNull();
    expect(redirect).toHaveBeenCalledWith("/auth");
  });
});
