export type PointBalance = {
  real: number;
  test: number;
};

function count(value: unknown): number {
  const parsed = typeof value === "number" ? value : typeof value === "string" ? Number(value) : Number.NaN;
  if (!Number.isFinite(parsed)) return 0;
  return Math.max(0, Math.trunc(parsed));
}

/** `points` includes sandbox top-ups. Spendable points are `points - test_points`. */
export function splitPoints(points: unknown, testPoints?: unknown): PointBalance {
  const total = count(points);
  if (arguments.length < 2 || testPoints == null) return { real: total, test: 0 };
  const test = Math.min(total, count(testPoints));
  return { real: total - test, test };
}
