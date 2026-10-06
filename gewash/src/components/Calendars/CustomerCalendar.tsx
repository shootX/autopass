import React, { useState } from "react";
import {
  addMonths,
  subMonths,
  format,
  startOfMonth,
  endOfMonth,
  startOfWeek,
  endOfWeek,
  eachDayOfInterval,
  isSameDay,
  addDays,
  parseISO,
} from "date-fns";
import { useDispatch, useSelector } from "react-redux";
import { type RootState } from "@/store";
import "../../styles/customer_styles/customer-calendar.scss";
import Header from "../Header";
import { removeAppointment } from "@/store/appointmentsSlice";
import { useLoadAppointmentsFromBackend } from "@/hooks/useLoadAppointmentsFromBackend";
import { customFetch } from "@/utils/customFetch";
import { useFetchBranches, type Branch } from "@/hooks/useFetchBranches";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "@/hooks/useTranslation";
import { ru, ka, enUS } from "date-fns/locale";

import {
  trashIconUrl,
  managerCallIconUrl,
  pathIconUrl,
  qrIconYellowUrl,
} from "@/assets/staticUrls";

type Appointment = {
  id: number;
  branchId: string;
  branchName: string;
  branchAddress: string;
  date: string;
  time: string;
  type: string;
};

export default function WashAppointmentsCalendar() {
  const t = useTranslation();
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [popupVisible, setPopupVisible] = useState(false);
  const [selectedAppointment, setSelectedAppointment] = useState<Appointment | null>(null);
  const { branches } = useFetchBranches();
  const navigate = useNavigate();
  const dispatch = useDispatch();
  useLoadAppointmentsFromBackend();

  const currentLang = useSelector((s: RootState) => s.lang.currentLang);

  const branchMap = new Map<number, Branch>();
  branches.forEach((b) => branchMap.set(b.id, b));

  const appointments = useSelector((state: RootState) => state.appointments.appointments);

  const monthStart = startOfMonth(currentMonth);
  const monthEnd = endOfMonth(currentMonth);
  const startDate = startOfWeek(monthStart, { weekStartsOn: 0 });
  const endDate = endOfWeek(monthEnd, { weekStartsOn: 0 });
  const days = eachDayOfInterval({ start: startDate, end: endDate });

  const appointmentsByDay = new Map<string, number>();
  appointments.forEach((a) => {
    const date = parseISO(a.date);
    const key = date.toDateString();
    appointmentsByDay.set(key, (appointmentsByDay.get(key) || 0) + 1);
  });

  const selectedAppointments = appointments.filter((a) =>
    isSameDay(parseISO(a.date), selectedDate)
  );

  const handleDeleteClick = (appointment: Appointment) => {
    setSelectedAppointment(appointment);
    setPopupVisible(true);
  };

  const handleConfirmDelete = async () => {
    if (!selectedAppointment) return;

    try {
      const token = localStorage.getItem("access_token");
      const response = await customFetch(
        `${import.meta.env.VITE_API_URL}/appointments/${selectedAppointment.id}/remove`,
        {
          method: "DELETE",
          headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
          },
        }
      );

      if (!response.ok) throw new Error("Failed to delete appointment");

      dispatch(removeAppointment({ date: selectedAppointment.date, time: selectedAppointment.time }));
    } catch (err) {
      console.error("Error deleting appointment:", err);
    } finally {
      setPopupVisible(false);
      setSelectedAppointment(null);
    }
  };

  const handleCancelDelete = () => {
    setPopupVisible(false);
    setSelectedAppointment(null);
  };

  const locales = {
    ru,
    ka,
    en: enUS,
  };

  const locale = locales[currentLang] ?? enUS;
  const weekStart = startOfWeek(new Date(), { locale });

  const handleRouteClick = async (selectedBranch: any) => {
    if (!selectedBranch) return;

    window.ReactNativeWebView?.postMessage(JSON.stringify({
      type: 'navigate',
      lat: selectedBranch.lat,
      lng: selectedBranch.lng
    }));

    return;
  };

  return (
    <div>
      <Header logoVariant="calendar" title={t("ManagerCalendarGrid.header")} />

      <div className="wash-calendar">
        <div className="calendar-header">
          <button onClick={() => setCurrentMonth(subMonths(currentMonth, 1))}>&lt;</button>
          <span>{format(currentMonth, "MMM yyyy", { locale: locales[currentLang] ?? enUS })}</span>
          <button onClick={() => setCurrentMonth(addMonths(currentMonth, 1))}>&gt;</button>
        </div>

        <div className="calendar-grid-wrapper">
          <div className="calendar-daynames">
            {/* {["SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT"].map((day) => (
              <div key={day} className="calendar-dayname">{day}</div>
            ))} */}
            {Array.from({ length: 7 }).map((_, index) => (
              <div key={index} className="calendar-dayname">
                {format(addDays(weekStart, index), "EEE", { locale }).toUpperCase()}
              </div>
            ))}
          </div>

          <div className="calendar-grid">
            {days.map((day) => {
              const isSelected = isSameDay(day, selectedDate);
              const key = day.toDateString();
              const hasAppointments = appointmentsByDay.has(key);

              return (
                <div
                  key={day.toISOString()}
                  className={`calendar-cell${isSelected ? " selected" : ""}`}
                  onClick={() => setSelectedDate(day)}
                >
                  <div>{format(day, "d")}</div>
                  {hasAppointments && <div className="dot" />}
                </div>
              );
            })}
          </div>
        </div>

        <div className="appointments-panel">
          {selectedAppointments.length === 0 ? (
            <p className="no-events">{t("WashAppointmentsCalendar.noAppointments")}</p>
          ) : (
            selectedAppointments.map((a, i) => {
              const branch = branchMap.get(Number(a.branchId));
              return (
                <div key={i} className="appointment-card">
                  <p className="appointment-date-time">
                    {format(parseISO(a.date), "dd MMMM yyyy")} · {a.time}
                  </p>
                  {branch ? (
                    <>
                      <p className="appointment-branch">{branch.name}</p>
                      <p className="appointment-address">{branch.address}</p>
                    </>
                  ) : (
                    <p className="appointment-branch">{t("WashAppointmentsCalendar.branchNotFound")}</p>
                  )}
                  <div className="functional-block-caledar">
                    <button onClick={() => handleDeleteClick(a)}>
                      <img src={trashIconUrl} alt="delete" />
                    </button>
                    {branch?.manager?.phone && (
                      <a href={`tel:+${branch.manager.phone}`}>
                        <button>
                          <img src={managerCallIconUrl} alt="call" />
                        </button>
                      </a>
                    )}

                    <button>
                      <img src={pathIconUrl} alt="map" onClick={() => handleRouteClick(branch)} />
                    </button>
                    <button onClick={() => navigate("/customer-qr-page")}>
                      <img src={qrIconYellowUrl} alt="qr" />
                    </button>
                  </div>
                </div>
              );
            })
          )}
        </div>
      </div>

      {popupVisible && (
        <div className="delete-popup-backdrop">
          <div className="delete-popup">
            <h3>{t("CustomerCalendar.cancel_appointment")}</h3>
            <p>{t("CustomerCalendar.cancel_warning")}</p>
            <div className="popup-actions">
              <button onClick={handleCancelDelete}>{t("CustomerCalendar.cancel")}</button>
              <button onClick={handleConfirmDelete}>{t("CustomerCalendar.yes")}</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
