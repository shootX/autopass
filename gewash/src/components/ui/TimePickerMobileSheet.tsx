import React, { useState } from "react";
import { motion } from "framer-motion";

const TIME_OPTIONS = [
  "09:00", "11:00", "13:00", "15:00", "17:00", "19:00",
];

export function TimePickerMobileSheet({
  open,
  setOpen,
  applyTime,
  ...props
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  applyTime: (time: string) => void;
}) {
  const [selectedTime, setSelectedTime] = useState("");
console.log('props', props);
  if (props.disabled) {
    return null;
  }
  if (!open) return null;

  const now = new Date();

  const isToday =
    props.toDate &&
    props.toDate.getFullYear() === now.getFullYear() &&
    props.toDate.getMonth() === now.getMonth() &&
    props.toDate.getDate() === now.getDate();

  return (
    <>
      <div
        style={{
          position: "fixed",
          inset: 0,
          backgroundColor: "rgba(24,61,105,0.5)",
          zIndex: 999,
          paddingTop: "16px",
        }}
      />
      <motion.div
        drag='y'
        dragMomentum={false}
        onDragEnd={(e, info) => {
          if (info.offset.y > 60) setOpen(false);
        }}
        onUpdate={(latest) => {
          if (latest.y < 0) latest.y = 0;
        }}
        style={{
          position: "fixed",
          left: 0,
          right: 0,
          bottom: 0,
          zIndex: 1000,
          backgroundColor: "#fff",
          borderTopLeftRadius: "16px",
          borderTopRightRadius: "16px",
          minHeight: "300px",
          overflowY: "auto",
          boxShadow: "0 0 20px rgba(0,0,0,0.1)",
          cursor: "grab",
          padding: "16px",
          width: "100%",
          maxWidth: "480px",
          marginLeft: "auto",
          marginRight: "auto",
        }}
      >
        <div style={{ display: "flex", flexDirection: "column", gap: "24px" }}>
          <div style={{ display: "flex", justifyContent: "center", marginTop: "16px" }}>
            <div
              style={{
                height: "6px",
                width: "40px",
                borderRadius: "16px",
                backgroundColor: "#D1D5DB",
                marginBottom: "16px",
              }}
            />
          </div>

          <h3
            style={{
              textAlign: "center",
              fontSize: "20px",
              fontWeight: 600,
              color: "#183D69",
              padding: "0px",
            }}
          >
            Choose a time
          </h3>

          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(2, 1fr)",
              gap: "16px",
              padding: "16px",

            }}
          >
            {TIME_OPTIONS.map((time) => {
              const [hours, minutes] = time.split(":").map(Number);

              const isPast =
                !!isToday &&
                (hours < now.getHours() ||
                  (hours === now.getHours() && minutes <= now.getMinutes()));

              return (
                <div
                  key={time}
                  onClick={() => {
                    if (!isPast) {
                      setSelectedTime(time);
                    }
                  }}
                  style={{
                    display: "flex",
                    alignItems: "center",
                    opacity: isPast ? 0.4 : 1,
                    cursor: isPast ? "not-allowed" : "pointer",
                  }}
                >
                  <div
                    style={{
                      padding: "10px",
                      borderRadius: "12px",
                      backgroundColor: selectedTime === time ? "#F7B233" : "#183D69",
                      width: "40px",
                      height: "40px",
                    }}
                  >
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path
                        d="M15 1.34001C16.5083 2.21087 17.7629 3.46054 18.6398 4.96531C19.5167 6.47009 19.9854 8.17779 19.9994 9.91935C20.0135 11.6609 19.5725 13.376 18.72 14.8947C17.8676 16.4135 16.6332 17.6832 15.1392 18.5783C13.6452 19.4734 11.9434 19.9628 10.2021 19.9981C8.46083 20.0333 6.74055 19.6132 5.21155 18.7793C3.68256 17.9453 2.39787 16.7265 1.48467 15.2435C0.571462 13.7605 0.0614093 12.0647 0.00500011 10.324L0 10L0.00500011 9.67601C0.0610032 7.94901 0.563548 6.26598 1.46364 4.79101C2.36373 3.31604 3.63065 2.09947 5.14089 1.2599C6.65113 0.420327 8.35315 -0.0135887 10.081 0.000452316C11.8089 0.0144933 13.5036 0.476012 15 1.34001ZM10 4.00001C9.75507 4.00005 9.51866 4.08997 9.33563 4.25273C9.15259 4.41549 9.03566 4.63976 9.007 4.88301L9 5.00001V10L9.009 10.131C9.0318 10.3045 9.09973 10.469 9.206 10.608L9.293 10.708L12.293 13.708L12.387 13.79C12.5624 13.9261 12.778 13.9999 13 13.9999C13.222 13.9999 13.4376 13.9261 13.613 13.79L13.707 13.707L13.79 13.613C13.9261 13.4376 13.9999 13.222 13.9999 13C13.9999 12.778 13.9261 12.5624 13.79 12.387L13.707 12.293L11 9.58501V5.00001L10.993 4.88301C10.9643 4.63976 10.8474 4.41549 10.6644 4.25273C10.4813 4.08997 10.2449 4.00005 10 4.00001Z"
                        fill={selectedTime === time ? "#183D69" : "#F7B233"}
                      />
                    </svg>
                  </div>

                  <button
                    disabled={isPast}
                    onClick={() => {
                      if (!isPast) {
                        setSelectedTime(time);
                      }
                    }}
                    style={{
                      padding: "8px 16px",
                      borderRadius: "8px",
                      fontWeight: selectedTime === time ? 600 : 400,
                      color: isPast
                        ? "#9CA3AF"
                        : selectedTime === time
                          ? "#F7B233"
                          : "#183D69",
                      cursor: isPast ? "not-allowed" : "pointer",
                      WebkitTapHighlightColor: "transparent",
                    }}
                  >
                    {time}
                  </button>
                </div>
              );
            })}
          </div>

          <button
            onClick={() => {
              applyTime(selectedTime);
              setOpen(false);
            }}
            disabled={!selectedTime}
            style={{
              borderRadius: "16px",
              backgroundColor: "#183D69",
              width: "100%",
              padding: "12px 24px",
              textAlign: "center",
              color: "#F7B233",
              fontWeight: 600,
              fontSize: "14px",
              border: "none",
              margin: "0 auto 16px auto",
              cursor: selectedTime ? "pointer" : "not-allowed",
              opacity: selectedTime ? 1 : 0.5,
              display: "block",
            }}
          >
            Select
          </button>
        </div>
      </motion.div>
    </>
  );
}
