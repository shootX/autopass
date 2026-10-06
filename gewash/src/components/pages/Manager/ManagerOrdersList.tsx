import { useTranslation } from "@/hooks/useTranslation";
import ManagerOrder from "../../ManagerOrder";
import type { Appointment } from "@/hooks/useAllRecords";

type Status = "New" | "Confirm" | "Deleted" | "Rescheduled" | "Expired";
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

  function getRawStatus(code: number): Status {
    switch (code) {
      case 0: return "New";
      case 1: return "Confirm";
      case 2: return "Deleted";
      case 3: return "Rescheduled";
      case 4: return "Expired";
      default: return "New";
    }
  }

  function getLocalizedStatus(code: number): string {
    const raw = getRawStatus(code);
    return t(`ManagerOrdersList.status.${raw}`);
  }

  const filteredAppointments = appointments.filter((a) => {
    const rawStatus = getRawStatus(a.approved);
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
          status={getRawStatus(a.approved)}
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
