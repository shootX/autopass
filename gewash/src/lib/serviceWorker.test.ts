import { readFileSync } from "node:fs";
import { runInNewContext } from "node:vm";
import { describe, expect, it, vi } from "vitest";

const source = readFileSync("public/sw.js", "utf8");
const origin = "https://wash.example.test";

type TestRequest = { url: string; method: string; mode: string; cache: string; headers: Headers };
type TestEvent = { request: TestRequest; respondWith: (response: Promise<Response>) => void; waitUntil: (task: Promise<unknown>) => void };

function worker(response = new Response("asset", { headers: { "Content-Type": "application/javascript" } })) {
  Object.defineProperty(response, "type", { value: "basic" });
  const handlers = new Map<string, (event: TestEvent) => void>();
  const put = vi.fn(async () => undefined);
  const cache = { put, match: vi.fn(async () => undefined), addAll: vi.fn(async () => undefined) };
  const caches = { open: vi.fn(async () => cache), keys: vi.fn(async () => ["autopass-shell-v1", "other-app-cache"]), delete: vi.fn(async () => true), match: vi.fn(async () => undefined) };
  const fetch = vi.fn(async () => response);
  const pending: Promise<unknown>[] = [];
  let intercepted = false;
  runInNewContext(source, {
    self: { location: { origin }, addEventListener: (name: string, handler: (event: TestEvent) => void) => handlers.set(name, handler), skipWaiting: vi.fn(), clients: { claim: vi.fn() } },
    caches, fetch, URL, Response,
  });
  const request = (path: string, overrides: Partial<TestRequest> = {}) => {
    const event: TestEvent = {
      request: { url: origin + path, method: "GET", mode: "cors", cache: "default", headers: new Headers(), ...overrides },
      respondWith: (task) => { intercepted = true; pending.push(task); },
      waitUntil: (task) => { pending.push(task); },
    };
    handlers.get("fetch")?.(event);
    return event;
  };
  const finish = async () => { for (let i = 0; i < pending.length; i++) await pending[i]; };
  return { request, finish, handlers, cache, caches, fetch, intercepted: () => intercepted };
}

describe("public-only service worker cache", () => {
  it.each(["/dashboard/sms-inbox", "/partner/reports", "/login", "/api/me"])("does not intercept private route %s", async (path) => {
    const w = worker();
    w.request(path);
    await w.finish();
    expect(w.intercepted()).toBe(false);
    expect(w.cache.put).not.toHaveBeenCalled();
  });

  it("does not intercept document navigation or authorized asset requests", () => {
    const w = worker();
    w.request("/assets/app.js", { mode: "navigate" });
    w.request("/assets/app.js", { headers: new Headers({ Authorization: "Bearer fixture-token" }) });
    expect(w.intercepted()).toBe(false);
  });

  it.each(["no-store", "private, max-age=60"])("respects response cache directive %s", async (directive) => {
    const w = worker(new Response("secret fixture", { headers: { "Cache-Control": directive, "Content-Type": "application/javascript" } }));
    w.request("/assets/app.js");
    await w.finish();
    expect(w.cache.put).not.toHaveBeenCalled();
  });

  it("caches public assets", async () => {
    const w = worker();
    w.request("/assets/app.js");
    await w.finish();
    expect(w.cache.put).toHaveBeenCalledTimes(1);
  });

  it("removes the old application cache while preserving unrelated caches", async () => {
    const w = worker();
    const event = w.request("/api/me");
    w.handlers.get("activate")?.(event);
    await w.finish();
    expect(w.caches.delete).toHaveBeenCalledWith("autopass-shell-v1");
    expect(w.caches.delete).not.toHaveBeenCalledWith("other-app-cache");
  });
});
