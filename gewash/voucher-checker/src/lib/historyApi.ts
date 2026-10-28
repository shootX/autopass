import { getToken } from "./session";
import { authJsonHeaders } from "./apiHeaders";
import { assertPartnerAccess } from "./partnerGate";

export type RedeemedVoucher = {
  code: string;
  voucher: string | null;
  client: string | null;
  usedAt: string | null;
};

function asText(v: unknown): string | null {
  return typeof v === "string" && v.trim() !== "" ? v : null;
}

/** GET /partner/vouchers/history: this partner's last 100 redeemed vouchers, newest first. */
export async function fetchRedeemedVouchers(): Promise<RedeemedVoucher[]> {
  const base = String(import.meta.env.VITE_API_URL || "").trim().replace(/\/+$/, "");
  if (!base) throw new Error("Missing VITE_API_URL");
  const token = getToken();
  const res = await fetch(`${base}/partner/vouchers/history`, { headers: authJsonHeaders(token) });

  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    json = null;
  }
  assertPartnerAccess(res.status, json, Boolean(token));
  if (!res.ok || !json || typeof json !== "object") {
    throw new Error(`Could not load history (${res.status})`);
  }

  const rows = (json as { vouchers?: unknown }).vouchers;
  if (!Array.isArray(rows)) return [];

  return rows.flatMap((row): RedeemedVoucher[] => {
    if (!row || typeof row !== "object") return [];
    const r = row as Record<string, unknown>;
    const code = asText(r.code);
    if (!code) return [];
    return [{ code, voucher: asText(r.voucher), client: asText(r.client), usedAt: asText(r.used_at) }];
  });
}
