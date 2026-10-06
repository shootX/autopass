// hooks/useCarModels.ts
import { useEffect, useState } from "react";
import { customFetch } from "@/utils/customFetch";

const API_URL = import.meta.env.VITE_API_URL;

type Model = { id: number; name: string };

export function useCarModels(brandId: number | null) {
  const [models, setModels] = useState<Model[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<Error | null>(null);

  useEffect(() => {
    if (!brandId) return;

    const token = localStorage.getItem("access_token");
    if (!token) {
      setError(new Error("No access token"));
      return;
    }

    setLoading(true);
    customFetch(`${API_URL}/cars/brands/${brandId}/models`, {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    })
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch models");
        return res.json();
      })
      .then((data) => setModels(data.models))
      .catch(setError)
      .finally(() => setLoading(false));
  }, [brandId]);

  return { models, loading, error };
}
