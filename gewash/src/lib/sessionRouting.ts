import type { SessionPhase, UserRole } from "@/store/userSlice";

export const MANAGER_HOME = "/manager-qr-page";

export type AppRouteDecision = "loading" | "login" | "manager" | "customer" | "hold";

/**
 * Route selection is allowed only after GET /me settles.
 * `pending` (and a stale role) must not choose manager or customer pages.
 */
export function selectAppRoute(session: SessionPhase, role: UserRole | null): AppRouteDecision {
  if (session === "pending") return "loading";
  if (session === "error") return "hold";
  if (session === "unauthorized") return "login";
  if (role === "manager") return "manager";
  if (role === "customer") return "customer";
  if (session === "anonymous") return "customer";
  return "hold";
}

/** Car ownership is a customer concern. Managers and an unknown role skip it. */
export function shouldCheckCars(role: UserRole | null, bypassAuth: boolean): boolean {
  if (bypassAuth) return false;
  return role === "customer";
}

/** Null until /me has produced a role — callers must not navigate yet. */
export function postLoginPath(role: UserRole | null): string | null {
  if (role === "manager") return MANAGER_HOME;
  if (role === "customer") return "/";
  return null;
}
