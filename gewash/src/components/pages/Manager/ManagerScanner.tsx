import { Html5Qrcode } from "html5-qrcode";
import React, { useEffect, useRef, useState } from "react";
import "../../../styles/manager-scanner.scss";
import { useCheckQr } from "@/hooks/useCheckQr";
import { differenceInMonths, format } from "date-fns";
import { customFetch } from "@/utils/customFetch";
import { closeIconUrl, whashesUrl, timeUrl } from "@/assets/staticUrls";
import { formatPhone } from "@/lib/format";
import Header from "@/components/Header";
import { releaseScanner } from "@/lib/releaseScanner";
import { useTranslation } from "@/hooks/useTranslation";
import { readAccessToken } from "@/lib/accessToken";
import { approvePackageBody, approveShouldRescan, approveShowsError, packageQrReady, spendApproveToken } from "@/lib/packageApprove";

const SCAN_CONFIG = {
  fps: 10,
  qrbox: { width: 250, height: 250 },
  aspectRatio: 1.0,
  disableFlip: false,
};

export default function ManagerScanner(): React.JSX.Element {
  const [scannedCode, setScannedCode] = useState<string | null>(null);
  const [showScannerUI, setShowScannerUI] = useState(true);
  const [cameraAttempt, setCameraAttempt] = useState(0);
  const [cameraError, setCameraError] = useState(false);
  const [isApproved, setIsApproved] = useState(false);
  const [approvalSuccess, setApprovalSuccess] = useState(false);
  const [showResultCard, setShowResultCard] = useState(true);
  const [approvedOffset, setApprovedOffset] = useState(0);
  const [grant, setGrant] = useState<string | null>(null);
  const [rescanNote, setRescanNote] = useState(false);


  const qrRef = useRef<HTMLDivElement>(null);
  const { result, loading, error } = useCheckQr(scannedCode);
  const t = useTranslation();

  useEffect(() => {
    if (!showScannerUI || !qrRef.current) return;

    let active = true;
    const html5QrCode = new Html5Qrcode("qr-reader");
    setCameraError(false);

    html5QrCode
      .start(
        { facingMode: "environment" },
        SCAN_CONFIG,
        (decodedText: string) => {
          if (!active) return;
          setScannedCode(decodedText);
          void releaseScanner(html5QrCode).finally(() => {
            if (!active) return;
            if (qrRef.current) qrRef.current.innerHTML = "";
            setShowScannerUI(false);
          });
        },
        () => { }
      )
      .catch(() => {
        if (!active) return;
        setCameraError(true);
      });

    return () => {
      active = false;
      void releaseScanner(html5QrCode);
    };
  }, [showScannerUI, cameraAttempt]);


  const handleCloseCard = () => {
    setApprovalSuccess(false);
    setScannedCode(null);
    setShowResultCard(false);
    scanAgain();
  };

  useEffect(() => {
    if (result) setShowResultCard(true);
    setGrant(result?.approve_token ?? null);
    if (result) setRescanNote(false);
  }, [result]);



  const handleApprove = async () => {
    const spent = spendApproveToken(grant);
    setGrant(spent.remaining);
    const body = approvePackageBody({
      qrCode: scannedCode,
      approveToken: spent.token,
    });
    if (!body || !pkgMeta?.id) return;
    setIsApproved(true);

    const token = readAccessToken();

    try {
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/package/${pkgMeta.id}/approve`, {
        method: "POST",
        headers: {
          "Authorization": `Bearer ${token}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify(body),
      });

      if (!res.ok) {
        if (approveShouldRescan(res.status)) {
          if (approveShowsError(res.status)) setRescanNote(true);
          scanAgain();
          return;
        }
        throw new Error("Approval failed");
      }

      await res.json();
      setApprovalSuccess(true);
      setApprovedOffset(1);
    } catch (err) {
      console.error("Approve error:", err);
      setIsApproved(false);
    }
  };

  const scanAgain = () => {
    setScannedCode(null);
    setShowResultCard(false);
    setApprovalSuccess(false);
    setIsApproved(false);
    setApprovedOffset(0);
    setCameraError(false);
    if (qrRef.current) qrRef.current.innerHTML = "";
    setShowScannerUI(true);
    setCameraAttempt((attempt) => attempt + 1);
  };


  const pkgMeta = result?.package;
  const user = pkgMeta?.user;
  const pkg = pkgMeta?.package;

  const isValid = packageQrReady(result) && user != null && pkgMeta != null && pkg != null;

  return (
    <div>
      <Header variant="bar" title="Scanner" style={{ textAlign: "center", justifyContent: "center" }} />
      <div className='scanner-wrapper'>
        <div id='qr-reader' ref={qrRef} className='scanner-box' />
        {cameraError && (
          <div className="scanner-camera-error">
            <p>{t("Scanner.cameraRequired")}</p>
            <button type="button" onClick={scanAgain}>{t("Scanner.retry")}</button>
          </div>
        )}
        {rescanNote && <p className="warning">{t("Scanner.rescan")}</p>}
        {showScannerUI && (
          <div className='image-upload'>
            {/* <label htmlFor='qr-image'>
              Upload QR image (for desktop test):
            </label>
            <input
              type='file'
              id='qr-image'
              accept='image/*'
              onChange={handleFileUpload}
            /> */}
          </div>
        )}
      </div>
      <div className='scanner-result'>
        {loading && <p style={{ textAlign: "center", marginTop: "16px" }}>Checking QR code...</p>}
        {error && <p className='error'>{error.message}</p>}
        {isValid && showResultCard && (
          <div className='qr-result-card'>
            <div className='qr-client-block'>
              <p className='qr-client-name'>
                {user.name} {user.surname}
              </p>
              {user.phone ? <p className='qr-client-phone'>{formatPhone(user.phone)}</p> : null}
              <img
                src={closeIconUrl}
                alt='close'
                className='qr-close-icon'
                onClick={handleCloseCard}
                style={{ cursor: "pointer" }}
              />
              <hr />
            </div>

            <div className='qr-active-package-block'>
              <p className='qr-active-package'>Active package</p>
              <span className='package-end'>
                until {format(new Date(pkgMeta.end_date), "dd.MM.yyyy")}
              </span>
            </div>
            <div className='qr-flexible-info'>
              <div className='qr-total-washes-block'>
                <p className='qr-total-washes'>
                  <img src={whashesUrl} alt='' />{" "}
                  Washes
                </p>
                <span className='qr-whashes-count'>
                  {pkgMeta.number_of_washes -
                    pkgMeta.used_washes -
                    approvedOffset}
                </span>
                <span className='qr-remaining'>remaining</span>
              </div>
              <div className='qr-package-period-block'>
                <p className='qr-package-period'>
                  <img src={timeUrl} alt='' /> Period{" "}
                </p>
                <span className='qr-package-month-count'>
                  {differenceInMonths(
                    new Date(pkgMeta.end_date),
                    new Date(pkgMeta.created_at)
                  )}{" "}
                </span>
                <span className='qr-package-month'>months</span>
              </div>
            </div>
            {!approvalSuccess ? (
              <button
                className='qr-approve-button'
                onClick={handleApprove}
                disabled={isApproved}
              >
                {isApproved ? <span className='spinner' /> : "Approve"}
              </button>
            ) : (
              <div className='qr-success-banner'>Aprove success</div>
            )}
          </div>
        )}
        {!loading && !error && scannedCode && !isValid && (
          <p className='warning'>
            QR scanned, but data is not valid or not full
          </p>
        )}
      </div>
    </div>
  );
}