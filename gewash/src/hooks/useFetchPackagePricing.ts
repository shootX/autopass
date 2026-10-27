import { useEffect, useState } from "react";
import { customFetch } from "@/utils/customFetch";
import { readAccessToken } from "@/lib/accessToken";

export type PriceOption = {
  id: number;
  package_id: number;
  month: number;
  price: number;
};

export type PackagePricing = {
  id: number;
  car_type: string;
  washes?: number;
  prices: PriceOption[];
};

export function useFetchPackagePricing(carId: number | null) {
  const [packages, setPackages] = useState<PackagePricing[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  

  useEffect(() => {
    if (!carId) return;

    setLoading(true);
    setError("");

    const token = readAccessToken();

    customFetch(`${import.meta.env.VITE_API_URL}/packages?carid=${carId}`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then(async (res) => {
        if (!res.ok) {
          // 422 body_unconfirmed: no package is priced until the car's body type is confirmed; the server says so.
          const body = await res.json().catch(() => null);
          throw new Error(typeof body?.error === "string" ? body.error : "Failed to fetch packages");
        }
        return res.json();
      })
      .then((data) => {
        setPackages(data.packages || []);
      })
      .catch((err) => {
        setError(err.message || "Unknown error");
      })
      .finally(() => {
        setLoading(false);
      });
  }, [carId]);

  return { packages, loading, error };
}
