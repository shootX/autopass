export type SetPasswordBody = {
  password: string;
  temp_code: number;
  code: string;
};

/** Body for POST /api/register/set_password. `code` is the SMS the user typed on verify. */
export function buildSetPasswordBody(input: {
  password: string;
  tempCode: number;
  code: string;
}): SetPasswordBody {
  return {
    password: input.password,
    temp_code: input.tempCode,
    code: input.code,
  };
}

/**
 * Ticket id from POST /api/register.
 * `sms_code` must not be read — the server no longer returns it, and the SPA never uses it.
 */
export function tempCodeFromRegister(data: {
  success?: boolean;
  temp_code?: unknown;
  sms_code?: unknown;
}): number | null {
  if (!data.success) return null;
  const value = data.temp_code;
  if (typeof value === "number" && Number.isInteger(value)) return value;
  if (typeof value === "string" && /^\d+$/.test(value)) return Number(value);
  return null;
}
