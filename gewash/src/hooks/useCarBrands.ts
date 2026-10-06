import type { Brand } from "@/store/carSlice";
import { customFetch } from "@/utils/customFetch";
import { useEffect, useState } from "react";

const API_URL = import.meta.env.VITE_API_URL;

export function useCarBrands() {
  const [brands, setBrands] = useState<Brand[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem("access_token");
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
        console.error("Ошибка при загрузке брендов:", err);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  return { brands, loading };
}
