import { describe, expect, it } from "vitest";
import { approvePackageBody, packageQrReady } from "./packageApprove";

describe("package approve", () => {
  it("does not approve with a package id alone", () => {
    expect(approvePackageBody({})).toBeNull();
    expect(approvePackageBody({ qrCode: "  ", approveToken: "" })).toBeNull();
  });

  it("sends the scanned qr_code and the checkqr approve_token", () => {
    expect(approvePackageBody({ qrCode: "QR123", approveToken: "tok" })).toEqual({
      qr_code: "QR123",
      approve_token: "tok",
    });
  });

  it("accepts a checkqr payload that no longer includes a phone number", () => {
    expect(
      packageQrReady({
        success: true,
        approve_token: "tok",
        package: {
          id: 4,
          end_date: "2026-12-01",
          created_at: "2026-10-01",
          used_washes: 1,
          number_of_washes: 5,
          user: { name: "ნინო", surname: "ბერიძე" },
          package: { count_washes: 5 },
        },
      }),
    ).toBe(true);
  });
});