// hooks/useAllRecords.ts
import { useEffect, useState } from "react";
import { customFetch } from "@/utils/customFetch";

export type Status = "New" | "Confirm" | "Deleted" | "Rescheduled" | "Expired";

export interface Appointment {
  id: number;
  date: string;
  time: string;
  approved: number;
  qr_code: string;
  user: {
    name: string;
    surname: string;
    phone: string;
  };
  car: {
    plate: string;
  };
  services: {
    id: number;
    name: string;
  }[];
}

export function useAllRecords() {
  const [appointments, setAppointments] = useState<Appointment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const [refreshFlag, setRefreshFlag] = useState(0); // 🔁 триггер

  const fetchRecords = async () => {
    try {
      const token = localStorage.getItem("access_token");
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/allrecords`, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      if (!res.ok) throw new Error("Failed to fetch records");

      const data = await res.json();
      setAppointments(data.appointments ?? []);
    } catch (err) {
      setError(err as Error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRecords();
  }, [refreshFlag]); 

  const refetch = () => setRefreshFlag((prev) => prev + 1);

  return { appointments, loading, error, refetch };
}

