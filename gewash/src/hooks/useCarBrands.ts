import { parseCatalogList } from "@/lib/carCatalog";
import type { Brand } from "@/store/carSlice";
import { customFetch } from "@/utils/customFetch";
import { useEffect, useState } from "react";

const API_URL = import.meta.env.VITE_API_URL;

export function useCarBrands() {
  const [brands, setBrands] = useState<Brand[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    if (!token) {
      setError(true);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(false);

    customFetch(`${API_URL}/cars/brands`, {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    })
      .then((res) => {
        if (!res.ok) throw new Error("brands");
        return res.json();
      })
      .then((data: unknown) => {
        if (cancelled) return;
        const body = data && typeof data === "object" ? (data as { brands?: unknown }) : {};
        setBrands(parseCatalogList(body.brands));
      })
      .catch(() => {
        if (!cancelled) setError(true);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return { brands, loading, error };
}
