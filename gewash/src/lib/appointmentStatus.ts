export type AppointmentStatus = "New" | "Confirm" | "Deleted" | "Completed" | "Expired" | "Rescheduled";

export const appointmentCodes = { pending: 0, confirmed: 1, cancelled: 2, completed: 3, expired: 4, rescheduled: 5 } as const;

export function appointmentStatus(code: number): AppointmentStatus {
  const labels: Record<number, AppointmentStatus> = {
    0: "New", 1: "Confirm", 2: "Deleted", 3: "Completed", 4: "Expired", 5: "Rescheduled",
  };
  return labels[code] ?? "New";
}

