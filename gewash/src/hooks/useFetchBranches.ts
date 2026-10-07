import { customFetch } from "@/utils/customFetch";
import { parseBranch } from "@/hooks/fetchFilteredBranches";
import { useEffect, useState } from "react";

export type Branch = {
  id: number;
  name: string;
  address: string;
  lat: number;
  lng: number;
  phone: string;
  workStart?: string | null;
  workEnd?: string | null;
  isOpen?: boolean | null;
  manager: {
    id: number;
    name: string;
    surname: string;
    email: string;
    phone: string;
  } | null;
  services: {
    id: number;
    name: string;
    pivot?: {
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

        setBranches((data.branches ?? []).map(parseBranch));
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  return { branches, loading, error };
}
