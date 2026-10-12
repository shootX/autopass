import type { AppDispatch } from "@/store";
import { refreshCurrentUser } from "@/hooks/useUser";
import { setSession, type UserRole } from "@/store/userSlice";

/** Loads GET /me before the caller navigates. Returns null when the role is still unknown. */
export async function fetchUserData(dispatch: AppDispatch): Promise<UserRole | null> {
  dispatch(setSession("pending"));
  try {
    return await refreshCurrentUser(dispatch, "establish");
  } catch (err) {
    console.error("Failed to fetch user:", err);
    return null;
  }
}
