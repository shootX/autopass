import { jsonHeaders } from "./apiHeaders";

export type LoginResponse = {
  access_token?: string;
  token?: string;
  success?: boolean;
  message?: string;
  error?: string;
};

function apiUrl(): string {
  const base = String(import.meta.env.VITE_API_URL || "").trim().replace(/\/+$/, "");
  if (!base) throw new Error("Missing VITE_API_URL");
  return base;
}

function loginPath(): string {
  const p = String(import.meta.env.VITE_AUTH_LOGIN_PATH || "/partner/login").trim();
  return p.startsWith("/") ? p : `/${p}`;
}

function pickToken(json: unknown): string | null {
  if (!json || typeof json !== "object") return null;
  const o = json as Record<string, unknown>;
  const data = o.data && typeof o.data === "object" ? (o.data as Record<string, unknown>) : null;
  const candidates = [o.access_token, o.token, data?.access_token, data?.token];
  for (const t of candidates) {
    if (typeof t === "string" && t.trim()) return t.trim();
  }
  return null;
}

export async function login(loginValue: string, password: string): Promise<{ token: string; payload: LoginResponse }> {
  const loginClean = loginValue.trim();
  const passClean = password.trim();
  if (!loginClean) throw new Error("Login is required");
  if (!passClean) throw new Error("Password is required");

  let res: Response;
  try {
    res = await fetch(`${apiUrl()}${loginPath()}`, {
      method: "POST",
      headers: jsonHeaders(),
      body: JSON.stringify({ login: loginClean, password: passClean }),
    });
  } catch (e) {
    const msg = e instanceof Error ? e.message : "Network error";
    throw new Error(msg);
  }

  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    // ignore
  }

  const payload: LoginResponse = (json && typeof json === "object" ? (json as LoginResponse) : { success: false }) as LoginResponse;
  const token = pickToken(json);

  if (!res.ok || !token) {
    const msg =
      payload.message ||
      payload.error ||
      (typeof json === "object" && json && "errors" in (json as object) ? JSON.stringify((json as { errors?: unknown }).errors) : null) ||
      `Login failed (${res.status})`;
    throw new Error(typeof msg === "string" ? msg : `Login failed (${res.status})`);
  }

  return { token, payload };
}

