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

export type SmsDelivery = "simulated" | "accepted" | "failed" | "unknown";

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
