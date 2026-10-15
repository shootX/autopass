import { customFetch } from "@/utils/customFetch";
import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"
import { readAccessToken } from "@/lib/accessToken";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export async function updateAppointmentStatus(
  id: number,
  status: number,
  date?: string,
  time?: string
) {
  const token = readAccessToken();

  const body: { status: number; date?: string; time?: string } = { status };
  if (date) body.date = date;
  if (time) body.time = time;

  const res = await customFetch(
    `${import.meta.env.VITE_API_URL}/appointments/${id}/edit`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify(body),
    }
  );

  if (!res.ok) throw new Error("Failed to update status");
  return await res.json();
}
