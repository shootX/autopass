import { appointmentStatus } from "@/lib/appointmentStatus";
import { useTranslation } from "@/hooks/useTranslation";
import ManagerOrder from "../../ManagerOrder";
import type { Appointment } from "@/hooks/useAllRecords";

type Filter = "New" | "Confirmed" | "Sheduled" | "All";

interface ManagerOrdersListProps {
  activeStatus: Filter;
  onDelete: (order: Appointment) => void;
  onReschedule: (id: number, date: string) => void;
  onConfirm: (id: number) => void;
  appointments: Appointment[];
  refetch: () => void;
}

export default function ManagerOrdersList({
  activeStatus,
  onDelete,
  onReschedule,
  onConfirm,
  appointments,
}: ManagerOrdersListProps) {
  const t = useTranslation();



  function getLocalizedStatus(code: number): string {
    const raw = appointmentStatus(code);
    return t(`ManagerOrdersList.status.${raw}`);
  }

  const filteredAppointments = appointments.filter((a) => {
    const rawStatus = appointmentStatus(a.approved);
    if (activeStatus === "All") return true;
    if (activeStatus === "Sheduled") return rawStatus === "Rescheduled";
    if (activeStatus === "Confirmed") return rawStatus === "Confirm";
    return rawStatus === activeStatus;
  });

  if (filteredAppointments.length === 0) {
    return <div className="empty-message">{t("ManagerOrdersList.emptyMessage")}</div>;
  }

  return (
    <div>
      {filteredAppointments.map((a) => (
        <ManagerOrder
          key={a.id}
          status={appointmentStatus(a.approved)}
          localizedStatus={getLocalizedStatus(a.approved)}
          date={`${a.date} · ${a.time.slice(0, 5)}`}
          type={a.services.map((s) => s.name).join(", ")}
          customer={{
            name: `${a.user.name} ${a.user.surname}`,
            phone: a.user.phone,
          }}
          onDelete={() => onDelete(a)}
          onReschedule={() => onReschedule(a.id, a.date)}
          onConfirmed={() => onConfirm(a.id)}
        />
      ))}
    </div>
  );
}
