export type PartnerSignal = "ok" | "unauthorized" | "must_change";

export class PartnerFlowError extends Error {
  kind: Exclude<PartnerSignal, "ok">;

  constructor(kind: Exclude<PartnerSignal, "ok">) {
    super(kind === "must_change" ? "Password change required" : "Session expired");
    this.name = "PartnerFlowError";
    this.kind = kind;
  }
}

export function partnerScreen(token: string | null, mustChange: boolean): "login" | "password" | "vouchers" {
  if (!token) return "login";
  if (mustChange) return "password";
  return "vouchers";
}

function flag(body: unknown, key: string): boolean {
  if (!body || typeof body !== "object") return false;
  return (body as Record<string, unknown>)[key] === true;
}

/** 401 with a token ends the session. 403 must_change_password blocks every other screen. Login 401 does not. */
export function notePartnerResponse(status: number, body: unknown, hadToken: boolean): PartnerSignal {
  if (status === 401 && hadToken) return "unauthorized";
  if (status === 403 && flag(body, "must_change_password")) return "must_change";
  return "ok";
}

export function assertPartnerAccess(status: number, body: unknown, hadToken: boolean): void {
  const signal = notePartnerResponse(status, body, hadToken);
  if (signal !== "ok") throw new PartnerFlowError(signal);
}
