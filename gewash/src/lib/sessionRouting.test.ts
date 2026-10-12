import { describe, expect, it } from "vitest";
import { MANAGER_HOME, postLoginPath, selectAppRoute, shouldCheckCars } from "./sessionRouting";

describe("selectAppRoute", () => {
  it("does not choose a route before /me resolves", () => {
    expect(selectAppRoute("pending", null)).toBe("loading");
    expect(selectAppRoute("pending", "manager")).toBe("loading");
    expect(selectAppRoute("pending", "customer")).toBe("loading");
  });

  it("sends a failed /me 401 to login", () => {
    expect(selectAppRoute("unauthorized", null)).toBe("login");
    expect(selectAppRoute("unauthorized", "manager")).toBe("login");
  });

  it("selects a home only after /me is ready", () => {
    expect(selectAppRoute("ready", "manager")).toBe("manager");
    expect(selectAppRoute("ready", "customer")).toBe("customer");
    expect(selectAppRoute("ready", null)).toBe("hold");
    expect(selectAppRoute("error", null)).toBe("hold");
  });
});

describe("car-check and post-login path", () => {
  it("skips car-check for a manager and lands on the manager page", () => {
    expect(shouldCheckCars("manager", false)).toBe(false);
    expect(postLoginPath("manager")).toBe(MANAGER_HOME);
    expect(MANAGER_HOME).toBe("/manager-qr-page");
  });

  it("still car-checks a regular user", () => {
    expect(shouldCheckCars("customer", false)).toBe(true);
    expect(postLoginPath("customer")).toBe("/");
  });

  it("does not car-check or navigate before the role is known", () => {
    expect(shouldCheckCars(null, false)).toBe(false);
    expect(postLoginPath(null)).toBeNull();
  });
});
