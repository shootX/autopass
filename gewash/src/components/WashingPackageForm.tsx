import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Switch } from "./ui/switch";
import type { PackageData } from "@/types";
import { CarDropDown } from "./ui/CarDropDown";
import type { Car } from "@/store/carSlice";
import { useMemo } from "react";
import { useActivatePackage } from "@/hooks/useActivatePackage";
import { useFetchPackagePricing } from "@/hooks/useFetchPackagePricing";
import { useTranslation } from "@/hooks/useTranslation";
import { bodyLabel, carParts, formatGel, savePendingPayment } from "@/lib/v4";
import { Info, Repeat2 } from "lucide-react";

type Props = {
  mode: "create" | "edit";
  isVisible: boolean;
  cars: Car[]; 
  initialPackage?: PackageData;
  onSubmit: (pkg: PackageData) => void;
  onClose: () => void;
  compact?: boolean;
  activePackages: PackageData[];
};

export function WashingPackageForm({
  mode,
  isVisible,
  cars,
  initialPackage,
  onSubmit,
  onClose,
  activePackages,
}: Props) {
  const t = useTranslation();
  const [selectedWashCount, setSelectedWashCount] = useState<number | "infinity" | null>(null);
  const [selectedTerm, setSelectedTerm] = useState<number | null>(null);
  const [autoRenewal, setAutoRenewal] = useState(true);
  const [carDropOpen, setCarDropOpen] = useState(false);
  const { activatePackage, loading, error } = useActivatePackage();
  const [selectedCar, setSelectedCar] = useState<Car | null>(null);
  const carId = selectedCar?.id ?? null;
  const { packages: pricingPackages, loading: pricingLoading, error: pricingError } = useFetchPackagePricing(carId);  
  const availableWashes = pricingPackages
    .map((pkg) => pkg.washes)
    .filter((count): count is number => typeof count === "number");
  const [isSubmitting, setIsSubmitting] = useState(false);


  const availableCars: Car[] = useMemo(() => {
    return mode === "create"
      ? cars.filter((car) => !activePackages.some((pkg) => pkg.plate === car.plate))
      : cars;
  }, [mode, cars, activePackages]); 
  
  useEffect(() => {
    if (mode === "edit" && initialPackage && isVisible) {
      const car = availableCars.find((c) => c.plate === initialPackage.plate) ?? null;
      setSelectedCar(car);
      setSelectedWashCount(initialPackage.washes);
      setSelectedTerm(initialPackage.period);
      setAutoRenewal(initialPackage.autoRenewal);
    } else if (mode === "create" && isVisible) {
      setSelectedCar(availableCars[0] ?? null);
    }
  }, [mode, initialPackage, availableCars, isVisible]);


  const pricedPackage = useMemo(() => {
    if (selectedWashCount && selectedWashCount !== "infinity") {
      return pricingPackages.find((pkg) => pkg.washes === selectedWashCount) ?? pricingPackages[0];
    }
    return pricingPackages[0];
  }, [pricingPackages, selectedWashCount]);

  const availableTerms = pricedPackage?.prices.map((p) => p.month) ?? [];

  useEffect(() => {
    if (mode !== "create" || selectedWashCount != null || !pricingPackages.length) return;
    const best = [...pricingPackages].sort((a, b) => (b.prices?.length ?? 0) - (a.prices?.length ?? 0))[0];
    if (typeof best?.washes === "number") setSelectedWashCount(best.washes);
  }, [mode, pricingPackages, selectedWashCount]);

  useEffect(() => {
    if (selectedTerm != null || !availableTerms.length) return;
    setSelectedTerm(availableTerms[Math.floor((availableTerms.length - 1) / 2)]);
  }, [availableTerms, selectedTerm]);

  const price = useMemo(() => {
    if (!selectedTerm || !pricedPackage) return null;
    return pricedPackage.prices.find((p) => p.month === selectedTerm)?.price ?? null;
  }, [selectedTerm, pricedPackage]);
  

  
  const handleSubmit = async () => {
    if (!selectedCar || !selectedWashCount || !selectedTerm) return;
  
    setIsSubmitting(true);
  
    const packageId = mode === "edit" ? initialPackage?.id : pricedPackage?.id;
  
    if (!packageId) {
      setIsSubmitting(false);
      return;
    }
  
    const payload = {
      car_id: selectedCar.id,
      number_of_washes: selectedWashCount,
      sub_term: selectedTerm,
      renewal: autoRenewal,
    };
  
    try {
      const response = await activatePackage(payload);
  
      if (response?.success && response.url) {
        savePendingPayment({
          kind: "package",
          car: carParts(selectedCar).title || selectedCar.plate,
          washes: typeof selectedWashCount === "number" ? selectedWashCount : undefined,
          months: selectedTerm,
          price: price ?? undefined,
          renewal: autoRenewal,
          at: new Date().toISOString(),
        });
        window.location.href = response.url;
        return;
      }

      console.error("Failed to activate or missing payment URL");
  
      onSubmit({
        id: packageId,
        car_id: payload.car_id,
        plate: selectedCar.plate,
        washes: selectedWashCount,
        model: "",
        period: selectedTerm,
        startDate: initialPackage?.startDate ?? new Date(),
        autoRenewal,
      });
  
      onClose();
    } catch (err) {
      console.error("Submit error:", err);
    } finally {
      setIsSubmitting(false);
    }
  };
  
  return (
    <AnimatePresence>
      {isVisible && (
        <motion.div
          className="v4-buy"
          initial={{ opacity: 0, height: 0 }}
          animate={{ opacity: 1, height: "auto" }}
          exit={{ opacity: 0, height: 0 }}
          transition={{ duration: 0.3 }}
        >
          {selectedCar && (
            <button
              type="button"
              className="v4-carcard v4-dk"
              onClick={() => availableCars.length > 1 && setCarDropOpen(true)}
              style={{ width: "100%", textAlign: "left", border: 0, cursor: availableCars.length > 1 ? "pointer" : "default" }}
            >
              <div className="v4-kicker" style={{ color: "#A7B1AA" }}>{t("WashingPackageForm.vehicle.label")}</div>
              <h2>{carParts(selectedCar).title || selectedCar.plate}</h2>
              <div style={{ display: "flex", gap: 8, marginTop: 10, alignItems: "center" }}>
                <span className="ap-plate" style={{ borderColor: "#fff" }}><b>GE</b><span>{selectedCar.plate}</span></span>
                {bodyLabel(carParts(selectedCar).type) && (
                  <span className="v4-chip" style={{ background: "#26332B", color: "#D9E3DB" }}>{bodyLabel(carParts(selectedCar).type)}</span>
                )}
              </div>
              <span className="v4-limeico" style={{ position: "absolute", right: 16, top: 16 }}>
                <Repeat2 size={20} />
              </span>
              <div className="foot">
                <Info size={16} />
                <span>{t("WashingPackageForm.warning")}</span>
              </div>
            </button>
          )}

          <div style={{ marginTop: 18 }}>
            <div className="v4-sec">{t("WashingPackageForm.washes.label")}</div>
            {pricingLoading && <p className="v4-kicker" style={{ marginTop: 8 }}>{t("MyPackages.loading")}</p>}
            {pricingError && <p className="ap-error" style={{ marginTop: 8 }}>{pricingError}</p>}
            {!pricingLoading && availableWashes.length === 0 && (
              <p className="v4-kicker" style={{ marginTop: 8 }}>{t("MyPackages.empty")}</p>
            )}
            <div className="v4-tiles" style={{ gridTemplateColumns: `repeat(${Math.min(Math.max(availableWashes.length, 1), 3)}, 1fr)` }}>
              {availableWashes.map((count) => (
                <button
                  key={count}
                  type="button"
                  className={`v4-tile${selectedWashCount === count ? " on" : ""}`}
                  onClick={() => {
                    setSelectedWashCount(count);
                    setSelectedTerm(null);
                  }}
                >
                  <b>{count}</b>
                  რეცხვა
                </button>
              ))}
            </div>
          </div>

          <div style={{ marginTop: 18 }}>
            <div className="v4-sec">{t("WashingPackageForm.term.label")}</div>
            <div className="v4-radios">
              {availableTerms.map((term) => {
                const termPrice = pricedPackage?.prices.find((p) => p.month === term)?.price;
                return (
                  <button
                    key={term}
                    type="button"
                    className={`v4-ro${selectedTerm === term ? " on" : ""}`}
                    onClick={() => setSelectedTerm(term)}
                  >
                    <span className="rd" />
                    <b>{term} {t("WashingPackageForm.term.unit")}</b>
                    {termPrice != null && <span className="pr">{formatGel(termPrice)}</span>}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="v4-renew">
            <div style={{ flex: 1 }}>
              <b>{t("WashingPackageForm.renewal.label")}</b>
              <div className="v4-kicker">ვადის ბოლოს ბარათიდან ჩამოიჭრება</div>
            </div>
            <Switch checked={autoRenewal} onCheckedChange={(val) => setAutoRenewal(val)} />
          </div>

          {error && <p className="ap-error" style={{ marginTop: 8 }}>{error}</p>}

          <button
            className="ap-btn"
            style={{ marginTop: 16 }}
            disabled={!selectedCar || !selectedTerm || selectedWashCount === null || isSubmitting || loading}
            onClick={handleSubmit}
          >
            {isSubmitting || loading ? (
              <span className="spinner" />
            ) : (
              <>
                {mode === "edit" ? t("WashingPackageForm.button.edit") : t("WashingPackageForm.button.create")}
                {price != null && <span> · {formatGel(price)}</span>}
              </>
            )}
          </button>
        </motion.div>
      )}
      {carDropOpen && selectedCar && (
        <CarDropDown
          open={carDropOpen}
          setOpen={setCarDropOpen}
          selectedCar={selectedCar}
          cars={availableCars}
          applyCar={(car) => setSelectedCar(car)}
        />
      )}
    </AnimatePresence>
  );
}
