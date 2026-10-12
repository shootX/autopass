import { describe, expect, it, vi } from "vitest";
import { scrollToElementId } from "./scrollToId";

describe("scrollToElementId", () => {
  it("scrolls the vehicles anchor into view", () => {
    const el = document.createElement("div");
    el.id = "vehicles";
    el.scrollIntoView = vi.fn();
    document.body.appendChild(el);

    expect(scrollToElementId("vehicles")).toBe(true);
    expect(el.scrollIntoView).toHaveBeenCalledWith({ block: "start" });

    el.remove();
  });

  it("does nothing when the anchor is missing", () => {
    expect(scrollToElementId("vehicles")).toBe(false);
  });
});
