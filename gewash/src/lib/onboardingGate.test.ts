import { describe, expect, it } from "vitest";
import { shouldShowOnboarding } from "./onboardingGate";

describe("shouldShowOnboarding", () => {
  it("blocks the carousel on auth deep links", () => {
    expect(shouldShowOnboarding(true, false, "/auth")).toBe(false);
    expect(shouldShowOnboarding(true, false, "/manager-auth")).toBe(false);
    expect(shouldShowOnboarding(true, false, "/register")).toBe(false);
    expect(shouldShowOnboarding(true, false, "/renew-password")).toBe(false);
    expect(shouldShowOnboarding(true, false, "/help")).toBe(false);
    expect(shouldShowOnboarding(true, false, "/privacy")).toBe(false);
    expect(shouldShowOnboarding(true, false, "/payment/sandbox")).toBe(false);
  });

  it("still shows the carousel on the logged-out home entry", () => {
    expect(shouldShowOnboarding(true, false, "/")).toBe(true);
  });

  it("hides it after it has been completed or the user is logged in", () => {
    expect(shouldShowOnboarding(true, true, "/")).toBe(false);
    expect(shouldShowOnboarding(false, false, "/")).toBe(false);
  });
});
