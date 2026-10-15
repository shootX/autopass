import type { Brand } from "@/store/carSlice";
import { customFetch } from "@/utils/customFetch";
import { useEffect, useState } from "react";
import { readAccessToken } from "@/lib/accessToken";

const API_URL = import.meta.env.VITE_API_URL;

export function useCarBrands() {
  const [brands, setBrands] = useState<Brand[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);

  useEffect(() => {
    const token = readAccessToken();
    if (!token) {
      console.warn("Нет access_token — бренды не загружаются");
      return;
    }

    setLoading(true);

    customFetch(`${API_URL}/cars/brands`, {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    })
      .then((res) => {
        if (!res.ok) throw new Error("Ошибка загрузки брендов");
        return res.json();
      })
      .then((data) => {
        setBrands(data.brands);
      })
      .catch((err) => {
        setError(true);
        console.error("Ошибка при загрузке брендов:", err);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  return { brands, loading, error };
}
