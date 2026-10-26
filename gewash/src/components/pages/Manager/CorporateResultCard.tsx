import React, { useState } from "react";
import { customFetch } from "@/utils/customFetch";
import { closeIconUrl, whashesUrl } from "@/assets/staticUrls";
import { useTranslation } from "@/hooks/useTranslation";
import { readAccessToken } from "@/lib/accessToken";
import {
  approvePackageBody,
  approveShouldRescan,
  approveShowsError,
  corporateCanApprove,
  spendApproveToken,
  type CorporateCheck,
} from "@/lib/packageApprove";

type Props = {
  result: CorporateCheck;
  onClose: () => void;
  /** 403 and 409 need a fresh scan; `showError` is true for 403. */
  onRescan: (showError: boolean) => void;
};

/** A company car after the scan: which company, wash package or discount, and what is left. */
export default function CorporateResultCard({ result, onClose, onRescan }: Props): React.JSX.Element {
  const t = useTranslation();
  const car = result.corporate;
  const [grant, setGrant] = useState<string | null>(result.approve_token ?? null);
  const [busy, setBusy] = useState(false);
  const [left, setLeft] = useState({ car: car.washes_left, company: car.company_washes });
  const [approved, setApproved] = useState(false);
  const [error, setError] = useState(corporateCanApprove(result) ? "" : car.reason_text || t("Scanner.cannotWash"));
  const driver = [car.user?.name, car.user?.surname].filter(Boolean).join(" ");

  const approve = async () => {
    const spent = spendApproveToken(grant);
    setGrant(spent.remaining);
    const body = approvePackageBody({ approveToken: spent.token });
    if (!body) return;
    setBusy(true);
    try {
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/corporate-wash/${car.id}/approve`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${readAccessToken()}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(body),
      });
      if (approveShouldRescan(res.status)) {
        onRescan(approveShowsError(res.status));
        return;
      }
      const data = await res.json().catch(() => null);
      if (!res.ok) {
        setError(typeof data?.error === "string" ? data.error : t("Scanner.cannotWash"));
        return;
      }
      setLeft({ car: data?.corporate?.washes_left ?? left.car - 1, company: data?.corporate?.company_washes ?? left.company - 1 });
      setApproved(true);
      setError("");
    } catch {
      setError(t("Scanner.cannotWash"));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className='qr-result-card'>
      <div className='qr-client-block'>
        <p className='qr-client-phone'>{t("Scanner.companyCar")}</p>
        <p className='qr-client-name'>{car.company}</p>
        {driver ? <p className='qr-client-phone'>{driver}</p> : null}
        <img src={closeIconUrl} alt='close' className='qr-close-icon' onClick={onClose} style={{ cursor: "pointer" }} />
        <hr />
      </div>

      <div className='qr-active-package-block'>
        <p className='qr-active-package'>{car.mode === "washes" ? t("Scanner.modeWashes") : t("Scanner.modeDiscount")}</p>
        {car.plate ? <span className='package-end'>{car.plate}</span> : null}
      </div>
      {car.mode === "washes" && (
        <div className='qr-flexible-info'>
          <div className='qr-total-washes-block'>
            <p className='qr-total-washes'>
              <img src={whashesUrl} alt='' /> {t("Scanner.carWashes")}
            </p>
            <span className='qr-whashes-count'>{left.car}</span>
          </div>
          <div className='qr-package-period-block'>
            <p className='qr-package-period'>{t("Scanner.companyWashes")}</p>
            <span className='qr-package-month-count'>{left.company}</span>
          </div>
        </div>
      )}
      {error && <p className='warning'>{error}</p>}
      {approved ? (
        <div className='qr-success-banner'>{t("Scanner.approved")}</div>
      ) : corporateCanApprove(result) && !error ? (
        <button className='qr-approve-button' onClick={approve} disabled={busy || !grant}>
          {busy ? <span className='spinner' /> : t("Scanner.approve")}
        </button>
      ) : null}
    </div>
  );
}
