export async function customFetch(input: RequestInfo, init?: RequestInit): Promise<Response> {
    const url = typeof input === "string" ? input : input.url;
  
    const headers = new Headers(init?.headers || {});
    if (url.includes("ngrok-free.app")) {
      headers.set("ngrok-skip-browser-warning", "true");
    }
  
    return fetch(input, {
      ...init,
      headers,
    });
  }
  