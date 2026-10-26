export type ApprovePackageInput = {
  qrCode?: string | null;
  approveToken?: string | null;
};

export type ApprovePackageBody = {
  approve_token: string;
};

/** The checkqr grant is single-use. Callers must keep `remaining`, which is always empty. */
export function spendApproveToken(token: string | null | undefined): { token: string | null; remaining: null } {
  const trimmed = token?.trim() ?? "";
  return { token: trimmed || null, remaining: null };
}

/** 403 shows an error and rescans. 409 only rescans. */
export function approveShouldRescan(status: number): boolean {
  return status === 403 || status === 409;
}

export function approveShowsError(status: number): boolean {
  return status === 403;
}

/** The API accepts `{approve_token}` only. A package id or static QR is not a grant. */
export function approvePackageBody(input: ApprovePackageInput): ApprovePackageBody | null {
  const token = input.approveToken?.trim() ?? "";
  if (!token) return null;
  return { approve_token: token };
}

export type CheckedPackage = {
  success?: boolean;
  approve_token?: string;
  package?: {
    id?: number;
    end_date?: string;
    created_at?: string;
    used_washes?: number;
    number_of_washes?: number;
    user?: { name?: string | null; surname?: string | null; phone?: string | null };
    package?: { count_washes?: number; car_type?: string | null };
  };
};

export function packageQrReady(result: CheckedPackage | null | undefined): boolean {
  const user = result?.package?.user;
  const pkg = result?.package?.package;
  const meta = result?.package;
  return Boolean(
    result?.success &&
      typeof user?.name === "string" &&
      typeof user?.surname === "string" &&
      typeof pkg?.count_washes === "number" &&
      typeof meta?.used_washes === "number" &&
      typeof meta?.created_at === "string" &&
      typeof meta?.end_date === "string" &&
      typeof result.approve_token === "string" && result.approve_token.trim() !== "",
  );
}

/** checkqr answer for a company car (`kind: "corporate"`). Only an approvable car carries an approve_token. */
export type CorporateCheck = {
  success?: boolean;
  kind: "corporate";
  approve_token?: string | null;
  corporate: {
    id: number;
    company: string;
    mode: "washes" | "discount";
    plate: string | null;
    car_type?: string | null;
    user?: { name?: string | null; surname?: string | null };
    washes_left: number;
    company_washes: number;
    can_approve: boolean;
    reason?: string | null;
    reason_text?: string | null;
  };
};

export function isCorporateCheck(result: unknown): result is CorporateCheck {
  const value = result as Partial<CorporateCheck> | null | undefined;
  return Boolean(
    value?.success &&
      value.kind === "corporate" &&
      typeof value.corporate?.id === "number" &&
      typeof value.corporate.company === "string" &&
      typeof value.corporate.washes_left === "number",
  );
}

/** A company car is approvable only with a token and the server's go-ahead. */
export function corporateCanApprove(result: CorporateCheck): boolean {
  return result.corporate.can_approve && typeof result.approve_token === "string" && result.approve_token.trim() !== "";
}
