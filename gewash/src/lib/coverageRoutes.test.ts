import { describe, expect, it } from "vitest";
import { customerAliasPaths, managerAliasPaths, missingCoverageRoutes } from "./coverageRoutes";

describe("coverage aliases", () => {
  it("wires the screens that already exist", () => {
    expect(customerAliasPaths.home).toBe("/home");
    expect(customerAliasPaths.calendar).toBe("/calendar");
    expect(customerAliasPaths.customerCalendar).toBe("/customer-calendar");
    expect(customerAliasPaths.qr).toBe("/qr");
    expect(customerAliasPaths.profileCars).toBe("/profile/cars");
    expect(managerAliasPaths.scanner).toBe("/manager/scanner");
    expect(managerAliasPaths.bookings).toBe("/manager/bookings");
  });

  it("does not pretend the missing screens exist", () => {
    const wired = new Set<string>([
      ...Object.values(customerAliasPaths),
      ...Object.values(managerAliasPaths),
    ]);
    for (const path of missingCoverageRoutes) {
      expect(wired.has(path)).toBe(false);
    }
  });
});
