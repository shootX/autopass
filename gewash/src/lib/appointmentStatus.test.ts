import { expect, it } from "vitest";
import { appointmentStatus } from "./appointmentStatus";
it("distinguishes completed, expired and rescheduled bookings consistently", () => {
  expect(appointmentStatus(3)).toBe("Completed");
  expect(appointmentStatus(4)).toBe("Expired");
  expect(appointmentStatus(5)).toBe("Rescheduled");
});

