import { describe, expect, it } from "vitest";
import { LOGIN_PHONE_MAX_LENGTH, localLoginPhone } from "./loginPhone";

describe("localLoginPhone", () => {
  it("keeps a compact local number", () => {
    expect(localLoginPhone("555112233")).toBe("555112233");
  });

  it("accepts spaces and dashes", () => {
    expect(localLoginPhone("555 11 22 33")).toBe("555112233");
    expect(localLoginPhone("555-11-22-33")).toBe("555112233");
  });

  it("strips a 995 prefix from spaced and compact forms", () => {
    expect(localLoginPhone("+995555112233")).toBe("555112233");
    expect(localLoginPhone("+995 555 11 22 33")).toBe("555112233");
    expect(localLoginPhone("995555112233")).toBe("555112233");
    expect(localLoginPhone("995 555-11-22-33")).toBe("555112233");
  });

  it("does not treat a 9-digit number starting with 995 as a country code", () => {
    expect(localLoginPhone("995123456")).toBe("995123456");
  });

  it("caps the local number at 9 digits", () => {
    expect(localLoginPhone("555 11 22 33999")).toBe("555112233");
  });

  it("allows a formatted paste to arrive before truncation", () => {
    const formatted = "+995 555 11 22 33";
    expect(formatted.length).toBeLessThanOrEqual(LOGIN_PHONE_MAX_LENGTH);
    expect(localLoginPhone(formatted.slice(0, LOGIN_PHONE_MAX_LENGTH))).toBe("555112233");
  });
});
