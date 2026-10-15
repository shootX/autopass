import { describe, expect, it } from "vitest";
import { parseSmsDelivery, publicSmsDetail, smsStatusCopy, visibleSmsDelivery } from "./smsDelivery";

describe("sms status copy", () => {
  it("labels simulated sends as a test and not a real phone message", () => {
    for (const lang of ["ka", "en", "ru"] as const) {
      const copy = smsStatusCopy("simulated", lang);
      expect(copy.simulated).toBe(true);
      expect(copy.body.length).toBeGreaterThan(10);
    }
    expect(smsStatusCopy("simulated", "ka").body).toContain("სატესტო რეჟიმში");
    expect(smsStatusCopy("simulated", "en").body.toLowerCase()).toContain("test mode");
    expect(smsStatusCopy("simulated", "en").body.toLowerCase()).toContain("not sent");
  });

  it("says accepted without claiming the message was delivered", () => {
    const en = smsStatusCopy("accepted", "en");
    expect(en.title).toBe("Accepted");
    expect(en.body).toContain("does not mean delivered");
    expect(en.body.toLowerCase()).not.toMatch(/has been delivered|was delivered|successfully delivered/);
    expect(smsStatusCopy("accepted", "ka").title).toBe("მიღებულია");
    expect(smsStatusCopy("accepted", "ka").body).toContain("მიღებულია");
    expect(smsStatusCopy("accepted", "ka").body).toContain("არ ნიშნავს მიტანას");
  });

  it("keeps failed and unknown distinct", () => {
    expect(smsStatusCopy("failed", "ka").title).not.toBe(smsStatusCopy("unknown", "ka").title);
    expect(smsStatusCopy("unknown", "en").body.toLowerCase()).toContain("not sent again");
    expect(smsStatusCopy("failed", "ka", "SMS ბალანსი არასაკმარისია. სცადეთ მოგვიანებით.").body).toContain(
      "ბალანსი",
    );
  });

  it("shows a simulated notice only outside production", () => {
    expect(visibleSmsDelivery("simulated", false)).toBe("simulated");
    expect(visibleSmsDelivery("simulated", true)).toBeNull();
    expect(visibleSmsDelivery("accepted", true)).toBe("accepted");
    expect(visibleSmsDelivery(null, false)).toBeNull();
  });

  it("never echoes a 6-digit code", () => {
    expect(publicSmsDetail("code 123456")).toBeNull();
    expect(smsStatusCopy("failed", "en", "your code is 123456").body).not.toContain("123456");
    expect(parseSmsDelivery("delivered")).toBeNull();
    expect(parseSmsDelivery("accepted")).toBe("accepted");
  });
});
