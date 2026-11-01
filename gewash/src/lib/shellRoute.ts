/**
 * The customer and manager web panels were removed on 2026-11-01. Customers and managers use the
 * mobile app. This build keeps only the pages the app and the stores still link to.
 */
export type ShellRoute = "privacy" | "home" | "leave";

export function shellRoute(pathname: string): ShellRoute {
  const path = pathname.replace(/\/+$/, "") || "/";
  if (path === "/privacy") return "privacy";
  if (path === "/") return "home";
  return "leave";
}

export type PrivacyLang = "ka" | "en" | "ru";

/** ?lang= wins, then the language the old web panel saved, then Georgian. */
export function privacyLang(search: string, stored: string | null): PrivacyLang {
  const asked = new URLSearchParams(search).get("lang");
  for (const value of [asked, stored]) {
    if (value === "ka" || value === "en" || value === "ru") return value;
    if (value === "ge") return "ka";
  }
  return "ka";
}
