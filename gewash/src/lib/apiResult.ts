export type ApiBody = {
  success?: boolean;
  error?: string;
  message?: string;
};

/** null when the call succeeded. Otherwise a message to show. */
export function apiErrorMessage(ok: boolean, data: ApiBody | null, fallback: string): string | null {
  if (ok && data?.success !== false) return null;
  if (typeof data?.error === "string" && data.error) return data.error;
  if (typeof data?.message === "string" && data.message) return data.message;
  return fallback;
}
