import { parseCatalogList, parseYears, type CatalogItem } from "@/lib/carCatalog";
import { customFetch } from "@/utils/customFetch";
import { useEffect, useState } from "react";

const API_URL = import.meta.env.VITE_API_URL;

function authHeaders(): HeadersInit {
  return {
    Authorization: `Bearer ${localStorage.getItem("access_token")}`,
    "Content-Type": "application/json",
    Accept: "application/json",
  };
}

export function useCarModels(brand: CatalogItem | null) {
  const [models, setModels] = useState<CatalogItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  const brandId = brand?.id ?? null;
  const brandSource = brand?.source ?? null;

  useEffect(() => {
    if (brandId === null || brandSource === null) {
      setModels([]);
      setError(null);
      setLoading(false);
      return;
    }

    const token = localStorage.getItem("access_token");
    if (!token) {
      setModels([]);
      setError(new Error("models"));
      setLoading(false);
      return;
    }

    let cancelled = false;
    setModels([]);
    setError(null);
    setLoading(true);
    customFetch(`${API_URL}/cars/brands/${brandId}/models`, { headers: authHeaders() })
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch models");
        return res.json();
      })
      .then((data: unknown) => {
        if (cancelled) return;
        const body = data && typeof data === "object" ? (data as { models?: unknown }) : {};
        setModels(parseCatalogList(body.models).filter((model) => model.source === brandSource));
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(err instanceof Error ? err : new Error("Failed to fetch models"));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [brandId, brandSource]);

  return { models, loading, error };
}

export function useCarYears() {
  const [years, setYears] = useState<number[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    if (!token) return;
    let cancelled = false;
    setLoading(true);
    customFetch(`${API_URL}/cars/years`, { headers: authHeaders() })
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch years");
        return res.json();
      })
      .then((data: unknown) => {
        if (!cancelled) setYears(parseYears(data));
      })
      .catch(() => {
        if (!cancelled) setError("years");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return { years, loading, error };
}
