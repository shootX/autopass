import { useEffect, useState } from "react";
import { useDispatch } from "react-redux";
import { customFetch } from "@/utils/customFetch";
import { setRole, setUser, type UserRole, type User } from "@/store/userSlice";
import type { AppDispatch } from "@/store";

/** Fetches /me and updates Redux. Safe to call after payment or when restoring from bfcache. */
export async function refreshCurrentUser(dispatch: AppDispatch): Promise<void> {
  const token = localStorage.getItem("access_token");
  if (!token) return;

  const res = await customFetch(`${import.meta.env.VITE_API_URL}/me`, {
    method: "GET",
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
    },
  });

  if (!res.ok) throw new Error("Failed to fetch /me");

  const data = (await res.json()) as { success: boolean; user: Record<string, unknown> & { id: number; role: number } };

  const raw = data.user as Record<string, unknown>;
  const parsed: User = {
    id: data.user.id,
    firstName: (data.user.name as string) || "",
    lastName: (data.user.surname as string) || "",
    sex: (data.user.sex as User["sex"]) ?? null,
    dateOfBirth: data.user.date_of_birth ? String(data.user.date_of_birth) : null,
    phone: (data.user.phone as string) || "",
    email: (data.user.email as string) || "",
    emailVerified: !!data.user.email_verified_at,
    role: data.user.role,
    createdAt: String(data.user.created_at ?? ""),
    updatedAt: String(data.user.updated_at ?? ""),
    enablePushWashAppointment: Boolean(data.user.enable_push_wash_appointment),
    enablePushRenewalSubscription: Boolean(data.user.enable_push_renewal_subscription),
    enablePushSpecialPromotions: Boolean(data.user.enable_push_special_promotions),
    points: typeof raw.points === "number" ? raw.points : Number(raw.points) || 0,
    referralsCount:
      typeof raw.referrals_count === "number"
        ? raw.referrals_count
        : Number(raw.referrals_count) || 0,
  };

  dispatch(setUser(parsed));

  const roleMap: Record<number, UserRole> = {
    1: "manager",
    0: "customer",
  };

  const mappedRole = roleMap[data.user.role];
  if (mappedRole) {
    dispatch(setRole(mappedRole));
  } else {
    console.warn("Unknown role:", data.user.role);
  }
}

export function useUser() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  const dispatch = useDispatch<AppDispatch>();

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    if (!token) {
      setError(new Error("No access token"));
      setLoading(false);
      return;
    }

    const fetchUser = async () => {
      try {
        await refreshCurrentUser(dispatch);
      } catch (err) {
        setError(err as Error);
      } finally {
        setLoading(false);
      }
    };

    void fetchUser();
  }, [dispatch]);

  return { loading, error };
}

/** After back/forward from payment (bfcache), /me is not refetched by default — refresh user once. */
export function useRefreshUserOnBfcacheRestore() {
  const dispatch = useDispatch<AppDispatch>();

  useEffect(() => {
    const onPageShow = (e: PageTransitionEvent) => {
      if (!e.persisted) return;
      void refreshCurrentUser(dispatch).catch(() => {});
    };
    window.addEventListener("pageshow", onPageShow);
    return () => window.removeEventListener("pageshow", onPageShow);
  }, [dispatch]);
}
