import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Switch } from "./ui/switch";
import { InfinityIcon } from "./ui/InfinityIcon";
import type { PackageData } from "@/types";
import { CarDropDown } from "./ui/CarDropDown";
import type { Car } from "@/store/carSlice";
import { useMemo } from "react";
import { useActivatePackage } from "@/hooks/useActivatePackage";
import { useFetchPackagePricing } from "@/hooks/useFetchPackagePricing";
import { useTranslation } from "@/hooks/useTranslation";
import { leftArrowUrl } from "@/assets/staticUrls";

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
  compact,
  activePackages,
}: Props) {
  const t = useTranslation();
  const [selectedWashCount, setSelectedWashCount] = useState<number | "infinity" | null>(null);
  const [selectedTerm, setSelectedTerm] = useState<number | null>(null);
  const [autoRenewal, setAutoRenewal] = useState(true);
  const [carDropOpen, setCarDropOpen] = useState(false);
  const { activatePackage, loading, error, success } = useActivatePackage();
  const [selectedCar, setSelectedCar] = useState<Car | null>(null);
  const carId = selectedCar?.id ?? null;
  const { packages: pricingPackages } = useFetchPackagePricing(carId);  
  const availableWashes = pricingPackages.map(pkg => pkg.washes);
const availableTerms = pricingPackages[0]?.prices.map(p => p.month) ?? [];
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


  const price = useMemo(() => {
    if (!selectedTerm) return null;
  
    const pkg = pricingPackages[0];
    const priceObj = pkg?.prices.find(p => p.month === selectedTerm);
  
    return priceObj?.price ?? null;
  }, [selectedTerm, pricingPackages]);
  

  
  const handleSubmit = async () => {
    if (!selectedCar || !selectedWashCount || !selectedTerm) return;
  
    setIsSubmitting(true);
  
    const packageId =
      mode === "edit" ? initialPackage?.id : pricingPackages[0]?.id;
  
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
        window.location.href = response.url;
        return;
      } else {
        console.error("Failed to activate or missing payment URL");
      }
  
      await activatePackage(payload);
  
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
          className={`washing-package-block ${compact ? "compact" : ""}`}
          initial={{ opacity: 0, height: 0 }}
          animate={{ opacity: 1, height: "auto" }}
          exit={{ opacity: 0, height: 0 }}
          transition={{ duration: 0.3 }}
        >
          <h1>{mode === "edit" ? "Update package" : t("WashingPackageForm.title.create")}</h1>
          <p className='package-warn'>
            {t("WashingPackageForm.warning")}
          </p>

          {/* Vehicle selector */}
          {mode === "create" && (
            <div className='you-vehicle'>
              <span>{t("WashingPackageForm.vehicle.label")}</span>
              {availableCars.length === 1 ? (
                <p className='vehicle-number-plate'>{availableCars[0].plate}</p>
              ) : (
                <div
                  onClick={() => setCarDropOpen(true)}
                  className='vehicle-dropdown'
                >
                  <button className='vehicle-selector'>
                    {selectedCar?.plate}
                  </button>
                  <img
                    src={leftArrowUrl}
                    alt='arrow'
                  />
                </div>
              )}
            </div>
          )}

          {/* Wash count */}
          <div className='number-washers-select'>
            <p>{t("WashingPackageForm.washes.label")}</p>
            <div className='washers-select-options'>
              {availableWashes.map((count) => (
                <div
                  key={count}
                  className={selectedWashCount === count ? "active" : ""}
                  onClick={() => setSelectedWashCount(count)}
                >
                  {count}
                </div>
              ))}
              {/* <div
                className={selectedWashCount === "infinity" ? "active" : ""}
                onClick={() => setSelectedWashCount("infinity")}
              >
                <InfinityIcon
                  color={
                    selectedWashCount === "infinity" ? "#F7B233" : "#183D69"
                  }
                />
              </div> */}
            </div>
          </div>

          {/* Term */}
          <div className='number-washers-select'>
            <p>{t("WashingPackageForm.term.label")}</p>
            <div className='pheriod-select-options'>
              {availableTerms.map((term) => (
                <div
                  key={term}
                  className={selectedTerm === term ? "active" : ""}
                  onClick={() => setSelectedTerm(term)}
                >
                  <span>{term}</span>
                  <span
                    style={{
                      color: selectedTerm === term ? "#F7B233" : "#183D69",
                    }}
                  >
                    {t("WashingPackageForm.term.unit")}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Renewal */}
          <div className='renewal'>
            <div>{t("WashingPackageForm.renewal.label")}</div>
            <Switch
              checked={autoRenewal}
              onCheckedChange={(val) => setAutoRenewal(val)}
            />
          </div>

          {/* Price */}
          <div className='sub-price-block'>
            <p>{t("WashingPackageForm.price.label")}</p>
            <p className='price'>{price !== null ? `₾ ${price}` : "—"}</p>
          </div>

          {/* Submit */}
          <button
            className='activate-package-btn'
            disabled={
              !selectedCar ||
              !selectedTerm ||
              selectedWashCount === null ||
              isSubmitting
            }
            onClick={handleSubmit}
          >
            {isSubmitting ? (
              <span className='spinner' />
            ) : mode === "edit" ? (
            t("WashingPackageForm.button.edit")
            ) : (
              t("WashingPackageForm.button.create")
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
