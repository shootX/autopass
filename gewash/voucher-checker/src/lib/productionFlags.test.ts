import { describe, expect, it } from "vitest";
import { assertProductionBuildFlags, voucherMockEnabled } from "./productionFlags";

describe("voucher mock", () => {
  it("cannot be enabled in a production build", () => {
    expect(voucherMockEnabled({ PROD: true, VITE_MOCK_VOUCHER_CHECK: "true" })).toBe(false);
    expect(voucherMockEnabled({ PROD: true, VITE_MOCK_VOUCHER_CHECK: "1" })).toBe(false);
    expect(voucherMockEnabled({ PROD: true, VITE_MOCK_VOUCHER_CHECK: "yes" })).toBe(false);
    expect(voucherMockEnabled({ PROD: false, VITE_MOCK_VOUCHER_CHECK: "true" })).toBe(true);
    expect(voucherMockEnabled({ PROD: false, VITE_MOCK_VOUCHER_CHECK: "false" })).toBe(false);
  });

  it("fails the production build when the mock flag is set", () => {
    expect(() =>
      assertProductionBuildFlags({
        command: "build",
        mode: "production",
        env: { VITE_MOCK_VOUCHER_CHECK: "yes" },
        keys: ["VITE_MOCK_VOUCHER_CHECK"],
      }),
    ).toThrow(/VITE_MOCK_VOUCHER_CHECK/);
  });
});
