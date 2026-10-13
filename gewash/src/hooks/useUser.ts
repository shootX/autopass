import { useEffect, useState } from "react";
import { useDispatch } from "react-redux";
import { customFetch } from "@/utils/customFetch";
import {
  rejectSession,
  resetSession,
  setRole,
  setSession,
  setUser,
  type UserRole,
  type User,
} from "@/store/userSlice";
import { clearSessionStorage, rememberSessionRole } from "@/lib/session";
import type { AppDispatch } from "@/store";

export class MeRequestError extends Error {
  status: number;

  constructor(status: number) {
    super(`Failed to fetch /me (${status})`);
    this.name = "MeRequestError";
    this.status = status;
  }
}

function mapRole(role: unknown): UserRole {
  const roleMap: Record<number, UserRole> = {
    1: "manager",
    0: "customer",
  };
  return roleMap[Number(role)] ?? "customer";
}

/**
 * Fetches GET /me and writes the role into Redux.
 * `establish` is the cold start / login path: failure blocks route selection.
 * Silent refresh (bfcache) keeps the current session on a transient error.
 */
export async function refreshCurrentUser(
  dispatch: AppDispatch,
  mode: "establish" | "silent" = "silent",
): Promise<UserRole | null> {
  const token = localStorage.getItem("access_token");
  if (!token) {
    if (mode === "establish") dispatch(resetSession());
    return null;
  }

  try {
    const res = await customFetch(`${import.meta.env.VITE_API_URL}/me`, {
      method: "GET",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    });

    if (res.status === 401) {
      clearSessionStorage();
      dispatch(rejectSession());
      throw new MeRequestError(401);
    }

    if (!res.ok) throw new MeRequestError(res.status);

    const data = (await res.json()) as { success: boolean; user: Record<string, unknown> & { id: number; role: number } };
    const raw = data.user;

    const parsed: User = {
      id: data.user.id,
      firstName: (data.user.name as string) || "",
      lastName: (data.user.surname as string) || "",
      sex: (data.user.sex as User["sex"]) ?? null,
      dateOfBirth: data.user.date_of_birth ? String(data.user.date_of_birth) : null,
      phone: (data.user.phone as string) || "",
      email: (data.user.email as string) || "",
      emailVerified: !!data.user.email_verified_at,
      role: Number(data.user.role),
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

    const role = mapRole(data.user.role);
    rememberSessionRole(role);
    dispatch(setUser(parsed));
    dispatch(setRole(role));
    dispatch(setSession("ready"));
    return role;
  } catch (err) {
    if (err instanceof MeRequestError && err.status === 401) throw err;
    if (mode === "establish") dispatch(setSession("error"));
    throw err;
  }
}

export function useUser() {
  const [error, setError] = useState<Error | null>(null);
  const dispatch = useDispatch<AppDispatch>();

  useEffect(() => {
    let cancelled = false;

    refreshCurrentUser(dispatch, "establish").catch((err) => {
      if (cancelled) return;
      console.error("Failed to fetch /me:", err);
      setError(err as Error);
    });

    return () => {
      cancelled = true;
    };
  }, [dispatch]);

  return { error };
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
