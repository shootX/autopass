const TOKEN_KEY = "access_token";
const EXPIRES_KEY = "access_token_expires_at";

export function saveAccessToken(token: string, expiresInSeconds?: number, now = Date.now()): void {
  localStorage.setItem(TOKEN_KEY, token);
  if (typeof expiresInSeconds === "number" && Number.isFinite(expiresInSeconds) && expiresInSeconds > 0) {
    localStorage.setItem(EXPIRES_KEY, String(now + expiresInSeconds * 1000));
    return;
  }
  localStorage.removeItem(EXPIRES_KEY);
}

/** Drops the token once the server-reported expires_in has passed. */
export function dropExpiredAccessToken(now = Date.now()): boolean {
  const raw = localStorage.getItem(EXPIRES_KEY);
  if (!raw) return false;
  const expiresAt = Number(raw);
  if (!Number.isFinite(expiresAt) || expiresAt > now) return false;
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(EXPIRES_KEY);
  return true;
}
