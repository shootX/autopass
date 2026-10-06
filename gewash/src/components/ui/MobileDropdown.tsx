import { motion } from "framer-motion";
import { useTranslation } from "@/hooks/useTranslation";

export function MobileDropdownSheet({
  open,
  setOpen,
  setPeriod,
}: {
  open: boolean;
  setOpen: (v: boolean) => void;
  setPeriod: (p: string) => void;
}) {
  if (!open) return null;
  const t = useTranslation();

  const OPTIONS = [
    t("MobileDropdownSheet.options.today"),
    t("MobileDropdownSheet.options.yesterday"),
    t("MobileDropdownSheet.options.last7Days"),
    t("MobileDropdownSheet.options.lastMonth"),
    t("MobileDropdownSheet.options.custom"),
  ];

  return (
    <>
      <div
        style={{
          position: "fixed",
          inset: 0,
          backgroundColor: "rgba(24,61,105,0.5)",
          zIndex: 999,
        }}
      />

      <motion.div
        drag='y'
        dragMomentum={false}
        onDragEnd={(e, info) => {
          if (info.offset.y > 50) setOpen(false);
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
          minHeight: "375px",
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
          {/* Drag Indicator */}
          <div
            style={{
              display: "flex",
              justifyContent: "center",
              marginTop: "16px",
            }}
          >
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

          {/* Header */}
          <h3
            style={{
              textAlign: "center",
              fontSize: "20px",
              fontWeight: 600,
              color: "#183d69",
              padding: "0px",
            }}
          >
                {t("MobileDropdownSheet.title")}
          </h3>

          {/* Options */}
          <ul style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
            {OPTIONS.map((label) => (
              <li
                key={label}
                onClick={() => {
                  setPeriod(label);
                  setOpen(false);
                }}
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: "12px",
                  padding: "4px",
                  cursor: "pointer",
                  borderRadius: "8px",
                  transition: "background 0.2s ease",
                }}
              >
                <div
                  style={{
                    backgroundColor: "#183D69",
                    width: "40px",
                    height: "40px",
                    borderRadius: "12px",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <svg
                    width='20'
                    height='20'
                    viewBox='0 0 20 20'
                    fill='none'
                    xmlns='http://www.w3.org/2000/svg'
                  >
                    <path
                      d='M15 1.34001C16.5083 2.21087 17.7629 3.46054 18.6398 4.96531C19.5167 6.47009 19.9854 8.17779 19.9994 9.91935C20.0135 11.6609 19.5725 13.376 18.72 14.8947C17.8676 16.4135 16.6332 17.6832 15.1392 18.5783C13.6452 19.4734 11.9434 19.9628 10.2021 19.9981C8.46083 20.0333 6.74055 19.6132 5.21155 18.7793C3.68256 17.9453 2.39787 16.7265 1.48467 15.2435C0.571462 13.7605 0.0614093 12.0647 0.00500011 10.324L0 10L0.00500011 9.67601C0.0610032 7.94901 0.563548 6.26598 1.46364 4.79101C2.36373 3.31604 3.63065 2.09947 5.14089 1.2599C6.65113 0.420327 8.35315 -0.0135887 10.081 0.000452316C11.8089 0.0144933 13.5036 0.476012 15 1.34001ZM10 4.00001C9.75507 4.00005 9.51866 4.08997 9.33563 4.25273C9.15259 4.41549 9.03566 4.63976 9.007 4.88301L9 5.00001V10L9.009 10.131C9.0318 10.3045 9.09973 10.469 9.206 10.608L9.293 10.708L12.293 13.708L12.387 13.79C12.5624 13.9261 12.778 13.9999 13 13.9999C13.222 13.9999 13.4376 13.9261 13.613 13.79L13.707 13.707L13.79 13.613C13.9261 13.4376 13.9999 13.222 13.9999 13C13.9999 12.778 13.9261 12.5624 13.79 12.387L13.707 12.293L11 9.58501V5.00001L10.993 4.88301C10.9643 4.63976 10.8474 4.41549 10.6644 4.25273C10.4813 4.08997 10.2449 4.00005 10 4.00001Z'
                      fill='#F7B233'
                    />
                  </svg>
                </div>
                <span
                  style={{
                    fontSize: "18px",
                    fontWeight: 400,
                    color: "#183D69",
                  }}
                >
                  {label}
                </span>
              </li>
            ))}
          </ul>
        </div>
      </motion.div>
    </>
  );
}
