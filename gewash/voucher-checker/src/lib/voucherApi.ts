import { getToken } from "./session";
import { authJsonHeaders } from "./apiHeaders";

export type VoucherCheckResponse = {
  success?: boolean;
  valid?: boolean;
  message?: string;
  error?: string;
  data?: unknown;
  voucher?: unknown;
  temp_code?: number | string;
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

function usePath(): string {
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

function pickTempCode(json: unknown): string | null {
  if (!json || typeof json !== "object") return null;
  const o = json as Record<string, unknown>;
  const data = asRecord(o.data);
  const raw = o.temp_code ?? data?.temp_code;
  if (typeof raw === "number" && Number.isFinite(raw)) return String(raw);
  if (typeof raw === "string" && raw.trim()) return raw.trim();
  return null;
}

export type VoucherCheckResult =
  | { status: "idle" }
  | { status: "loading"; code: string }
  | { status: "valid"; code: string; payload: VoucherCheckResponse }
  | { status: "invalid"; code: string; payload: VoucherCheckResponse }
  | { status: "error"; code: string; message: string };

/** Mock is off by default — real `fetch` to the API. Set `VITE_MOCK_VOUCHER_CHECK=true` for UI work without backend. */
function voucherCheckMockEnabled(): boolean {
  const v = String(import.meta.env.VITE_MOCK_VOUCHER_CHECK ?? "false").trim().toLowerCase();
  return v === "true" || v === "1" || v === "yes";
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
      tempCode: "mock-temp-code",
      payload: { success: true, valid: true, message: "OK (mock)", temp_code: "mock-temp-code" },
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

  const tempCodeStr = pickTempCode(json);
  const successFlag = parseBool(payload.success);

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
    payload: { ...payload, success: successFlag ?? true, temp_code: tempCodeStr ?? payload.temp_code },
  };
}

export type UseVoucherResult = { ok: boolean; payload: VoucherUseResponse };

/** Confirm SMS: POST { temp_code, code } — code is the SMS digits the user entered. */
export async function useVoucher(tempCode: string | number, smsCode: string): Promise<UseVoucherResult> {
  const sms = smsCode.replace(/\D/g, "").trim();
  const tcRaw = String(tempCode).trim();
  if (!tcRaw) throw new Error("Session expired. Request the code again.");
  if (!sms) throw new Error("Enter the SMS code");

  if (voucherCheckMockEnabled()) {
    await new Promise((r) => setTimeout(r, 400));
    return { ok: true, payload: { success: true } };
  }

  const url = `${apiUrl()}${usePath()}`;
  const token = getToken();
  const body: Record<string, unknown> = { code: sms };
  if (/^\d+$/.test(tcRaw)) {
    const n = Number(tcRaw);
    body.temp_code = n <= Number.MAX_SAFE_INTEGER ? n : tcRaw;
  } else {
    body.temp_code = tcRaw;
  }

  const res = await fetch(url, {
    method: "POST",
    headers: authJsonHeaders(token),
    body: JSON.stringify(body),
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

  const success = parseBool(payload.success) === true;

  if (!res.ok || !success) {
    const msg = payload.message || payload.error || `Request failed (${res.status})`;
    return { ok: false, payload: { ...payload, success: false, message: msg } };
  }

  return { ok: true, payload: { ...payload, success: true } };
}
