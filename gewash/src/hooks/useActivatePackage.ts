import { customFetch } from "@/utils/customFetch";
import { useState } from "react";

export function useActivatePackage() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  type ActivationPayload = {
    car_id: number;
    number_of_washes: number;
    sub_term: string;
    renewal: boolean;
  };
  

  const activatePackage = async (
    payload: ActivationPayload
  ): Promise<{ success: boolean; url: string } | null> => {
    setLoading(true);
    setError(null);

    try {
      const token = localStorage.getItem("access_token");

      const response = await customFetch(
        `${import.meta.env.VITE_API_URL}/packages/buy`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        }
      );

      if (!response.ok) {
        throw new Error("Failed to buy package");
      }

      const data = await response.json();
      return data; // { success: true, url: "..." }
    } catch (err: any) {
      setError(err.message || "Unknown error");
      return null;
    } finally {
      setLoading(false);
    }
  };

  return { activatePackage, loading, error };
}
