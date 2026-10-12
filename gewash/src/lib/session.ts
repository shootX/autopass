const AUTH_PATH = "/auth";

type Redirect = (path: string) => void;

let redirect: Redirect = (path) => {
  window.location.assign(path);
};
let redirecting = false;

export function setSessionRedirect(fn: Redirect): void {
  redirect = fn;
  redirecting = false;
}

export function clearAccessToken(): void {
  localStorage.removeItem("access_token");
}

/** Drop the stored token and leave the app. No-op on the login screen. */
export function expireSession(): void {
  clearAccessToken();
  if (redirecting || window.location.pathname === AUTH_PATH) return;
  redirecting = true;
  redirect(AUTH_PATH);
}

export function bearerToken(headers: Headers): string | null {
  const value = headers.get("Authorization");
  if (!value?.toLowerCase().startsWith("bearer ")) return null;
  const token = value.slice(7).trim();
  if (!token || token === "null" || token === "undefined") return null;
  return token;
}
