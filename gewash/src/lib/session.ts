import { clearAccessToken } from "./accessToken";

const CUSTOMER_LOGIN = "/auth";
const MANAGER_LOGIN = "/manager-auth";
const ROLE_KEY = "autopass.session_role";
const ME_CACHE_KEY = "autopass.me";

type Redirect = (path: string) => void;
type ExpiredListener = () => void;

let redirect: Redirect = (path) => {
  window.location.assign(path);
};
let redirecting = false;
const expiredListeners = new Set<ExpiredListener>();

export function setSessionRedirect(fn: Redirect): void {
  redirect = fn;
  redirecting = false;
}

export function onSessionExpired(listener: ExpiredListener): () => void {
  expiredListeners.add(listener);
  return () => {
    expiredListeners.delete(listener);
  };
}

export function rememberSessionRole(role: "manager" | "customer"): void {
  sessionStorage.setItem(ROLE_KEY, role);
}

export function readSessionRole(): "manager" | "customer" | null {
  const value = sessionStorage.getItem(ROLE_KEY);
  if (value === "manager" || value === "customer") return value;
  return null;
}

export function loginPathForRole(role: string | null): string {
  return role === "manager" ? MANAGER_LOGIN : CUSTOMER_LOGIN;
}

/** Tokens, remembered role, and any cached /me payload. */
export function clearSessionStorage(): void {
  clearAccessToken();
  localStorage.removeItem(ME_CACHE_KEY);
  sessionStorage.removeItem(ROLE_KEY);
  sessionStorage.removeItem(ME_CACHE_KEY);
}

function isLoginPath(pathname: string): boolean {
  return pathname === CUSTOMER_LOGIN || pathname === MANAGER_LOGIN;
}

/** Drop the stored session and leave for the role's login screen. */
export function expireSession(): void {
  const path = loginPathForRole(readSessionRole());
  clearSessionStorage();
  for (const listener of expiredListeners) listener();
  if (redirecting || isLoginPath(window.location.pathname)) return;
  redirecting = true;
  redirect(path);
}

export function bearerToken(headers: Headers): string | null {
  const value = headers.get("Authorization");
  if (!value?.toLowerCase().startsWith("bearer ")) return null;
  const token = value.slice(7).trim();
  if (!token || token === "null" || token === "undefined") return null;
  return token;
}
