import { useEffect } from "react";
import { useDispatch } from "react-redux";
import { setAppointments } from "@/store/appointmentsSlice";
import { customFetch } from "@/utils/customFetch";

export function useLoadAppointmentsFromBackend() {
  const dispatch = useDispatch();

  useEffect(() => {
    const token = localStorage.getItem("access_token");

    customFetch(`${import.meta.env.VITE_API_URL}/myappointments`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
      .then((res) => res.json())
      .then((data) => {
        const mapped = data.appointments.map((a: any) => ({
          id: a.id,
          branchId: a.car_wash_id,
          branchName: "Test Branch",
          branchAddress: "123 Main St",
          date: a.date,
          time: a.time.slice(0, 5), 
          type: a.services?.[0]?.name || "Unknown",
          approved: a.approved,
          carId: a.car_id,
        }));

        dispatch(setAppointments(mapped));
      })
      .catch((err) => {
        console.error("Failed to load appointments", err);
      });
  }, []);
}
