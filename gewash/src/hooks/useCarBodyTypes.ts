import { useEffect, useState } from "react";
import type { Brand, Model } from "@/store/carSlice";
import { customFetch } from "@/utils/customFetch";
import { record } from "@/lib/carResult";
import { readAccessToken } from "@/lib/accessToken";
import { readBodyTypes, type BodyTypeOption } from "@/lib/carBody";

export type { BodyTypeOption };

/**
 * Body types of the model in the year, as geoapi.ge names them. choose: several (or one next to unnamed variants,
 * incomplete), so the form offers them; the car is saved without a pick too and waits for it. unknown: geoapi.ge
 * can't tell (unavailable: it did not answer); the car is saved and its type is checked again later.
 */
export function useCarBodyTypes(brand: Brand | null, model: Model | null, year: string) {
  const [types, setTypes] = useState<BodyTypeOption[]>([]);
  const [choose, setChoose] = useState(false);
  const [unknown, setUnknown] = useState(false);
  const [incomplete, setIncomplete] = useState(false);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [retry, setRetry] = useState(0);
  const brandId = brand?.id, modelId = model?.id;
  const catalogSource = model?.source ?? brand?.source ?? "local";

  useEffect(() => {
    setTypes([]);
    setChoose(false);
    setUnknown(false);
    setIncomplete(false);
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
      const options = readBodyTypes(body.body_types);
      if (!controller.signal.aborted) {
        setTypes(options);
        setChoose(body.choose === true && options.length > 0);
        setIncomplete(body.incomplete === true);
        // geoapi.ge did not answer: the same retry as a failed request.
        setFailed(body.unavailable === true);
        setUnknown(body.status === "unknown" && body.unavailable !== true);
      }
    }).catch(() => {
      if (!controller.signal.aborted) setFailed(true);
    }).finally(() => {
      if (!controller.signal.aborted) setLoading(false);
    });
    return () => controller.abort();
  }, [brandId, modelId, catalogSource, year, retry]);

  return { types, choose, unknown, incomplete, loading, failed, retry: () => setRetry(value => value + 1) };
}
