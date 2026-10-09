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
import { bodyLabel, carParts } from "@/lib/v4";
import { WashRing } from "@/components/v4/WashRing";
import { QrCode, Trash2 } from "lucide-react";

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
  totalWashes?: number;
  endDate?: string;
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
  totalWashes,
  endDate: endsOn,
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
  

  const computedEnd = endsOn ? new Date(endsOn) : new Date(startDate);
  if (!endsOn) computedEnd.setMonth(computedEnd.getMonth() + period);
  const today = new Date();
  const daysLeft = Math.ceil((computedEnd.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

  const isExpired = computedEnd < today;
  const isExpiringSoon = daysLeft <= 7 && !isExpired;

  const initialPackage: PackageData = {
    plate,
    model,
    washes,
    period,
    startDate,
    autoRenewal,
  };

  const matched = cars.find((car) => car.plate === plate);
  const parts = carParts(matched);
  const remaining = washes === "infinity" ? null : washes;
  const total = totalWashes ?? (typeof washes === "number" ? washes : 0);
  const shownExpiry = computedEnd.toLocaleDateString("en-GB");

  return (
    <div className="active-package-card" style={{ background: "transparent", boxShadow: "none", padding: 0 }}>
      <div style={{ textAlign: "center" }}>
        <div style={{ fontFamily: "Plus Jakarta Sans, sans-serif", fontWeight: 800, fontSize: 24 }}>
          {parts.title || plate}
        </div>
        <div className="v4-kicker" style={{ marginTop: 2 }}>
          {[plate, bodyLabel(parts.type || model)].filter(Boolean).join(" · ")}
        </div>
        {(isExpired || isExpiringSoon) && (
          <div className={isExpired ? "ap-closed" : "ap-open"} style={{ marginTop: 6 }}>
            {isExpired
              ? t("ActivePackageCard.status.expired")
              : t("ActivePackageCard.status.until").replace("{{date}}", shownExpiry)}
          </div>
        )}
      </div>

      {remaining != null && total > 0 && <WashRing remaining={remaining} total={total} />}

      <div className="v4-pkg-num">
        <b>{remaining ?? t("ActivePackageCard.details.washes.infinity")}</b>
        {remaining != null && total > 0 && <span> / {total}</span>}
        <div className="v4-kicker">{t("ActivePackageCard.details.washes.remaining")}</div>
      </div>

      <div className="v4-pair">
        <div>
          <div className="v4-kicker">{t("ActivePackageCard.details.period.label")}</div>
          <b>{period} {t("ActivePackageCard.details.period.unit")}</b>
        </div>
        <div>
          <div className="v4-kicker">{t("Home.period.expiration")}</div>
          <b>{shownExpiry}</b>
        </div>
      </div>

      <div className="v4-renew">
        <span style={{ flex: 1, fontWeight: 800 }}>{t("ActivePackageCard.autoRenewal")}</span>
        <Switch checked={autoRenewal} onCheckedChange={(val) => setAutoRenewal(val)} />
      </div>

      <button type="button" className="ap-btn" style={{ marginTop: 8 }} onClick={() => navigate("/customer-qr-page")}>
        {t("QRPage.car.showQRAlt")} <QrCode size={20} />
      </button>
      <button type="button" className="v4-ib" style={{ marginTop: 10 }} onClick={() => setShowDeletePopup(true)} aria-label={t("ActivePackageCard.actions.deleteAlt")}>
        <Trash2 size={18} />
      </button>

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
