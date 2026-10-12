/** Longest accepted login string before it is normalized, e.g. "+995 555 11 22 33". */
export const LOGIN_PHONE_MAX_LENGTH = 18;

/**
 * Local 9-digit phone for login.
 * Spaces, dashes, and a leading 995 / +995 are removed.
 * A 9-digit number that itself starts with 995 is kept.
 */
export function localLoginPhone(input: string): string {
  const digits = input.replace(/[\s-]/g, "").replace(/\D/g, "");
  const withoutCountry = digits.startsWith("995") && digits.length > 9 ? digits.slice(3) : digits;
  return withoutCountry.slice(0, 9);
}
