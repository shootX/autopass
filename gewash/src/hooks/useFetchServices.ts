import { customFetch } from "@/utils/customFetch";
import { useEffect, useState } from "react";

export type Service = {
  id: number;
  name: string;
};

export function useFetchServices() {
  const [services, setServices] = useState<Service[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  

  useEffect(() => {
    const token = localStorage.getItem("access_token");

    customFetch(`${import.meta.env.VITE_API_URL}/services-list`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success && Array.isArray(data.services)) {
          setServices(data.services);
        } else {
          throw new Error("Invalid response format");
        }
      })
      .catch((err) => {
        setError(err.message || "Failed to fetch services");
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  return { services, loading, error };
}
