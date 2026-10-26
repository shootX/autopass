import { useEffect, useState } from "react";
import { customFetch } from "@/utils/customFetch";
import { readAccessToken } from "@/lib/accessToken";

type EnrichedCar = {
  id: number;
  plate: string;
  year: number | null;
  type: string;
  model: string;
  brand: string;
  image: string | null;
};

let carsCache: EnrichedCar[] | null = null;
let carsInflight: Promise<EnrichedCar[]> | null = null;

export function invalidateCarsCache() {
  carsCache = null;
  carsInflight = null;
}

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) return null;
  return value as Record<string, unknown>;
}

function readString(value: unknown, fallback: string): string {
  return typeof value === "string" && value.trim() ? value : fallback;
}

function loadCars(): Promise<EnrichedCar[]> {
  if (carsCache) return Promise.resolve(carsCache);
  if (carsInflight) return carsInflight;

  const token = readAccessToken();
  carsInflight = customFetch(`${import.meta.env.VITE_API_URL}/mycars`, {
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
  })
    .then((res) => {
      if (!res.ok) throw new Error("Failed to fetch cars");
      return res.json();
    })
    .then((data: unknown) => {
      const body = asRecord(data);
      const list = Array.isArray(body?.cars) ? body.cars : [];
      const enriched: EnrichedCar[] = list.map((item) => {
        const car = asRecord(item);
        const model = asRecord(car?.model);
        const brand = asRecord(model?.brand);
        const id = typeof car?.id === "number" ? car.id : Number(car?.id);
        return {
          id: Number.isFinite(id) ? id : 0,
          plate: readString(car?.plate, ""),
          year: typeof car?.year === "number" ? car.year : null,
          // car_type: the car's own body type from the catalog; the model's type is the fallback.
          type: readString(car?.car_type, readString(model?.type, "Unknown")),
          model: readString(model?.name, "Unknown"),
          brand: readString(brand?.name, "Unknown"),
          image: typeof car?.image === "string" ? car.image : null,
        };
      });
      carsCache = enriched;
      return enriched;
    })
    .finally(() => {
      carsInflight = null;
    });

  return carsInflight;
}

export function useFetchCars() {
  const [cars, setCars] = useState<EnrichedCar[]>(carsCache ?? []);
  const [loading, setLoading] = useState(carsCache === null);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;
    loadCars()
      .then((enriched) => {
        if (!cancelled) setCars(enriched);
      })
      .catch((err) => {
        if (cancelled) return;
        setError("Error loading vehicles");
        console.error(err);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return { cars, loading, error };
}
