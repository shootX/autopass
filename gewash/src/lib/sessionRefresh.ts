import { refreshDelayMs, readAccessToken, saveAccessToken, setAccessTokenListener } from "./accessToken";
import { expireSession } from "./session";

export type RefreshOutcome = "ok" | "unauthorized" | "busy" | "offline";
export type SessionBoot = "pending" | "ready" | "anonymous" | "hold";

let timer: ReturnType<typeof setTimeout> | null = null;
let inflight: Promise<RefreshOutcome> | null = null;
let boot: SessionBoot = "pending";
let bootPromise: Promise<SessionBoot> | null = null;
let generation = 0;
const listeners = new Set<(phase: SessionBoot) => void>();

function clearTimer(): void {
  if (timer != null) {
    clearTimeout(timer);
    timer = null;
  }
}

function publishBoot(next: SessionBoot): void {
  boot = next;
  for (const listener of listeners) listener(next);
}

export function readSessionBoot(): SessionBoot {
  return boot;
}

export function subscribeSessionBoot(listener: (phase: SessionBoot) => void): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

export function resetSessionBootForTests(): void {
  generation += 1;
  inflight = null;
  boot = "pending";
  bootPromise = null;
  clearTimer();
}

function postRefresh(): Promise<Response> {
  return fetch(`${import.meta.env.VITE_API_URL}/refresh`, {
    method: "POST",
    credentials: "include",
    headers: { Accept: "application/json" },
  });
}

async function refreshOnce(): Promise<RefreshOutcome> {
  const res = await postRefresh();
  if (res.status === 409) return "busy";
  if (res.status === 401) {
    if (readAccessToken()) expireSession();
    return "unauthorized";
  }
  if (!res.ok) return "offline";
  const data = (await res.json()) as { access_token?: unknown; expires_in?: unknown };
  if (typeof data.access_token === "string" && data.access_token) {
    const expiresIn = typeof data.expires_in === "number" ? data.expires_in : undefined;
    saveAccessToken(data.access_token, expiresIn);
    return "ok";
  }
  return readAccessToken() ? "ok" : "unauthorized";
}

async function runRefresh(): Promise<RefreshOutcome> {
  const first = await refreshOnce();
  if (first !== "busy") return first;
  const second = await refreshOnce();
  return second === "busy" ? "busy" : second;
}

/** Cookie refresh. Concurrent callers share one request. No Authorization header. */
export function refreshAccessToken(): Promise<RefreshOutcome> {
  if (inflight) return inflight;
  inflight = runRefresh()
    .catch(() => "offline" as const)
    .finally(() => {
      inflight = null;
    });
  return inflight;
}

/** Cold load / new tab. Resolves once, before protected routes render. */
export function bootSession(): Promise<SessionBoot> {
  if (!bootPromise) {
    const gen = generation;
    bootPromise = refreshAccessToken().then((outcome) => {
      const next: SessionBoot = outcome === "ok"
        ? "ready"
        : outcome === "unauthorized"
          ? "anonymous"
          : readAccessToken()
            ? "ready"
            : "hold";
      if (gen === generation) publishBoot(next);
      return next;
    });
  }
  return bootPromise;
}

export function scheduleAccessRefresh(expiresInSeconds: number | undefined): void {
  clearTimer();
  if (typeof expiresInSeconds !== "number" || !Number.isFinite(expiresInSeconds) || expiresInSeconds <= 0) return;
  if (!readAccessToken()) return;
  timer = setTimeout(() => {
    timer = null;
    void refreshAccessToken();
  }, refreshDelayMs(expiresInSeconds));
}

export async function logoutSession(): Promise<void> {
  const token = readAccessToken();
  try {
    await fetch(`${import.meta.env.VITE_API_URL}/refresh/logout`, {
      method: "POST",
      credentials: "include",
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    });
  } catch {
    /* The cookie clear is best-effort; the local session still ends. */
  }
  expireSession();
}

setAccessTokenListener(scheduleAccessRefresh);
