import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { updateAppointmentStatus } from "@/lib/utils";
import type { Appointment } from "./useAllRecords";

export function useManagerActions({
    appointments,
    refetch,
  }: {
    appointments: Appointment[];
    refetch: () => void;
  }) {
  const [popupVisible, setPopupVisible] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState<any | null>(null);
  const [deleteReason, setDeleteReason] = useState("");
  const navigate = useNavigate();
  const handleDelete = (order: any) => {
    setSelectedOrder(order);
    setPopupVisible(true);
  };

  const confirmDelete = async () => {
    if (selectedOrder) {
      try {
        await updateAppointmentStatus(selectedOrder.id, 2);
        refetch();
        console.log("Deleted:", selectedOrder.id);
      } catch (err) {
        console.error("Delete failed:", err);
      }
    }
    setDeleteReason("");
    setPopupVisible(false);
    setSelectedOrder(null);
  };

  const handleConfirm = async (id: number) => {
    try {
      await updateAppointmentStatus(id, 1);
      refetch();
      console.log("Confirmed:", id);
    } catch (err) {
      console.error("Confirm failed:", err);
    }
  };

  const handleReschedule = (id: number) => {
    const order = appointments.find((a) => a.id === id);

    if (!order) {
      console.warn("Order not found for reschedule:", id);
      return;
    }

    navigate(`/reschedule/${id}`, {
      state: {
        orderId: order.id,
        selectedDate: `${order.date} · ${order.time}`,
        customerName: `${order.user.name} ${order.user.surname}`,
        customerPhone: order.user.phone,
        serviceType: order.services?.[0]?.name ?? "—",
      },
    });
  };

  return {
    popupVisible,
    setPopupVisible,
    selectedOrder,
    setSelectedOrder,
    deleteReason,
    setDeleteReason,
    handleDelete,
    confirmDelete,
    handleConfirm,
    handleReschedule,
  };
}
