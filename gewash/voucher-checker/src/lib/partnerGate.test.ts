import { describe, expect, it } from "vitest";
import { partnerScreen, notePartnerResponse, PartnerFlowError, assertPartnerAccess } from "./partnerGate";

describe("partner screens", () => {
  it("blocks every screen except password change while the flag is set", () => {
    expect(partnerScreen(null, false)).toBe("login");
    expect(partnerScreen(null, true)).toBe("login");
    expect(partnerScreen("tok", true)).toBe("password");
    expect(partnerScreen("tok", false)).toBe("vouchers");
  });

  it("treats an authorized 401 as logout and ignores a login 401", () => {
    expect(notePartnerResponse(401, { error: "Unauthorized" }, true)).toBe("unauthorized");
    expect(notePartnerResponse(401, { error: "Unauthorized" }, false)).toBe("ok");
  });

  it("requires a password change on the contract flag", () => {
    expect(notePartnerResponse(403, { success: false, must_change_password: true }, true)).toBe("must_change");
    expect(notePartnerResponse(403, { success: false, message: "Incorrect code" }, true)).toBe("ok");
    expect(() => assertPartnerAccess(403, { must_change_password: true }, true)).toThrow(PartnerFlowError);
  });
});
