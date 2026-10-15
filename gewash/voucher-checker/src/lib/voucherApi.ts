import { getToken } from "./session";
import { voucherMockEnabled } from "./productionFlags";
import { authJsonHeaders } from "./apiHeaders";
import { assertPartnerAccess } from "./partnerGate";

const OPAQUE_TEMP_CODE = /^[a-f0-9]{32}$/i;

export type VoucherCheckResponse = {
  success?: boolean;
  valid?: boolean;
  message?: string;
  error?: string;
  data?: unknown;
  voucher?: unknown;
  temp_code?: string;
  delivery?: string;
};

export type VoucherUseResponse = {
  success?: boolean;
  message?: string;
  error?: string;
};

/** Only Latin letters + digits, uppercased (for voucher codes). */
function cleanCode(raw: string): string {
  return raw.replace(/[^A-Za-z0-9]/g, "").toUpperCase();
}

/** Pairs of two characters separated by " - " (e.g. X2 - X3 - X4 - X5). */
export function formatCodeForDisplay(raw: string): string {
  const code = cleanCode(raw);
  if (!code) return "";
  const pairs: string[] = [];
  for (let i = 0; i < code.length; i += 2) {
    pairs.push(code.slice(i, i + 2));
  }
  return pairs.join(" - ");
}

function apiUrl(): string {
  const base = String(import.meta.env.VITE_API_URL || "").trim().replace(/\/+$/, "");
  if (!base) throw new Error("Missing VITE_API_URL");
  return base;
}

function checkPath(): string {
  const p = String(import.meta.env.VITE_VOUCHER_CHECK_PATH || "/partner/voucher/check").trim();
  return p.startsWith("/") ? p : `/${p}`;
}

function voucherUsePath(): string {
  const p = String(import.meta.env.VITE_VOUCHER_USE_PATH || "/partner/voucher/use").trim();
  return p.startsWith("/") ? p : `/${p}`;
}

function parseBool(v: unknown): boolean | undefined {
  if (typeof v === "boolean") return v;
  if (typeof v === "number") return v !== 0;
  if (typeof v === "string") {
    const s = v.trim().toLowerCase();
    if (["true", "1", "yes", "ok", "valid"].includes(s)) return true;
    if (["false", "0", "no", "invalid"].includes(s)) return false;
  }
  return undefined;
}

function asRecord(v: unknown): Record<string, unknown> | null {
  return v && typeof v === "object" && !Array.isArray(v) ? (v as Record<string, unknown>) : null;
}

export function opaqueTempCode(raw: unknown): string | null {
  if (typeof raw !== "string") return null;
  const value = raw.trim();
  return OPAQUE_TEMP_CODE.test(value) ? value : null;
}

function pickTempCode(json: unknown): string | null {
  if (!json || typeof json !== "object") return null;
  const o = json as Record<string, unknown>;
  const data = asRecord(o.data);
  return opaqueTempCode(o.temp_code ?? data?.temp_code);
}

export type VoucherCheckResult =
  | { status: "idle" }
  | { status: "loading"; code: string }
  | { status: "valid"; code: string; payload: VoucherCheckResponse }
  | { status: "invalid"; code: string; payload: VoucherCheckResponse }
  | { status: "error"; code: string; message: string };

/** Mock is off by default. A production build ignores VITE_MOCK_VOUCHER_CHECK. */
function voucherCheckMockEnabled(): boolean {
  return voucherMockEnabled({
    PROD: import.meta.env.PROD,
    VITE_MOCK_VOUCHER_CHECK: import.meta.env.VITE_MOCK_VOUCHER_CHECK,
  });
}

export type CheckVoucherResult = {
  code: string;
  ok: boolean;
  tempCode?: string;
  payload: VoucherCheckResponse;
};

export async function checkVoucher(rawCode: string): Promise<CheckVoucherResult> {
  const code = cleanCode(rawCode);
  if (!code) throw new Error("Empty voucher code");

  if (voucherCheckMockEnabled()) {
    await new Promise((r) => setTimeout(r, 350));
    return {
      code,
      ok: true,
      tempCode: "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      payload: {
        success: true,
        valid: true,
        message: "OK (mock)",
        delivery: "simulated",
        temp_code: "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      },
    };
  }

  const url = `${apiUrl()}${checkPath()}`;
  const token = getToken();
  const res = await fetch(url, {
    method: "POST",
    headers: authJsonHeaders(token),
    body: JSON.stringify({ code }),
  });

  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    // ignore
  }

  const payload: VoucherCheckResponse = asRecord(json)
    ? (json as VoucherCheckResponse)
    : { success: false, error: "Bad response" };

  assertPartnerAccess(res.status, json, Boolean(token));

  const tempCodeStr = pickTempCode(json);
  const successFlag = parseBool(payload.success);

  if (payload.delivery === "unknown" && tempCodeStr) {
    return {
      code,
      ok: true,
      tempCode: tempCodeStr,
      payload: { ...payload, delivery: "unknown", temp_code: tempCodeStr, success: false },
    };
  }

  if (!res.ok) {
    const msg = payload.message || payload.error || `Request failed (${res.status})`;
    return {
      code,
      ok: false,
      payload: { ...payload, valid: false, message: msg, success: payload.success ?? false },
    };
  }

  const ok = tempCodeStr !== null || successFlag === true;
  if (!ok) {
    const msg = payload.message || payload.error || "Invalid voucher";
    return { code, ok: false, payload: { ...payload, valid: false, message: msg } };
  }

  return {
    code,
    ok: true,
    tempCode: tempCodeStr ?? undefined,
    payload: { ...payload, success: successFlag ?? true, temp_code: tempCodeStr ?? undefined },
  };
}

export type UseVoucherResult = { ok: boolean; payload: VoucherUseResponse };

/** Confirm SMS: POST { temp_code, code } — code is the SMS digits the user entered. */
export async function confirmVoucherUse(tempCode: string, smsCode: string): Promise<UseVoucherResult> {
  const sms = smsCode.replace(/\D/g, "").trim();
  const tc = opaqueTempCode(tempCode);
  if (!tc) throw new Error("Session expired. Request the code again.");
  if (!sms) throw new Error("Enter the SMS code");

  if (voucherCheckMockEnabled()) {
    await new Promise((r) => setTimeout(r, 400));
    return { ok: true, payload: { success: true } };
  }

  const url = `${apiUrl()}${voucherUsePath()}`;
  const token = getToken();
  const res = await fetch(url, {
    method: "POST",
    headers: authJsonHeaders(token),
    body: JSON.stringify({ temp_code: tc, code: sms }),
  });

  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    // ignore
  }

  const payload: VoucherUseResponse = asRecord(json)
    ? (json as VoucherUseResponse)
    : { success: false, error: "Bad response" };

  assertPartnerAccess(res.status, json, Boolean(token));

  const success = parseBool(payload.success) === true;

  if (!res.ok || !success) {
    const msg = payload.message || payload.error || `Request failed (${res.status})`;
    return { ok: false, payload: { ...payload, success: false, message: msg } };
  }

  return { ok: true, payload: { ...payload, success: true } };
}
