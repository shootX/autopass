const TOKEN_KEY = "voucher_checker_access_token";

export function getToken(): string | null {
  const t = localStorage.getItem(TOKEN_KEY);
  return t && t.trim() ? t.trim() : null;
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

