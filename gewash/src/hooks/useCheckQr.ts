import { useState, useEffect } from "react";
import { customFetch } from "@/utils/customFetch";
import { readAccessToken } from "@/lib/accessToken";
import type { CorporateCheck } from "@/lib/packageApprove";

export interface QrCheckResponse {
  success: boolean;
  approve_token?: string;
  package: {
    id: number;
    start_date: string;
    end_date: string;
    created_at: string;
    used_washes: number;
    number_of_washes: number;
    user: {
      name: string;
      surname: string;
      phone?: string;
    };
    package: {
      count_washes: number;
      car_type: string;
    };
    car?: {
      plate: string | null;
    };
  };
}

/** A package QR answers with `package`, a company car QR with `kind: "corporate"`. */
export type QrCheckResult = QrCheckResponse | CorporateCheck;

export const useCheckQr = (qrCode: string | null) => {
  const [result, setResult] = useState<QrCheckResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    if (!qrCode) return;

    const fetchQrData = async () => {
      setLoading(true);
      setError(null);

      try {
        const token = readAccessToken();
        const res = await customFetch(`${import.meta.env.VITE_API_URL}/checkqr/${qrCode}`, {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        });

        if (!res.ok) {
          // An expired company car code (410), or a package of a car whose body type is not confirmed (422),
          // says so in the user's language.
          const body = res.status === 410 || res.status === 422 ? await res.json().catch(() => null) : null;
          throw new Error(typeof body?.error === "string" ? body.error : `Request failed: ${res.status}`);
        }
        const data: QrCheckResult = await res.json();
        setResult(data);
      } catch (err) {
        setError(err as Error);
      } finally {
        setLoading(false);
      }
    };

    fetchQrData();
  }, [qrCode]);

  return { result, loading, error };
};
