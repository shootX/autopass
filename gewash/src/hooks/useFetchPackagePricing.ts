import { useEffect, useState } from "react";
import { customFetch } from "@/utils/customFetch";

export type PriceOption = {
  id: number;
  package_id: number;
  month: number;
  price: number;
};

export type PackagePricing = {
  id: number;
  car_type: string;
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

    const token = localStorage.getItem("access_token");

    customFetch(`${import.meta.env.VITE_API_URL}/packages?carid=${carId}`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch packages");
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
