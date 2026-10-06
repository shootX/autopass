import React, { useState } from "react";
import DeleteReasonPopup from "../../DeleteReasonPopup";
import ManagerOrdersList from "./ManagerOrdersList";
import { useManagerActions } from "@/hooks/useManagerActions";
import { useAllRecords } from "@/hooks/useAllRecords";
import { useTranslation } from "@/hooks/useTranslation";

type Filter = "New" | "Confirmed" | "Sheduled" | "All";
const filters: Filter[] = ["New", "Confirmed", "Sheduled", "All"];

export default function Booking() {
  const [activeStatus, setActiveStatus] = useState<Filter>("New");
  const [refreshFlag, setRefreshFlag] = useState(0);
  const { appointments, refetch } = useAllRecords();
  const t = useTranslation();
  const managerActions = useManagerActions({
    appointments,
    refetch: () => {
      refetch();
      setRefreshFlag((prev) => prev + 1); 
    },
  });
  const {
    popupVisible,
    setPopupVisible,
    deleteReason,
    setDeleteReason,
    handleDelete,
    setSelectedOrder,
    confirmDelete,
    handleConfirm,
    handleReschedule,
  } = managerActions;

  return (
    <div>
      <header style={{ textAlign: "center", justifyContent: "center" }}>
        {t("Booking.header")}
      </header>

      <div className='reservation-staus-bar'>
        <ul>
          {filters.map((label) => (
            <li
              key={label}
              onClick={() => setActiveStatus(label)}
              className={`status-bar-item ${activeStatus === label ? "active" : ""}`}
            >
              {t(`Booking.filters.${label}`)}
            </li>
          ))}
        </ul>
      </div>

      <ManagerOrdersList
        activeStatus={activeStatus}
        appointments={appointments}
        refetch={refetch}
        onDelete={handleDelete}
        onReschedule={handleReschedule}
        onConfirm={handleConfirm}
      />
      <DeleteReasonPopup
        visible={popupVisible}
        reason={deleteReason}
        setReason={setDeleteReason}
        onConfirm={confirmDelete}
        onCancel={() => {
          setDeleteReason("");
          setSelectedOrder(null);
          setPopupVisible(false);
        }}
      />
    </div>
  );
}
