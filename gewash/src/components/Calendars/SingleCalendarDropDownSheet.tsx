import { useEffect, useState, useRef } from "react";
import { motion } from "framer-motion";
import { useDrag } from "@use-gesture/react";
import { Calendar } from "@/components/ui/calendar";
import { addMonths, format, subMonths } from "date-fns";
import { leftArrowUrl, rightArrowUrl } from "@/assets/staticUrls";

export function SingleCalendarMobileSheet({
  open,
  setOpen,
  applyDate,
  initialDate,
  title = "Custom select chose",
  disabled = false,
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  applyDate: (date: Date) => void;
  initialDate?: Date;
  title?: string;
  disabled?: boolean;
}) {
  const [month, setMonth] = useState(new Date());
  const [selected, setSelected] = useState<Date | undefined>(initialDate);
  const calendarRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setSelected(initialDate);
  }, [initialDate]);

  useDrag(
    ({ down, swipe }) => {
      if (!down) {
        const [swipeX] = swipe;
        if (swipeX === -1) setMonth(addMonths(month, 1));
        if (swipeX === 1) setMonth(subMonths(month, 1));
      }
    },
    {
      target: calendarRef,
      axis: "x",
      swipe: {
        distance: [50, 0],
      },
      filterTaps: true,
      pointer: {
        touch: true,
      },
    }
  );

  const handleYearChange = (year: number) => {
    const newDate = new Date(month);
    newDate.setFullYear(year);
    setMonth(newDate);
  };

  if (!open) return null;

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
          bottom: 0,
          left: 0,
          right: 0,
          zIndex: 1000,
          backgroundColor: "#fff",
          borderTopLeftRadius: "16px",
          borderTopRightRadius: "16px",
          minHeight: "400px",
          boxShadow: "0 0 20px rgba(0,0,0,0.1)",
          cursor: "grab",
          padding: "16px",
          overflowY: "auto",
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
              fontSize: "18px",
              fontWeight: 600,
              color: "#374151",
              padding: "0px",
            }}
          >
            {title}
          </h3>



          <div style={{ display: "flex", justifyContent: "space-around", paddingTop: "8px" }}>
            <button
              type='button'
              onClick={() => setMonth(subMonths(month, 1))}
              style={{
                color: "#1E9E5A",
                fontWeight: "bold",
                border: "none",
                background: "transparent",
              }}
            >
              <img src={leftArrowUrl} alt='prev' />
            </button>

            {/* <span
              style={{
                color: "#14482F",
                fontWeight: 500,
                fontSize: "16px",
              }}
            >
              {month.toLocaleDateString("en-US", {
                month: "long",
                year: "numeric",
              }).replace(/^\w/, (c) => c.toUpperCase())}
            </span> */}

            <div
              style={{
                display: "flex",
                alignItems: "center",
                gap: 8,
              }}
            >
              <span
                style={{
                  color: "#14482F",
                  fontWeight: 500,
                  fontSize: "16px",
                }}
              >
                {month.toLocaleDateString("en-US", {
                  month: "long",
                })}
              </span>

              <select
                value={month.getFullYear()}
                onChange={(e) => {
                  const newDate = new Date(month);
                  newDate.setFullYear(Number(e.target.value));
                  setMonth(newDate);
                }}
                style={{
                  border: "none",
                  background: "transparent",
                  color: "#14482F",
                  fontWeight: 500,
                  fontSize: "16px",
                  cursor: "pointer",
                  outline: "none",
                }}
              >
                {Array.from({ length: 151 }, (_, i) => {
                  const year = 1950 + i;

                  return (
                    <option key={year} value={year}>
                      {year}
                    </option>
                  );
                })}
              </select>
            </div>

            <button
              type='button'
              onClick={() => setMonth(addMonths(month, 1))}
              style={{
                color: "#1E9E5A",
                fontWeight: "bold",
                border: "none",
                background: "transparent",
              }}
            >
              <img src={rightArrowUrl} alt='next' />
            </button>



          </div>

          <div style={{ display: "flex", justifyContent: "center", height: "260px", }} ref={calendarRef}>
            <Calendar
              month={month}
              onMonthChange={setMonth}
              mode='single'
              selected={selected}
              onSelect={setSelected}
              weekStartsOn={1}
              style={{
                borderRadius: "12px",
                padding: "12px 16px",
                fontSize: "14px",
                color: "#14482F",
              }}
              disableBefore={disabled}
            />
          </div>

          {selected && (
            <div
              style={{
                textAlign: "center",
                fontSize: "18px",
                fontWeight: 500,
                color: "#14482F",
              }}
            >
              {format(selected, "d MMMM yyyy")}
            </div>
          )}

          <button
            onClick={() => {
              if (selected) {
                applyDate(selected);
                setOpen(false);
              }
            }}
            disabled={!selected}
            style={{
              borderRadius: "16px",
              backgroundColor: "#14482F",
              padding: "12px 0px",
              textAlign: "center",
              color: "#B5DD3A",
              margin: "0px 16px",
              marginBottom: "16px",
              fontWeight: 600,
              fontSize: "14px",
              border: "none",
              cursor: selected ? "pointer" : "not-allowed",
              opacity: selected ? 1 : 0.5,
            }}
          >
            Select
          </button>
        </div>
      </motion.div>
    </>
  );
}
