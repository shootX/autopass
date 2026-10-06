/** Country code shown beside the phone field. Server lang files sometimes ship an empty prefix, and useTranslation() then falls back to the raw key. */
export function phonePrefix(value: string | undefined | null): string {
  const text = (value ?? "").trim();
  if (!text || text.endsWith(".prefix")) return "+995";
  return text;
}
