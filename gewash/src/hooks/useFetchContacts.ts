import { customFetch } from "@/utils/customFetch";
import { useEffect, useState } from "react";

export function useFetchContacts() {
  const [contacts, setContacts] = useState<{
    address: string;
    email: string;
    phone: string;
    website: string;
    working_hours: string;
  } | null>(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const fetchContacts = async () => {
        const token = localStorage.getItem("access_token");
      
        try {
          const res = await customFetch(`${import.meta.env.VITE_NO_API_URL}/api/contacts`, {
            method: "GET",
            headers: {
              Authorization: `Bearer ${token}`,
              "Content-Type": "application/json",
              Accept: "application/json",
            },
          });
      
          if (!res.ok) throw new Error("Failed to fetch contacts");
      
          const data = await res.json();
          setContacts(data.contacts);
        } catch (err) {
          setError("Error loading contacts");
          console.error(err);
        } finally {
          setLoading(false);
        }
      };
    fetchContacts();
  }, []);

  return { contacts, loading, error };
}
