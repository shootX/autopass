import React, { useEffect, useState, useRef } from "react";
import { motion } from "framer-motion";
import { useDrag } from "@use-gesture/react";
import { Calendar } from "@/components/ui/calendar";
import { addMonths, format, subMonths } from "date-fns";
import type { DateRange } from "react-day-picker";
import { leftArrowUrl, rightArrowUrl } from "@/assets/staticUrls";


export function CalendarMobileSheet({
  open,
  setOpen,
  applyRange,
  initialRange,
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  applyRange: (range: { from: Date; to: Date }) => void;
  initialRange?: { from: Date; to: Date };
}) {
  const [month, setMonth] = useState(new Date());
  const [range, setRange] = useState<DateRange | undefined>(initialRange);
  const calendarRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setRange(initialRange);
  }, [initialRange]);
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
      swipe: { distance: [50, 0] },
      filterTaps: true,
      pointer: { touch: true },
    }
  );

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
        drag="y"
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
          minHeight: "400px",
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
              margin: 0,
              padding: "0px"
            }}
          >
            Custom statistic period
          </h3>

          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-around",
              padding: "8px 12px",
            }}
          >
            <button
              type="button"
              onClick={() => setMonth(subMonths(month, 1))}
              style={{
                border: "none",
                background: "transparent",
                color: "#17BA68",
                fontWeight: 700,
                cursor: "pointer",
              }}
            >
              <img src={leftArrowUrl} alt="Previous Month" />
            </button>

            <span
              style={{
                fontSize: "18px",
                fontWeight: 500,
                color: "#183D69",
              }}
            >
              {month.toLocaleDateString("en-US", {
                month: "long",
                year: "numeric",
              }).replace(/^\w/, (c) => c.toUpperCase())}
            </span>

            <button
              type="button"
              onClick={() => setMonth(addMonths(month, 1))}
              style={{
                border: "none",
                background: "transparent",
                color: "#17BA68",
                fontWeight: 700,
                cursor: "pointer",
              }}
            >
              <img src={rightArrowUrl} alt="Next Month" />
            </button>
          </div>

          <div ref={calendarRef} style={{ display: "flex", justifyContent: "center" }}>
            <Calendar
              month={month}
              onMonthChange={setMonth}
              mode="range"
              selected={range}
              onSelect={setRange}
              weekStartsOn={1}
            />
          </div>

          {range?.from && range?.to && (
            <div
              style={{
                marginTop: "4px",
                textAlign: "center",
                fontSize: "16px",
                fontWeight: 500,
                color: "#183D69",
              }}
            >
              {format(range.from, "d MMMM yyyy")} – {format(range.to, "d MMMM yyyy")}
            </div>
          )}

          <button
            onClick={() => {
              if (range?.from && range?.to) {
                applyRange({ from: range.from, to: range.to });
                setOpen(false);
              }
            }}
            disabled={!range?.from || !range?.to}
            style={{
              borderRadius: "16px",
              backgroundColor: "#183D69",
              padding: "12px 24px",
              textAlign: "center",
              color: "#F7B233",
              fontWeight: 600,
              fontSize: "14px",
              border: "none",
              margin: "0 auto 16px auto",
              cursor: range?.from && range?.to ? "pointer" : "not-allowed",
              opacity: range?.from && range?.to ? 1 : 0.5,
              display: "block",
              width: "fit-content",
            }}
          >
            Select
          </button>
        </div>
      </motion.div>
    </>
  );
}
