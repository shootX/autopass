import { describe, expect, it } from "vitest";
import {
  assertProductionBuildFlags,
  authBypassEnabled,
  sandboxRouteEnabled,
} from "./productionFlags";

describe("auth bypass", () => {
  it("stays off in a production build even when the flag is set", () => {
    expect(authBypassEnabled({ PROD: true, VITE_BYPASS_AUTH: "true" })).toBe(false);
    expect(authBypassEnabled({ PROD: false, VITE_BYPASS_AUTH: "true" })).toBe(true);
    expect(authBypassEnabled({ PROD: false, VITE_BYPASS_AUTH: "false" })).toBe(false);
    expect(authBypassEnabled({ PROD: false })).toBe(false);
  });

  it("fails a production build when the flag is enabled", () => {
    expect(() =>
      assertProductionBuildFlags({
        command: "build",
        mode: "production",
        env: { VITE_BYPASS_AUTH: "true" },
        keys: ["VITE_BYPASS_AUTH"],
      }),
    ).toThrow(/VITE_BYPASS_AUTH/);
    expect(() =>
      assertProductionBuildFlags({
        command: "serve",
        mode: "production",
        env: { VITE_BYPASS_AUTH: "true" },
        keys: ["VITE_BYPASS_AUTH"],
      }),
    ).not.toThrow();
    expect(() =>
      assertProductionBuildFlags({
        command: "build",
        mode: "production",
        env: { VITE_BYPASS_AUTH: "false" },
        keys: ["VITE_BYPASS_AUTH"],
      }),
    ).not.toThrow();
  });
});

describe("payment sandbox route", () => {
  it("is hidden unless test payment is explicitly enabled", () => {
    expect(sandboxRouteEnabled({})).toBe(false);
    expect(sandboxRouteEnabled({ VITE_TEST_PAYMENTS: "false" })).toBe(false);
    expect(sandboxRouteEnabled({ PROD: true })).toBe(false);
    expect(sandboxRouteEnabled({ PROD: true, VITE_TEST_PAYMENTS: "true" })).toBe(true);
    expect(sandboxRouteEnabled({ PROD: false, VITE_TEST_PAYMENTS: "true" })).toBe(true);
  });
});
