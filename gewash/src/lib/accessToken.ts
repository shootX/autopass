const TOKEN_KEY = "access_token";
const EXPIRES_KEY = "access_token_expires_at";

let memoryToken: string | null = null;
let memoryExpiresAt: number | null = null;

type TokenListener = (expiresInSeconds: number | undefined) => void;
let listener: TokenListener = () => {};

function purgePersistedToken(): void {
  try {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(EXPIRES_KEY);
  } catch {
    /* private mode */
  }
}

purgePersistedToken();

export function setAccessTokenListener(fn: TokenListener): void {
  listener = fn;
}

export function readAccessToken(): string | null {
  return memoryToken;
}

export function saveAccessToken(token: string, expiresInSeconds?: number, now = Date.now()): void {
  memoryToken = token;
  if (typeof expiresInSeconds === "number" && Number.isFinite(expiresInSeconds) && expiresInSeconds > 0) {
    memoryExpiresAt = now + expiresInSeconds * 1000;
  } else {
    memoryExpiresAt = null;
  }
  purgePersistedToken();
  listener(typeof expiresInSeconds === "number" ? expiresInSeconds : undefined);
}

export function clearAccessToken(): void {
  memoryToken = null;
  memoryExpiresAt = null;
  purgePersistedToken();
  listener(undefined);
}

/** Drops the in-memory token once the server-reported expires_in has passed. */
export function dropExpiredAccessToken(now = Date.now()): boolean {
  purgePersistedToken();
  if (!memoryToken || memoryExpiresAt == null) return false;
  if (memoryExpiresAt > now) return false;
  memoryToken = null;
  memoryExpiresAt = null;
  listener(undefined);
  return true;
}

/** How long to wait before calling POST /api/refresh. Always before expires_in. */
export function refreshDelayMs(expiresInSeconds: number): number {
  const ttl = expiresInSeconds * 1000;
  if (!Number.isFinite(ttl) || ttl <= 0) return 0;
  const lead = Math.min(60_000, Math.max(1_000, Math.floor(ttl * 0.05)));
  return Math.max(0, ttl - lead);
}
