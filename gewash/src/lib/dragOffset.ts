/** Framer Motion types offset as a keyframe. Only a number can dismiss a sheet. */
export function dragY(value: unknown): number {
  return typeof value === "number" && Number.isFinite(value) ? value : 0;
}
