import { useState } from "react";
import { Switch } from "./ui/switch";
import { WashingPackageForm } from "./WashingPackageForm";
import { motion, AnimatePresence } from "framer-motion";
import type { PackageData } from "@/types";
import type { Car } from "@/store/carSlice";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "@/hooks/useTranslation";
import { customFetch } from "@/utils/customFetch";
import { invalidatePackagesCache } from "@/hooks/useActivePackages";
import {
  dropIconUrl,
  timeUrl,
  trashIconUrl,
  reloadIconUrl,
  calendarIconYellowUrl,
  qrIconYellowUrl,
} from "@/assets/staticUrls";

type Props = {
  id: number;
  plate: string;
  model: string;
  washes: number | "infinity";
  period: number;
  startDate: Date;
  autoRenewal: boolean;
  setAutoRenewal: (v: boolean) => void;
  onEdit?: (updated: PackageData) => void;
  cars: Car[];
  onDelete?: (plate: string) => void;
};

export function ActivePackageCard({
  id, // ← вот это
  plate,
  model,
  washes,
  period,
  startDate,
  autoRenewal,
  setAutoRenewal,
  onEdit,
  onDelete,
  cars,
}: Props){
  const t = useTranslation();
  const [isEditing, setIsEditing] = useState(false);
  const [showDeletePopup, setShowDeletePopup] = useState(false);
  const navigate = useNavigate();
  
  const deletePackage = async (id: number): Promise<boolean> => {
    const token = localStorage.getItem("access_token");
  
    try {
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/packages/${id}/remove`, {
        method: "DELETE",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
          Accept: "application/json",
        },
      });
  
      if (res.ok) invalidatePackagesCache();
      return res.ok;
    } catch (err) {
      console.error("Delete package error:", err);
      return false;
    }
  };
  

  const endDate = new Date(startDate);
  endDate.setMonth(endDate.getMonth() + period);
  const today = new Date();
  const daysLeft = Math.ceil((endDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

  const isExpired = endDate < today;
  const isExpiringSoon = daysLeft <= 7 && !isExpired;

  const initialPackage: PackageData = {
    plate,
    model,
    washes,
    period,
    startDate,
    autoRenewal,
  };

  return (
    <div className='active-package-card'>
      <div className='package-header'>
        <h2>{t("ActivePackageCard.header")}</h2>
        <span
          className={`package-status ${
            isExpired ? "expired" : isExpiringSoon ? "warning" : ""
          }`}
        >
          {isExpired
            ? t("ActivePackageCard.status.expired")
            : t("ActivePackageCard.status.until").replace(
                "{{date}}",
                endDate.toLocaleDateString("en-GB")
              )}
        </span>
      </div>

      <div className='vehicle-info'>
        <span>{t("ActivePackageCard.vehicle.label")}</span>
        <p className='vehicle-plate'>
          {plate} <span>({model})</span>
        </p>
      </div>

      <div className='package-details'>
        <div className='detail-block'>
          <span className='detail-label'>
            <img
              src={dropIconUrl}
              alt={t("ActivePackageCard.details.washes.iconAlt")}
            />
            {t("ActivePackageCard.details.washes.label")}
          </span>
          <span className='detail-value'>
            {washes === "infinity"
              ? t("ActivePackageCard.details.washes.infinity")
              : washes}
          </span>
          <span className='deatil-name'>
            {t("ActivePackageCard.details.washes.remaining")}
          </span>
        </div>
        <div className='detail-block'>
          <span className='detail-label'>
            <img
              src={timeUrl}
              alt={t("ActivePackageCard.details.period.iconAlt")}
            />
            {t("ActivePackageCard.details.period.label")}
          </span>
          <span className='detail-value'>{period}</span>
          <span className='deatil-name'>
            {t("ActivePackageCard.details.period.unit")}
          </span>
        </div>
      </div>

      <div className='switch-block-active-package'>
        <span>{t("ActivePackageCard.autoRenewal")}</span>
        <Switch
          onCheckedChange={(val) => setAutoRenewal(val)}
          checked={autoRenewal}
        />
      </div>

      <div className='package-actions'>
        <button onClick={() => setShowDeletePopup(true)}>
          <img
            src={trashIconUrl}
            alt={t("ActivePackageCard.actions.deleteAlt")}
          />
        </button>
        {/* <button onClick={() => setIsEditing((prev) => !prev)}>
          <img
            src={reloadIconUrl}
            alt={t("ActivePackageCard.actions.editAlt")}
          />
        </button> */}
        <button onClick={() => navigate("/branches")}>
          <img
            src={calendarIconYellowUrl}
            alt={t("ActivePackageCard.actions.calendarAlt")}
          />
        </button>
        <button onClick={() => navigate("/customer-qr-page")}>
          <img
            src={qrIconYellowUrl}
            alt={t("ActivePackageCard.actions.qrAlt")}
          />
        </button>
      </div>

      <AnimatePresence>
        {isEditing && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: "auto" }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.3 }}
          >
            <WashingPackageForm
              mode='edit'
              isVisible={true}
              cars={cars}
              initialPackage={initialPackage}
              onClose={() => setIsEditing(false)}
              onSubmit={(updated) => {
                onEdit?.(updated);
                setIsEditing(false);
              }}
              compact={true}
              activePackages={[]}
            />
          </motion.div>
        )}
      </AnimatePresence>

      {showDeletePopup && (
        <>
          <div
            className='package-delete-backdrop'
            onClick={() => setShowDeletePopup(false)}
          />
          <div className='package-delete-popup'>
            <h2>{t("ActivePackageCard.deletePopup.title")}</h2>
            <p>{t("ActivePackageCard.deletePopup.message")}</p>
            <div className='package-delete-popup-btns'>
              <button onClick={() => setShowDeletePopup(false)}>
                {t("ActivePackageCard.deletePopup.cancel")}
              </button>
              <button
                onClick={async () => {
                  const success = await deletePackage(id);
                  if (success) {
                    onDelete?.(plate);
                    setShowDeletePopup(false);
                    location.reload();
                  } else {
                    console.warn("Удаление не удалось");
                  }
                }}
              >
                {t("ActivePackageCard.deletePopup.confirm")}
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
