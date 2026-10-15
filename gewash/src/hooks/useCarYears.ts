import { useEffect, useState } from "react";
import type { Brand, Model } from "@/store/carSlice";
import { customFetch } from "@/utils/customFetch";
import { record } from "@/lib/carResult";
import { readAccessToken } from "@/lib/accessToken";

export function useCarYears(brand: Brand | null, model: Model | null) {
  const [years, setYears] = useState<number[]>([]);
  const [source, setSource] = useState<"manual" | "catalog">("manual");
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [retry, setRetry] = useState(0);
  const brandId = brand?.id, modelId = model?.id;
  const catalogSource = model?.source ?? brand?.source ?? "local";

  useEffect(() => {
    setYears([]);
    setFailed(false);
    if (!brandId || !modelId) return;
    const controller = new AbortController();
    setLoading(true);
    const params = new URLSearchParams({ source: catalogSource, brand_id: String(brandId), model_id: String(modelId) });
    customFetch(`${import.meta.env.VITE_API_URL}/cars/years?${params}`, {
      headers: { Authorization: `Bearer ${readAccessToken()}` },
      signal: controller.signal,
    }).then(async response => {
      if (!response.ok) throw new Error("Years unavailable");
      const body = record(await response.json());
      if (body?.success !== true || !Array.isArray(body.years)) throw new Error("Invalid year response");
      const options = body.years.filter((year): year is number => typeof year === "number" && Number.isInteger(year));
      if (!options.length) throw new Error("No years");
      if (!controller.signal.aborted) {
        setYears(options);
        setSource(body.source === "catalog" ? "catalog" : "manual");
      }
    }).catch(() => {
      if (!controller.signal.aborted) setFailed(true);
    }).finally(() => {
      if (!controller.signal.aborted) setLoading(false);
    });
    return () => controller.abort();
  }, [brandId, modelId, catalogSource, retry]);

  return { years, source, loading, failed, retry: () => setRetry(value => value + 1) };
}

