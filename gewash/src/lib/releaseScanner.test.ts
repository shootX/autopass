import { describe, expect, it, vi } from "vitest";
import { releaseScanner } from "./releaseScanner";

describe("releaseScanner", () => {
  it("stops and clears the camera", async () => {
    const scanner = { stop: vi.fn(async () => {}), clear: vi.fn(async () => {}) };
    await releaseScanner(scanner);
    expect(scanner.stop).toHaveBeenCalledOnce();
    expect(scanner.clear).toHaveBeenCalledOnce();
  });

  it("still clears when stop fails", async () => {
    const scanner = {
      stop: vi.fn(async () => {
        throw new Error("not running");
      }),
      clear: vi.fn(async () => {}),
    };
    await releaseScanner(scanner);
    expect(scanner.clear).toHaveBeenCalledOnce();
  });
});
