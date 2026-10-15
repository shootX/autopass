import { describe, expect, it } from "vitest";
import { splitPoints } from "./pointBalance";

describe("test points", () => {
  it("keeps the whole balance when the API does not send test_points", () => {
    expect(splitPoints(120)).toEqual({ real: 120, test: 0 });
    expect(splitPoints(0)).toEqual({ real: 0, test: 0 });
  });

  it("shows sandbox points apart from points that can be spent", () => {
    expect(splitPoints(100, 40)).toEqual({ real: 60, test: 40 });
    expect(splitPoints(15, 0)).toEqual({ real: 15, test: 0 });
    expect(splitPoints(10, 25)).toEqual({ real: 0, test: 10 });
  });
});
