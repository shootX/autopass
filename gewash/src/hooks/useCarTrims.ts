import { useEffect, useState } from "react";
import type { Brand, Model } from "@/store/carSlice";
import { customFetch } from "@/utils/customFetch";
import { record } from "@/lib/carResult";
import { readAccessToken } from "@/lib/accessToken";

export type TrimOption = { id: number; name: string; detail: string; photo: string | null };

/** One catalog list: rows, loading, failed (with retry). Nothing is asked while path is null. */
function useCatalogList(path: string | null, key: string, read: (row: Record<string, unknown>) => TrimOption | null) {
  const [rows, setRows] = useState<TrimOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [retry, setRetry] = useState(0);

  useEffect(() => {
    setRows([]);
    setFailed(false);
    setLoading(false);
    if (!path) return;
    const controller = new AbortController();
    setLoading(true);
    customFetch(`${import.meta.env.VITE_API_URL}${path}`, {
      headers: { Authorization: `Bearer ${readAccessToken()}` },
      signal: controller.signal,
    }).then(async response => {
      if (!response.ok) throw new Error(`${key} unavailable`);
      const body = record(await response.json());
      const list = body?.[key];
      if (body?.success !== true || !Array.isArray(list)) throw new Error(`Invalid ${key} response`);
      const options = list.flatMap(value => {
        const row = record(value);
        const option = row ? read(row) : null;
        return option ? [option] : [];
      });
      if (!controller.signal.aborted) setRows(options);
    }).catch(() => {
      if (!controller.signal.aborted) setFailed(true);
    }).finally(() => {
      if (!controller.signal.aborted) setLoading(false);
    });
    return () => controller.abort();
  }, [path, key, read, retry]);

  return { rows, loading, failed, retry: () => setRetry(value => value + 1) };
}

const text = (value: unknown) => (typeof value === "string" ? value : "");

function readGeneration(row: Record<string, unknown>): TrimOption | null {
  if (typeof row.id !== "number" || !text(row.name)) return null;
  const start = typeof row.year_start === "number" ? row.year_start : null;
  const end = typeof row.year_end === "number" ? row.year_end : null;
  const years = start ? `${start}–${end ?? ""}` : "";
  const photo = text(row.photo).startsWith("https://") || text(row.photo).startsWith("/") ? text(row.photo) : null;
  return { id: row.id, name: text(row.name), detail: years, photo };
}

function readModification(row: Record<string, unknown>): TrimOption | null {
  if (typeof row.id !== "number" || !text(row.name)) return null;
  const power = typeof row.power_hp === "number" ? `${row.power_hp} hp` : "";
  const detail = [text(row.fuel), power, text(row.transmission), text(row.drive)].filter(Boolean).join(" · ");
  return { id: row.id, name: text(row.name), detail, photo: null };
}

/**
 * Generations of a geoapi.ge model built in the year, each with its photo. A model from our own list (the
 * catalog was down when the brands loaded) has none, so nothing is asked.
 */
export function useCarGenerations(brand: Brand | null, model: Model | null, year: string) {
  const remote = model?.source === "carapi" && brand && year;
  const path = remote ? `/cars/models/${model.id}/generations?${new URLSearchParams({ brand_id: String(brand.id), year })}` : null;
  return useCatalogList(path, "generations", readGeneration);
}

/** Modifications (configurations) of the picked generation built in the year. */
export function useCarModifications(model: Model | null, generationId: number | null, year: string) {
  const path = model?.source === "carapi" && generationId && year
    ? `/cars/models/${model.id}/generations/${generationId}/modifications?${new URLSearchParams({ year })}` : null;
  return useCatalogList(path, "modifications", readModification);
}
