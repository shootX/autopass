import { useState, useEffect } from "react";
import { customFetch } from "@/utils/customFetch";

export interface QrCheckResponse {
  success: boolean;
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
      phone: string;
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

export const useCheckQr = (qrCode: string | null) => {
  const [result, setResult] = useState<QrCheckResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    if (!qrCode) return;

    const fetchQrData = async () => {
      setLoading(true);
      setError(null);

      try {
        const token = localStorage.getItem("access_token");
        const res = await customFetch(`${import.meta.env.VITE_API_URL}/checkqr/${qrCode}`, {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        });

        if (!res.ok) throw new Error(`Request failed: ${res.status}`);
        const data: QrCheckResponse = await res.json();
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
