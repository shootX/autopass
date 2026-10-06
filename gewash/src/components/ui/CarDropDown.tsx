import { motion } from "framer-motion";
import { hatchbackIconUrl } from "@/assets/staticUrls";
import type { CarPreview } from "@/store/carSlice";
type Props = {
  open: boolean;
  setOpen: (v: boolean) => void;
  applyCar: (car: CarPreview) => void;
  selectedCar: CarPreview;
  cars: CarPreview[];
};

export function CarDropDown({
  open,
  setOpen,
  selectedCar,
  applyCar,
  cars,
}: Props) {
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
        onClick={() => setOpen(false)}
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
          minHeight: "200px",
          overflowY: "auto",
          boxShadow: "0 0 20px rgba(0,0,0,0.1)",
          cursor: "grab",
          padding: "16px",
          width: "100%",
        }}
      >
        <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
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
                borderRadius: "9999px",
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
            Choose your vehicle
          </h3>

          <div
            style={{
              display: "flex",
              flexDirection: "column",
              gap: "12px",
              padding: "0 16px",
            }}
          >
            {cars.map((car) => {
              const isSelected = car.plate === selectedCar?.plate;
              return (
                <div
                  key={car.plate}
                  onClick={() => {
                    if (car.id !== selectedCar?.id) {
                      applyCar(car);
                    }
                    setOpen(false);
                  }}
                  style={{
                    display: "flex",
                    justifyContent: "start",
                    alignItems: "center",
                    borderRadius: "12px",
                    color: isSelected ? "#F7B233" : "#183D69",
                    cursor: "pointer",
                    transition: "background-color 0.3s",
                  }}
                >
                  <span style={{ fontWeight: 500, whiteSpace: "nowrap" }}>
                    <img
                      style={{
                        padding: "4px",
                        backgroundColor: "#183D69",
                        borderRadius: "8px",
                        marginRight: "8px",
                      }}
                      src={hatchbackIconUrl}
                      alt='Car icon'
                    />
                    {car.plate}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      </motion.div>
    </>
  );
}
