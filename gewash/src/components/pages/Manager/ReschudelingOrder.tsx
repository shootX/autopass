import React, { useEffect, useState } from "react"
import { useLocation, useNavigate } from "react-router-dom"
import { format } from "date-fns"
import { SingleCalendarMobileSheet } from "../../Calendars/SingleCalendarDropDownSheet"
import { TimePickerMobileSheet } from "../../ui/TimePickerMobileSheet"
import { updateAppointmentStatus } from "@/lib/utils"
import { useTranslation } from "@/hooks/useTranslation"
import { leftArrowUrl, managerVectorIconUrl } from "@/assets/staticUrls"


export default function ReschudelingOrder() {
  const t = useTranslation();
  const location = useLocation()
  const navigate = useNavigate()
  const [datePart, setDatePart] = useState("")
  const [pickedTime, setPickedTime] = useState("")
  const [selectedDateObj, setSelectedDateObj] = useState<Date | null>(null)
  const [calendarOpen, setCalendarOpen] = useState(false)
  const [timePickerOpen, setTimePickerOpen] = useState(false)
  const [isSaving, setIsSaving] = useState(false);

  const isSaveEnabled = selectedDateObj !== null && pickedTime !== "";
  const {
    orderId,
    selectedDate,
    customerName,
    customerPhone,
    serviceType,
    refetch,
  } = location.state || {};
  

  useEffect(() => {
    if (selectedDate?.includes(" · ")) {
      const [dateStr] = selectedDate.split(" · ")
      const parsed = new Date(dateStr.trim())
      if (!isNaN(parsed.getTime())) {
        setDatePart(format(parsed, "d MMMM"))
        setSelectedDateObj(parsed)
      }
    }
  }, [selectedDate])

  const handleCalendarOpen = () => setCalendarOpen(true)
  const handleSave = async () => {
    if (!orderId || !selectedDateObj || !pickedTime) return;
  
    setIsSaving(true);
  
    const formattedDate = format(selectedDateObj, "yyyy-MM-dd"); 
    const formattedTime = pickedTime;
  
    try {
      await updateAppointmentStatus(orderId, 3, formattedDate, formattedTime);
      navigate("/"); 
      refetch();
      window.location.reload();
    } catch (err) {
      console.error("Reschedule failed:", err);
    } finally {
      setIsSaving(false);
    }
  };
  

  return (
    <div>
      <header className='rescheduling-header'>
        <button className='rescheduling-back-btn' onClick={() => navigate(-1)}>
          <img src={leftArrowUrl} alt='Back' />
        </button>
        <h2 className='rescheduling-title'>{t("ReschudelingOrder.header.title")}</h2>
      </header>
      <div className='rescheduling-order-container'>
        <div className='rescheduling-customer-info'>
          <div>
            <p className='rescheduling-customer-info-name'>{customerName || "—"}</p>
            <p className='rescheduling-customer-info-phone'>{customerPhone || "—"}</p>
          </div>
          <div>
            <img src={managerVectorIconUrl} alt='' />
            <p className='rescheduling-washing-type'>{serviceType || "—"}</p>
          </div>
        </div>

        <div className='rescheduling-customer-date-container'>
          <div className='rescheduling-date'>
            <p>{t("ReschudelingOrder.form.date.label")}</p>
            <div
              className='rescheduling-calendar-dropdown'
              onClick={handleCalendarOpen}
            >
              {datePart || "---"}
              <img src={leftArrowUrl} alt='' />
            </div>

            <SingleCalendarMobileSheet
              open={calendarOpen}
              setOpen={setCalendarOpen}
              initialDate={selectedDateObj}
              applyDate={(date) => {
                setDatePart(format(date, "d MMMM"));
                setSelectedDateObj(date);
                setCalendarOpen(false);
              }}
            />

            <p>{t("ReschudelingOrder.form.time.label")}</p>
            <div
              onClick={() => setTimePickerOpen(true)}
              className='rescheduling-time-date'
            >
              {pickedTime || "---"}
              <img src={leftArrowUrl} alt='' />
            </div>
          </div>
        </div>

        <div className='rescheduling-save-block'>
          <button
            disabled={!isSaveEnabled || isSaving}
            className={`rescheduling-save-btn ${
              isSaveEnabled ? "active" : "inactive"
            }`}
            onClick={handleSave}
          >
            {isSaving ? <span className='spinner' /> : t("ReschudelingOrder.form.save.button")}
          </button>
        </div>
      </div>

      <TimePickerMobileSheet
        open={timePickerOpen}
        setOpen={setTimePickerOpen}
        applyTime={(time) => setPickedTime(time)}
      />
    </div>
  );
}
