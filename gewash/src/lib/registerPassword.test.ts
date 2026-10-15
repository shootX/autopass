import { describe, expect, it } from "vitest";
import { buildSetPasswordBody, readSmsChallenge, smsChallengeContinues, tempCodeFromRegister } from "./registerPassword";

const ID = "0123456789abcdef0123456789abcdef";

describe("set_password body", () => {
  it("sends only password and setup_token", () => {
    const body = buildSetPasswordBody({ password: "secret1", setupToken: "tok_abcdefghijklmnopqrstuvwxyz" });
    expect(body).toEqual({ password: "secret1", setup_token: "tok_abcdefghijklmnopqrstuvwxyz" });
    expect(body).not.toHaveProperty("code");
    expect(body).not.toHaveProperty("temp_code");
  });
});

describe("register response", () => {
  it("keeps the opaque temp_code", () => {
    expect(tempCodeFromRegister({ success: true, temp_code: ID, delivery: "simulated" })).toBe(ID);
  });

  it("keeps temp_code when delivery is unknown", () => {
    expect(tempCodeFromRegister({ success: false, temp_code: ID, delivery: "unknown" })).toBe(ID);
  });

  it("rejects the old numeric contract", () => {
    expect(tempCodeFromRegister({ success: true, temp_code: 7 })).toBeNull();
  });

  it("returns null on failed delivery", () => {
    expect(tempCodeFromRegister({ success: false, temp_code: ID, delivery: "failed" })).toBeNull();
  });

  it("treats accepted and simulated 200 responses as success", () => {
    expect(smsChallengeContinues({ success: true, temp_code: ID, delivery: "accepted" })).toBe(true);
    expect(smsChallengeContinues({ success: true, temp_code: ID, delivery: "simulated" })).toBe(true);
    expect(smsChallengeContinues({ success: false, temp_code: ID, delivery: "failed" })).toBe(false);
    expect(smsChallengeContinues({ success: true, temp_code: ID, delivery: "failed" })).toBe(false);
  });

  it("does not keep an OTP that arrived in the status text", () => {
    const challenge = readSmsChallenge({
      success: false,
      delivery: "failed",
      error: "code 123456",
    });
    expect(challenge.detail).toBeNull();
    expect(challenge.tempCode).toBeNull();
  });
});
