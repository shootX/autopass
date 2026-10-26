import type { Car } from "@/store/carSlice";

export function record(value: unknown): Record<string, unknown> | null {
  return value && typeof value === "object" && !Array.isArray(value)
    ? value as Record<string, unknown> : null;
}

export function readCar(value: unknown): Car | null {
  const body = record(value);
  const car = record(body?.car) ?? body;
  const id = Number(car?.id);
  if (!car || !Number.isInteger(id) || id < 1 || typeof car.plate !== "string") return null;
  const model = record(car.model);
  const brand = record(model?.brand);
  const year = Number(car.year);
  return {
    id, plate: car.plate,
    year: car.year != null && Number.isInteger(year) ? year : null,
    model: {
      id: typeof model?.id === "number" ? model.id : undefined,
      source: model?.source === "carapi" ? "carapi" : "local",
      external_model_id: typeof model?.external_model_id === "number" ? model.external_model_id : null,
      name: typeof model?.name === "string" ? model.name : typeof car.model === "string" ? car.model : "",
      type: typeof car.car_type === "string" && car.car_type ? car.car_type : typeof model?.type === "string" ? model.type : typeof car.type === "string" ? car.type : "",
      brand: {
        id: typeof brand?.id === "number" ? brand.id : undefined,
        source: brand?.source === "carapi" ? "carapi" : "local",
        external_brand_id: typeof brand?.external_brand_id === "number" ? brand.external_brand_id : null,
        name: typeof brand?.name === "string" ? brand.name : typeof car.brand === "string" ? car.brand : "",
      },
    },
  };
}

export function formatPlate(value: string): string {
  const raw = value.toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 7);
  return [raw.slice(0, 2), raw.slice(2, 5), raw.slice(5, 7)].filter(Boolean).join(" - ");
}

