import { describe, expect, it } from "vitest";
import { referralIpUrl } from "./referralIp";

describe("referral IP url", () => {
  it("uses the app API", () => {
    const url = referralIpUrl("https://wash.example/api");
    expect(url).toBe("https://wash.example/api/ref/ip");
    expect(url).not.toContain("geocar.ge");
  });
});
