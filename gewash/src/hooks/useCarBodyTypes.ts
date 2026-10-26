import { useEffect, useState } from "react";
import type { Brand, Model } from "@/store/carSlice";
import { customFetch } from "@/utils/customFetch";
import { record } from "@/lib/carResult";
import { readAccessToken } from "@/lib/accessToken";

export type BodyTypeOption = { key: string; name: string };

/** Body types of the model in the year. `choose` is true only when the year has several, so the form asks. */
export function useCarBodyTypes(brand: Brand | null, model: Model | null, year: string) {
  const [types, setTypes] = useState<BodyTypeOption[]>([]);
  const [choose, setChoose] = useState(false);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [retry, setRetry] = useState(0);
  const brandId = brand?.id, modelId = model?.id;
  const catalogSource = model?.source ?? brand?.source ?? "local";

  useEffect(() => {
    setTypes([]);
    setChoose(false);
    setFailed(false);
    if (!brandId || !modelId || !year) return;
    const controller = new AbortController();
    setLoading(true);
    const params = new URLSearchParams({ source: catalogSource, brand_id: String(brandId), model_id: String(modelId), year });
    customFetch(`${import.meta.env.VITE_API_URL}/cars/body-types?${params}`, {
      headers: { Authorization: `Bearer ${readAccessToken()}` },
      signal: controller.signal,
    }).then(async response => {
      if (!response.ok) throw new Error("Body types unavailable");
      const body = record(await response.json());
      if (body?.success !== true || !Array.isArray(body.body_types)) throw new Error("Invalid body type response");
      const options = body.body_types.flatMap(value => {
        const row = record(value);
        return typeof row?.key === "string" && typeof row.name === "string" ? [{ key: row.key, name: row.name }] : [];
      });
      if (!controller.signal.aborted) {
        setTypes(options);
        setChoose(body.choose === true && options.length > 1);
      }
    }).catch(() => {
      if (!controller.signal.aborted) setFailed(true);
    }).finally(() => {
      if (!controller.signal.aborted) setLoading(false);
    });
    return () => controller.abort();
  }, [brandId, modelId, catalogSource, year, retry]);

  return { types, choose, loading, failed, retry: () => setRetry(value => value + 1) };
}
