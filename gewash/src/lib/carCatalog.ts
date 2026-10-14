export const CATALOG_SOURCES = ["carapi", "local"] as const;

export type CatalogSource = (typeof CATALOG_SOURCES)[number];

export type CatalogItem = {
  id: number;
  name: string;
  source: CatalogSource;
};

export function catalogKey(item: Pick<CatalogItem, "id" | "source">): string {
  return `${item.source}:${item.id}`;
}

function positiveInt(value: unknown): number | null {
  return typeof value === "number" && Number.isInteger(value) && value > 0 ? value : null;
}

export function parseCatalogItem(value: unknown): CatalogItem | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) return null;
  const row = value as Record<string, unknown>;
  const id = positiveInt(row.id);
  const name = typeof row.name === "string" ? row.name.trim() : "";
  const source = row.source;
  if (id === null || !name) return null;
  if (source !== "carapi" && source !== "local") return null;
  return { id, name, source };
}

export function parseCatalogList(value: unknown): CatalogItem[] {
  if (!Array.isArray(value)) return [];
  return value.flatMap((item) => {
    const parsed = parseCatalogItem(item);
    return parsed ? [parsed] : [];
  });
}

export function readCreatedCarId(body: unknown): number {
  if (!body || typeof body !== "object") return 0;
  const record = body as Record<string, unknown>;
  const nested = record.car && typeof record.car === "object"
    ? (record.car as Record<string, unknown>)
    : record;
  const rawId = nested.id ?? record.id;
  if (typeof rawId === "number" && Number.isFinite(rawId)) return rawId;
  if (typeof rawId === "string" && rawId.trim() && Number.isFinite(Number(rawId))) return Number(rawId);
  return 0;
}

export function parseYears(value: unknown): number[] {
  if (!value || typeof value !== "object" || Array.isArray(value)) return [];
  const years = (value as { years?: unknown }).years;
  if (!Array.isArray(years)) return [];
  return years.filter((year): year is number => typeof year === "number" && Number.isInteger(year));
}

export type MyCarAddBody = {
  plate: string;
  source: CatalogSource;
  model_id: number;
  brand_id: number;
  year?: number;
};

/** Catalog ids are only sent with the source they were loaded under. */
export function myCarAddBody(input: {
  plate: string;
  brand: CatalogItem;
  model: CatalogItem;
  year?: number | null;
}): MyCarAddBody | null {
  if (input.brand.source !== input.model.source) return null;
  const plate = input.plate.trim();
  if (!plate) return null;
  const body: MyCarAddBody = {
    plate,
    source: input.model.source,
    model_id: input.model.id,
    brand_id: input.brand.id,
  };
  if (typeof input.year === "number" && Number.isInteger(input.year)) body.year = input.year;
  return body;
}

export type SavedModelRef = {
  id: number;
  source?: string | null;
  external_model_id?: number | null;
  brand?: {
    id?: number;
    source?: string | null;
    external_brand_id?: number | null;
  } | null;
};

export type CatalogWriteIds =
  | { source: "local"; model_id: number; brand_id?: number }
  | { source: "carapi"; model_id: number; brand_id: number };

/**
 * A saved car's model.id is always the local row. CarAPI ids live on external_* fields.
 * Sending model.id with source=carapi would attach the wrong model.
 */
export function catalogWriteIds(model: SavedModelRef): CatalogWriteIds | null {
  if (model.source === "local") {
    const modelId = positiveInt(model.id);
    if (modelId === null) return null;
    const brandId = positiveInt(model.brand?.id);
    return brandId === null
      ? { source: "local", model_id: modelId }
      : { source: "local", model_id: modelId, brand_id: brandId };
  }
  if (model.source !== "carapi") return null;
  const modelId = positiveInt(model.external_model_id);
  const brandId = positiveInt(model.brand?.external_brand_id);
  if (modelId === null || brandId === null) return null;
  return { source: "carapi", model_id: modelId, brand_id: brandId };
}

export type MyCarEditBody = {
  plate: string;
  year?: number | null;
  source?: CatalogSource;
  model_id?: number;
  brand_id?: number;
};

export function myCarEditBody(input: {
  plate: string;
  year?: number | null;
  includeYear: boolean;
  model?: SavedModelRef | null;
}): MyCarEditBody | null {
  const plate = input.plate.trim();
  if (!plate) return null;
  const body: MyCarEditBody = { plate };
  if (input.includeYear) body.year = input.year ?? null;
  if (!input.model) return body;
  const ids = catalogWriteIds(input.model);
  if (!ids) return null;
  body.source = ids.source;
  body.model_id = ids.model_id;
  if (ids.brand_id != null) body.brand_id = ids.brand_id;
  return body;
}
