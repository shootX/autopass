import { customFetch } from "@/utils/customFetch";
import { setUser, setRole, type User, type UserRole } from "@/store/userSlice";


export async function fetchUserData(dispatch: any) {
  const token = localStorage.getItem("access_token");
  if (!token) return;

  try {
    const res = await customFetch(`${import.meta.env.VITE_API_URL}/me`, {
      method: "GET",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    });

    if (!res.ok) throw new Error("Failed to fetch /me");

    const data = await res.json();

    const parsed: User = {
      id: data.user.id,
      firstName: data.user.name || "",
      lastName: data.user.surname || "",
      sex: data.user.sex ?? null,
      dateOfBirth: data.user.date_of_birth ? String(data.user.date_of_birth) : null,
      phone: data.user.phone || "",
      email: data.user.email || "",
      emailVerified: !!data.user.email_verified_at,
      role: data.user.role,
      createdAt: String(data.user.created_at ?? ""),
      updatedAt: String(data.user.updated_at ?? ""),
    };

    dispatch(setUser(parsed));

    const roleMap: Record<number, UserRole> = {
      1: "manager",
      0: "customer",
    };

    const mappedRole = roleMap[data.user.role];
    if (mappedRole) dispatch(setRole(mappedRole));
  } catch (err) {
    console.error("Failed to fetch user:", err);
  }
}
