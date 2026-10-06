/** Default JSON request headers; ngrok free tier often needs skip-browser-warning. */
export function jsonHeaders(): Record<string, string> {
  const h: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };
  const skip = String(import.meta.env.VITE_NGROK_SKIP_BROWSER_WARNING ?? "true").trim().toLowerCase();
  if (skip !== "false" && skip !== "0") {
    h["ngrok-skip-browser-warning"] = "true";
  }
  return h;
}

export function authJsonHeaders(token: string | null): Record<string, string> {
  const h = jsonHeaders();
  if (token?.trim()) {
    h.Authorization = `Bearer ${token.trim()}`;
  }
  return h;
}
