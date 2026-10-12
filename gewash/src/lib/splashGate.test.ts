import { describe, expect, it } from "vitest";
import { SPLASH_MAX_MS, splashShouldBlock } from "./splashGate";

describe("splashShouldBlock", () => {
  it("hides as soon as data is ready", () => {
    expect(splashShouldBlock({ langLoading: false, waitingForRole: false, elapsedMs: 40 })).toBe(false);
  });

  it("stays up while data is loading, before the cap", () => {
    expect(splashShouldBlock({ langLoading: true, waitingForRole: false, elapsedMs: 400 })).toBe(true);
    expect(splashShouldBlock({ langLoading: false, waitingForRole: true, elapsedMs: 900 })).toBe(true);
  });

  it("caps the splash at 1.5s even if data is still loading", () => {
    expect(splashShouldBlock({ langLoading: true, waitingForRole: true, elapsedMs: SPLASH_MAX_MS })).toBe(false);
    expect(splashShouldBlock({ langLoading: true, waitingForRole: true, elapsedMs: SPLASH_MAX_MS + 1 })).toBe(false);
  });
});
