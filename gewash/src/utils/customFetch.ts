import { bearerToken, expireSession } from "@/lib/session";
import { refreshAccessToken } from "@/lib/sessionRefresh";
import { readAccessToken } from "@/lib/accessToken";

function wantsCredentials(url: string): boolean {
  const path = url.split("?")[0] ?? url;
  return path.endsWith("/login")
    || path.endsWith("/register/set_password")
    || path.endsWith("/refresh")
    || path.endsWith("/logout");
}

async function request(input: RequestInfo, init: RequestInit | undefined, retried: boolean): Promise<Response> {
  const url = typeof input === "string" ? input : input.url;
  const headers = new Headers(init?.headers || {});
  if (!headers.has("Accept-Language")) {
    const language = localStorage.getItem("lang");
    headers.set("Accept-Language", language === "en" || language === "ru" ? language : "ka");
  }
  if (url.includes("ngrok-free.app")) {
    headers.set("ngrok-skip-browser-warning", "true");
  }

  const credentials = init?.credentials ?? (wantsCredentials(url) ? "include" : undefined);
  const response = await fetch(input, {
    ...init,
    headers,
    ...(credentials ? { credentials } : {}),
  });

  if (response.status === 401 && bearerToken(headers)) {
    if (!retried) {
      const outcome = await refreshAccessToken();
      const token = readAccessToken();
      if (outcome === "ok" && token) {
        headers.set("Authorization", `Bearer ${token}`);
        return request(input, { ...init, headers }, true);
      }
      if (outcome === "unauthorized") return response;
      return response;
    }
    expireSession();
  }

  return response;
}

export async function customFetch(input: RequestInfo, init?: RequestInit): Promise<Response> {
  return request(input, init, false);
}
