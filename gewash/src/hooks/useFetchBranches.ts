import { customFetch } from "@/utils/customFetch";
import { useEffect, useState } from "react";

export type Branch = {
  id: number;
  name: string;
  address: string;
  lat: number;
  lng: number;
  phone: string;
  manager: {
    id: number;
    name: string;
    surname: string;
    email: string;
    phone: string;
  };
  services: {
    id: number;
    name: string;
    pivot: {
      car_wash_id: number;
    };
  }[];
};

export function useFetchBranches() {
  const [branches, setBranches] = useState<Branch[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const token = localStorage.getItem("access_token");

    customFetch(`${import.meta.env.VITE_API_URL}/branches`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then(async (res) => {
        if (!res.ok) throw new Error("Failed to fetch branches");
        const data = await res.json();

        const parsed = data.branches.map((b: any) => {
          const [latStr, lngStr] = b.location.split(",");
          return {
            id: b.id,
            name: b.name,
            address: b.address,
            lat: parseFloat(latStr),
            lng: parseFloat(lngStr),
            phone: b.phone,
            isOpen: true,
            openTime: "09:00",
            closeTime: "18:00",
            manager: {
              id: b.manager.id,
              name: b.manager.name,
              surname: b.manager.surname,
              email: b.manager.email,
              phone: b.manager.phone,
            },
            services: b.services ?? [],
          };
        });        

        setBranches(parsed);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  return { branches, loading, error };
}
