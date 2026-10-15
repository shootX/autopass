const TOKEN_KEY = "voucher_checker_access_token";
const MUST_CHANGE_KEY = "voucher_checker_must_change";

let memoryToken: string | null = null;

function purgePersistedToken(): void {
  try {
    localStorage.removeItem(TOKEN_KEY);
  } catch {
    /* private mode or tests without storage */
  }
}

purgePersistedToken();

export function getToken(): string | null {
  return memoryToken;
}

export function setToken(token: string): void {
  memoryToken = token.trim();
  purgePersistedToken();
}

export function clearToken(): void {
  memoryToken = null;
  try {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(MUST_CHANGE_KEY);
  } catch {
    /* private mode */
  }
}

export function mustChangePassword(): boolean {
  try {
    return localStorage.getItem(MUST_CHANGE_KEY) === "1";
  } catch {
    return false;
  }
}

export function setMustChangePassword(required: boolean): void {
  try {
    if (required) localStorage.setItem(MUST_CHANGE_KEY, "1");
    else localStorage.removeItem(MUST_CHANGE_KEY);
  } catch {
    /* private mode */
  }
}
