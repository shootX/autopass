import { refreshDelayMs, readAccessToken, saveAccessToken, setAccessTokenListener } from "./accessToken";
import { expireSession } from "./session";
import { customFetch } from "@/utils/customFetch";

let timer: ReturnType<typeof setTimeout> | null = null;

function clearTimer(): void {
  if (timer != null) {
    clearTimeout(timer);
    timer = null;
  }
}

export async function refreshAccessToken(attempt = 0): Promise<boolean> {
  const current = readAccessToken();
  if (!current) return false;
  try {
    const res = await customFetch(`${import.meta.env.VITE_API_URL}/refresh`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${current}`,
        Accept: "application/json",
      },
    });
    if (res.status === 409 && attempt < 1) {
      return refreshAccessToken(attempt + 1);
    }
    if (res.status === 409) {
      return false;
    }
    if (!res.ok) {
      expireSession();
      return false;
    }
    const data = (await res.json()) as { access_token?: unknown; expires_in?: unknown };
    if (typeof data.access_token !== "string" || !data.access_token) {
      expireSession();
      return false;
    }
    const expiresIn = typeof data.expires_in === "number" ? data.expires_in : undefined;
    saveAccessToken(data.access_token, expiresIn);
    return true;
  } catch {
    expireSession();
    return false;
  }
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

setAccessTokenListener(scheduleAccessRefresh);
