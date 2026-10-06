import { Html5Qrcode } from "html5-qrcode";
import React, { useEffect, useRef, useState } from "react";
import "../../../styles/manager-scanner.scss";
import { useCheckQr } from "@/hooks/useCheckQr";
import { differenceInMonths, format } from "date-fns";
import { customFetch } from "@/utils/customFetch";
import { closeIconUrl, whashesUrl, timeUrl } from "@/assets/staticUrls";

export default function ManagerScanner(): React.JSX.Element {
  const [scannedCode, setScannedCode] = useState<string | null>(null);
  const [scanner, setScanner] = useState<Html5Qrcode | null>(null);
  const [isCameraReady, setIsCameraReady] = useState(false);
  const [showScannerUI, setShowScannerUI] = useState(true);
  const [isScannerRunning, setIsScannerRunning] = useState(false);
  const [isApproved, setIsApproved] = useState(false);
  const [approvalSuccess, setApprovalSuccess] = useState(false);
  const [showResultCard, setShowResultCard] = useState(true);
  const [approvedOffset, setApprovedOffset] = useState(0);


  const qrRef = useRef<HTMLDivElement>(null);
  const { result, loading, error } = useCheckQr(scannedCode);

  useEffect(() => {
    if (scanner || !showScannerUI || !qrRef.current) return;

    const html5QrCode = new Html5Qrcode("qr-reader");
    setScanner(html5QrCode);

    html5QrCode
      .start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: { width: 250, height: 250 },
          aspectRatio: 1.0,
          disableFlip: false,
        },
        (decodedText: string) => {
          console.log("QR scanned:", decodedText);
          setScannedCode(decodedText);

          html5QrCode
            .stop()
            .then(() => {
              html5QrCode.clear().then(() => {
                if (qrRef.current) qrRef.current.innerHTML = "";
                setIsScannerRunning(false);
                setScanner(null);
                setShowScannerUI(false);
              });
            })
            .catch((err) => {
              console.warn("Stop error after scan:", err);
              setIsScannerRunning(false);
              setScanner(null);
              setShowScannerUI(false);
            });
        },
        () => { }
      )
      .then(() => {
        setIsCameraReady(true);
        setIsScannerRunning(true);
      })
      .catch((err) => console.error("Start error:", err));
  }, [showScannerUI]);


  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file || !scanner) return;

    try {
      const result = await scanner.scanFile(file, true);
      console.log("Scanned from image:", result);
      setScannedCode(result);
      setShowScannerUI(false);
    } catch (err) {
      console.error("Image scan error:", err);
    }
  };

  const handleCloseCard = () => {
    setApprovalSuccess(false);
    setScannedCode(null);
    setShowResultCard(false);
    scanAgain();
  };

  useEffect(() => {
    if (result) setShowResultCard(true);
  }, [result]);



  const handleApprove = async () => {
    setIsApproved(true);
    if (!pkgMeta?.id) return;

    const token = localStorage.getItem("access_token");

    try {
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/package/${pkgMeta.id}/approve`, {
        method: "POST",
        headers: {
          "Authorization": `Bearer ${token}`,
          "Content-Type": "application/json",
        },
      });

      if (!res.ok) throw new Error("Approval failed");

      const data = await res.json();

      console.log("Approved:", data.package.id);
      setApprovalSuccess(true);
      setApprovedOffset(1);
    } catch (err) {
      console.error("Approve error:", err);
    }
  };

  const scanAgain = () => {
    setScannedCode(null);
    setShowResultCard(false);
    setApprovalSuccess(false);
    setIsApproved(false);
    setApprovedOffset(0);

    if (qrRef.current) qrRef.current.innerHTML = "";

    const html5QrCode = new Html5Qrcode("qr-reader");
    setScanner(html5QrCode);

    html5QrCode
      .start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: (viewfinderWidth, viewfinderHeight) => {
            const size = Math.min(viewfinderWidth, viewfinderHeight) * 0.8;
            return {
              width: size,
              height: size,
            };
          },
          aspectRatio: 1.0,
          disableFlip: false,
        },
        (decodedText: string) => {
          console.log("QR scanned:", decodedText);
          setScannedCode(decodedText);

          html5QrCode
            .stop()
            .then(() => {
              html5QrCode.clear().then(() => {
                if (qrRef.current) qrRef.current.innerHTML = "";
                setIsScannerRunning(false);
                setScanner(null);
                setShowScannerUI(false);
              });
            })
            .catch((err) => {
              console.warn("Stop error after scan:", err);
              setIsScannerRunning(false);
              setScanner(null);
              setShowScannerUI(false);
            });
        },
        () => { }
      )
      .then(() => {
        setIsCameraReady(true);
        setIsScannerRunning(true);
      })
      .catch((err) => console.error("Start error:", err));
  };


  const pkgMeta = result?.package;
  const user = pkgMeta?.user;
  const pkg = pkgMeta?.package;

  const isValid =
    result?.success &&
    typeof user?.name === "string" &&
    typeof user?.surname === "string" &&
    typeof user?.phone === "string" &&
    typeof pkg?.count_washes === "number" &&
    typeof pkgMeta?.used_washes === "number" &&
    typeof pkgMeta?.created_at === "string" &&
    typeof pkgMeta?.end_date === "string";

  return (
    <div>
      <header style={{ textAlign: "center", justifyContent: "center" }}>
        Scanner
      </header>
      <div className='scanner-wrapper'>
        <div id='qr-reader' ref={qrRef} className='scanner-box' />
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
              <p className='qr-client-phone'>+{user.phone}</p>
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