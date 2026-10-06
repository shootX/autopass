import { useState } from "react";
import { customFetch } from "@/utils/customFetch";

const API_URL = import.meta.env.VITE_API_URL;

type EditUserPayload = {
  name?: string;
  surname?: string;
  sex?: "male" | "female";
  date_of_birth?: string;
};

export function useEditUser() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<Error | null>(null);
  
    const editUser = async (payload: EditUserPayload): Promise<boolean> => {
      setLoading(true);
      setError(null);
  
      const token = localStorage.getItem("access_token");
      if (!token) {
        setError(new Error("No access token"));
        setLoading(false);
        return false;
      }
  
      try {
        const res = await customFetch(`${API_URL}/me/edit`, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        });
  
        if (!res.ok) {
          const errorText = await res.text();
          throw new Error(`Edit failed: ${errorText}`);
        }
  
        return true;
      } catch (err) {
        setError(err as Error);
        return false;
      } finally {
        setLoading(false);
      }
    };
  
    return { editUser, loading, error };
  }
  