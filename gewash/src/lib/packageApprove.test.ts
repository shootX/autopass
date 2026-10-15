import { describe, expect, it } from "vitest";
import { approvePackageBody, approveShouldRescan, approveShowsError, packageQrReady, spendApproveToken } from "./packageApprove";

describe("package approve", () => {
  it("does not approve with a package id alone", () => {
    expect(approvePackageBody({})).toBeNull();
    expect(approvePackageBody({ qrCode: "  ", approveToken: "" })).toBeNull();
  });

  it("sends only the checkqr approve_token", () => {
    expect(approvePackageBody({ qrCode: "QR123", approveToken: "tok" })).toEqual({
      approve_token: "tok",
    });
    expect(approvePackageBody({ qrCode: "QR123" })).toBeNull();
  });

  it("uses an approve_token once and then forgets it", () => {
    const first = spendApproveToken(" tok ");
    expect(first.token).toBe("tok");
    expect(first.remaining).toBeNull();
    expect(spendApproveToken(first.remaining)).toEqual({ token: null, remaining: null });
    expect(approvePackageBody({ qrCode: "QR123", approveToken: first.remaining })).toBeNull();
  });

  it("shows an error and rescans on 403, and rescans on 409", () => {
    expect(approveShouldRescan(403)).toBe(true);
    expect(approveShowsError(403)).toBe(true);
    expect(approveShouldRescan(409)).toBe(true);
    expect(approveShowsError(409)).toBe(false);
    expect(approveShouldRescan(422)).toBe(false);
    expect(approveShouldRescan(200)).toBe(false);
    expect(approveShouldRescan(500)).toBe(false);
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