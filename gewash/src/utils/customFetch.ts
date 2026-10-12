import { bearerToken, expireSession } from "@/lib/session";

export async function customFetch(input: RequestInfo, init?: RequestInit): Promise<Response> {
  const url = typeof input === "string" ? input : input.url;

  const headers = new Headers(init?.headers || {});
  if (url.includes("ngrok-free.app")) {
    headers.set("ngrok-skip-browser-warning", "true");
  }

  const response = await fetch(input, {
    ...init,
    headers,
  });

  if (response.status === 401 && bearerToken(headers)) {
    expireSession();
  }

  return response;
}
