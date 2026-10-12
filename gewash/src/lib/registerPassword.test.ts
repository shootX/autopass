import { describe, expect, it } from "vitest";
import { buildSetPasswordBody, tempCodeFromRegister } from "./registerPassword";

describe("set_password body", () => {
  it("sends the verify code next to temp_code", () => {
    const body = buildSetPasswordBody({
      password: "secret1",
      tempCode: 42,
      code: "123456",
    });

    expect(body).toEqual({
      password: "secret1",
      temp_code: 42,
      code: "123456",
    });
    expect(body).not.toHaveProperty("sms_code");
  });
});

describe("register response", () => {
  it("keeps temp_code and ignores sms_code", () => {
    const temp = tempCodeFromRegister({
      success: true,
      temp_code: 7,
      sms_code: "999999",
    });

    expect(temp).toBe(7);
  });

  it("returns null when register failed", () => {
    expect(tempCodeFromRegister({ success: false, temp_code: 1, sms_code: "1" })).toBeNull();
  });
});
