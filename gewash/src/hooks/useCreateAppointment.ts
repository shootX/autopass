import { useState } from "react";
import { useDispatch } from "react-redux";
import { addAppointment } from "@/store/appointmentsSlice";
import { customFetch } from "@/utils/customFetch";

type CreateAppointmentPayload = {
  car_wash_id: number;
  date: string; // формат "YYYY-MM-DD"
  time: string;
  branchName: string;
  branchAddress: string;
  type: string;
  service_id: number;
  car_id: number;
};

type CreateAppointmentResponse = {
  success: boolean;
  message: string;
  appointment: {
    id: number;
    user_id: number;
    car_wash_id: number;
    car_id: number;
    date: string;
    time: string;
    qr_code: string;
  };
};

// ✅ Безопасная нормализация даты без UTC-сдвига
export function useCreateAppointment() {
  const dispatch = useDispatch();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);  

  const createAppointment = async (payload: CreateAppointmentPayload) => {
    setLoading(true);
    setError(null);
    setSuccess(false);

    function normalizeDateWithOffset(dateStr: string): string {
      const [year, month, day] = dateStr.split("-").map(Number);
      const date = new Date(year, month - 1, day);
      date.setDate(date.getDate() + 1); // ← жёсткий +1
      return `${date.getFullYear()}-${(date.getMonth() + 1)
        .toString()
        .padStart(2, "0")}-${date.getDate().toString().padStart(2, "0")}`;
    }
    const formattedDate = normalizeDateWithOffset(payload.date);


    try {
      const token = localStorage.getItem("access_token");  
      const response = await customFetch(`${import.meta.env.VITE_API_URL}/appointments/add`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          car_wash_id: payload.car_wash_id,
          date: formattedDate,
          time: payload.time,
          service_id: payload.service_id,
          car_id: payload.car_id,
        }),
      });

      if (!response.ok) {
        throw new Error("Failed to create appointment");
      }

      const data: CreateAppointmentResponse = await response.json();

      dispatch(
        addAppointment({
          id: data.appointment.id,
          branchId: payload.car_wash_id,
          branchName: payload.branchName,
          branchAddress: payload.branchAddress,
          date: payload.date, 
          time: payload.time,
          type: payload.type,
        })
      );

      setSuccess(true);
      setError(null);
    } catch (err: any) {
      setError(err.message || "Unknown error");
    } finally {
      setLoading(false);
    }
  };

  return { createAppointment, loading, error, success };
}
