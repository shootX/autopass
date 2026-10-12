import { describe, expect, it } from "vitest";
import { apiErrorMessage } from "./apiResult";

describe("apiErrorMessage", () => {
  it("accepts HTTP ok with success true", () => {
    expect(apiErrorMessage(true, { success: true }, "fail")).toBeNull();
  });

  it("rejects success false even when HTTP is ok", () => {
    expect(apiErrorMessage(true, { success: false, error: "no" }, "fail")).toBe("no");
  });

  it("rejects a failed HTTP status", () => {
    expect(apiErrorMessage(false, null, "fail")).toBe("fail");
  });
});
