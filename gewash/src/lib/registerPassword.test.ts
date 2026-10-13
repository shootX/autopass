import { describe, expect, it } from "vitest";
import { buildSetPasswordBody, tempCodeFromRegister } from "./registerPassword";

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
});
