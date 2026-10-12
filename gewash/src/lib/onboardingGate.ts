const SKIP = new Set(["/auth", "/register", "/renew-password", "/privacy", "/payment-success"]);

/** Onboarding must not cover a direct link to login, register, or password reset. */
export function shouldShowOnboarding(loggedOut: boolean, onboarded: boolean, pathname: string): boolean {
  if (!loggedOut || onboarded) return false;
  return !SKIP.has(pathname);
}
