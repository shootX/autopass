export type ApprovePackageInput = {
  qrCode?: string | null;
  approveToken?: string | null;
};

export type ApprovePackageBody = {
  qr_code?: string;
  approve_token?: string;
};

/** The checkqr grant is single-use. Callers must keep `remaining`, which is always empty. */
export function spendApproveToken(token: string | null | undefined): { token: string | null; remaining: null } {
  const trimmed = token?.trim() ?? "";
  return { token: trimmed || null, remaining: null };
}

/** A rejected grant cannot be posted again. The manager scans a new code. */
export function approveShouldRescan(status: number): boolean {
  return status === 403 || status === 409 || status === 422;
}

/** Bare package id is rejected by the API. Require the scanned QR or the checkqr token. */
export function approvePackageBody(input: ApprovePackageInput): ApprovePackageBody | null {
  const qr = input.qrCode?.trim() ?? "";
  const token = input.approveToken?.trim() ?? "";
  if (!qr && !token) return null;
  const body: ApprovePackageBody = {};
  if (qr) body.qr_code = qr;
  if (token) body.approve_token = token;
  return body;
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
      (typeof result.approve_token === "string" || typeof meta?.id === "number"),
  );
}
