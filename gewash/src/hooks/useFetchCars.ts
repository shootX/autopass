import { useEffect, useState } from "react";
import { customFetch } from "@/utils/customFetch";

type EnrichedCar = {
  id: number;
  plate: string;
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

function loadCars(): Promise<EnrichedCar[]> {
  if (carsCache) return Promise.resolve(carsCache);
  if (carsInflight) return carsInflight;

  const token = localStorage.getItem("access_token");
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
    .then((data) => {
      const enriched: EnrichedCar[] = data.cars.map((car: any) => ({
        id: car.id,
        plate: car.plate,
        type: car.model?.type ?? "Unknown",
        model: car.model?.name ?? "Unknown",
        brand: car.model?.brand?.name ?? "Unknown",
        image: car.image ?? null,
      }));
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
