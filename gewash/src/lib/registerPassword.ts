import { parseSmsDelivery, publicSmsDetail, type SmsDelivery } from "@/lib/smsDelivery";

export type SetPasswordBody = {
  password: string;
  setup_token: string;
};

/**
 * Body for POST /api/register/set_password (stage 1 contract).
 * `setup_token` is the one-time token from POST /api/register/verify. The SMS code is never re-sent.
 */
export function buildSetPasswordBody(input: { password: string; setupToken: string }): SetPasswordBody {
  return {
    password: input.password,
    setup_token: input.setupToken,
  };
}

export type { SmsDelivery } from "@/lib/smsDelivery";

/**
 * Opaque challenge id (string) from POST /api/register (and reset/phone/voucher).
 * Returned on success, and on delivery "unknown" so the user can still type a code that may arrive.
 * Numeric ids from the old contract are rejected.
 */
export function tempCodeFromRegister(data: {
  success?: boolean;
  temp_code?: unknown;
  delivery?: unknown;
}): string | null {
  if (!data.success && data.delivery !== "unknown") return null;
  const value = data.temp_code;
  if (typeof value === "string" && /^[a-f0-9]{32}$/i.test(value)) return value;
  return null;
}

/** `/register`, `/change_password` and `/change_phone` succeed on accepted or simulated. */
export function smsChallengeContinues(data: {
  success?: boolean;
  temp_code?: unknown;
  delivery?: unknown;
}): boolean {
  if (data.success !== true) return false;
  const delivery = parseSmsDelivery(data.delivery);
  if (delivery !== "accepted" && delivery !== "simulated") return false;
  return tempCodeFromRegister(data) != null;
}

export function readSmsChallenge(data: {
  success?: boolean;
  temp_code?: unknown;
  delivery?: unknown;
  message?: unknown;
  error?: unknown;
}): { tempCode: string | null; delivery: SmsDelivery | null; detail: string | null } {
  const detailSource = typeof data.message === "string" ? data.message : data.error;
  return {
    tempCode: tempCodeFromRegister(data),
    delivery: parseSmsDelivery(data.delivery),
    detail: publicSmsDetail(detailSource),
  };
}
