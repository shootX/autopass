import { useEffect } from "react";
import { useDispatch } from "react-redux";
import { setAppointments } from "@/store/appointmentsSlice";
import { customFetch } from "@/utils/customFetch";
import { readAccessToken } from "@/lib/accessToken";

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) return null;
  return value as Record<string, unknown>;
}

function readText(value: unknown): string {
  return typeof value === "string" ? value : "";
}

function readId(value: unknown): number {
  const id = typeof value === "number" ? value : Number(value);
  return Number.isFinite(id) ? id : 0;
}

export function useLoadAppointmentsFromBackend() {
  const dispatch = useDispatch();

  useEffect(() => {
    const token = readAccessToken();

    customFetch(`${import.meta.env.VITE_API_URL}/myappointments`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((res) => res.json())
      .then((data: unknown) => {
        const body = asRecord(data);
        const list = Array.isArray(body?.appointments)
          ? body.appointments.filter(item => ![2, 3, 4].includes(Number(asRecord(item)?.approved))) : [];
        const mapped = list.map((item) => {
          const a = asRecord(item);
          const washing = asRecord(a?.washing);
          const carWash = asRecord(a?.car_wash);
          const services = Array.isArray(a?.services) ? a.services : [];
          const firstService = asRecord(services[0]);
          const serviceName = readText(firstService?.name);
          return {
            id: readId(a?.id),
            branchId: readId(a?.car_wash_id),
            branchName: readText(washing?.name) || readText(carWash?.name),
            branchAddress: readText(washing?.address) || readText(carWash?.address),
            date: readText(a?.date),
            time: readText(a?.time).slice(0, 5),
            type: serviceName || "Unknown",
            carId: readId(a?.car_id),
          };
        });

        dispatch(setAppointments(mapped));
      })
      .catch((err) => {
        console.error("Failed to load appointments", err);
      });
  }, [dispatch]);
}
