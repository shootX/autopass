import { useState } from "react";
import { checkVoucher, formatCodeForDisplay, useVoucher, type VoucherCheckResult } from "../lib/voucherApi";
import Spinner from "../components/Spinner";
import logOutIconUrl from "../assets/log_out_icon.svg";

const DISCOUNT_CODE_MAX_LEN = 8;

function sanitizeDiscountInput(value: string): string {
  return value.replace(/[^A-Za-z0-9]/g, "").toUpperCase().slice(0, DISCOUNT_CODE_MAX_LEN);
}

function delay(ms: number): Promise<void> {
  return new Promise((r) => setTimeout(r, ms));
}

type Props = {
  onLogout: () => void;
};

type StageVisual = "done" | "active" | "pending";

function StageBar({ discount, sms, bonus }: { discount: StageVisual; sms: StageVisual; bonus: StageVisual }) {
  const cls = (s: StageVisual) => {
    if (s === "done") return "cv-stage cv-stage--done";
    if (s === "active") return "cv-stage cv-stage--active";
    return "cv-stage cv-stage--pending";
  };

  return (
    <div className="cv-stages" role="list" aria-label="Verification steps">
      <div className={cls(discount)} role="listitem">
        <div className="cv-stage__bar" aria-hidden />
        <span className="cv-stage__label">Discount code</span>
      </div>
      <div className={cls(sms)} role="listitem">
        <div className="cv-stage__bar" aria-hidden />
        <span className="cv-stage__label">SMS confirmation</span>
      </div>
      <div className={cls(bonus)} role="listitem">
        <div className="cv-stage__bar" aria-hidden />
        <span className="cv-stage__label">Bonus redemption</span>
      </div>
    </div>
  );
}

export default function CodeVerificationPage({ onLogout }: Props) {
  /** 1 = voucher, 2 = SMS only, 3 = redeem (POST /use) */
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [rawDiscount, setRawDiscount] = useState("");
  const [smsCode, setSmsCode] = useState("");
  const [storedDiscountDisplay, setStoredDiscountDisplay] = useState("");
  const [tempCode, setTempCode] = useState<string | null>(null);
  const [result, setResult] = useState<VoucherCheckResult>({ status: "idle" });
  const [resendBusy, setResendBusy] = useState(false);
  const [successBurst, setSuccessBurst] = useState(false);

  const busy = result.status === "loading";

  const stagesStep1: { discount: StageVisual; sms: StageVisual; bonus: StageVisual } = {
    discount: "active",
    sms: "pending",
    bonus: "pending",
  };

  const stagesStep2: { discount: StageVisual; sms: StageVisual; bonus: StageVisual } = {
    discount: "done",
    sms: "active",
    bonus: "pending",
  };

  const stagesStep3: { discount: StageVisual; sms: StageVisual; bonus: StageVisual } = {
    discount: "done",
    sms: "done",
    bonus: "active",
  };

  function onDiscountChange(e: React.ChangeEvent<HTMLInputElement>) {
    setRawDiscount(sanitizeDiscountInput(e.target.value));
    if (result.status === "invalid" || result.status === "error") {
      setResult({ status: "idle" });
    }
  }

  async function runVoucherCheck() {
    const code = rawDiscount;
    if (code.length !== DISCOUNT_CODE_MAX_LEN) return;
    setResult({ status: "loading", code });
    try {
      const { payload, ok, code: cleaned, tempCode: tc } = await checkVoucher(code);
      if (ok && tc) {
        setStoredDiscountDisplay(formatCodeForDisplay(cleaned) || formatCodeForDisplay(code));
        setTempCode(tc);
        setStep(2);
        setResult({ status: "idle" });
      } else {
        setResult({ status: "invalid", code: cleaned, payload });
      }
    } catch (err) {
      const msg = err instanceof Error ? err.message : "Unexpected error";
      setResult({ status: "error", code, message: msg });
    }
  }

  function onCancelStep2() {
    setStep(1);
    setSmsCode("");
    setTempCode(null);
    setResult({ status: "idle" });
  }

  /** Step 2 → 3: только UI, без API (списание — на шаге 3). */
  function proceedToStep3() {
    if (smsCode.trim().length < 4 || !tempCode) return;
    setResult({ status: "idle" });
    setStep(3);
  }

  function onBackFromStep3() {
    setStep(2);
    setResult({ status: "idle" });
  }

  async function redeemVoucher() {
    if (!tempCode || smsCode.trim().length < 4) return;
    setResult({ status: "loading", code: smsCode });
    try {
      const { ok, payload } = await useVoucher(tempCode, smsCode);
      if (ok) {
        setSuccessBurst(true);
        await delay(3000);
        setSuccessBurst(false);
        setStep(1);
        setRawDiscount("");
        setSmsCode("");
        setTempCode(null);
        setStoredDiscountDisplay("");
        setResult({ status: "idle" });
      } else {
        const msg = payload.message || payload.error || "Could not redeem";
        setResult({ status: "error", code: smsCode, message: msg });
      }
    } catch (err) {
      const msg = err instanceof Error ? err.message : "Unexpected error";
      setResult({ status: "error", code: smsCode, message: msg });
    }
  }

  async function onResendSms() {
    if (!rawDiscount || rawDiscount.length !== DISCOUNT_CODE_MAX_LEN) return;
    setResendBusy(true);
    setResult({ status: "idle" });
    try {
      const { ok, tempCode: tc, payload } = await checkVoucher(rawDiscount);
      if (ok && tc) {
        setTempCode(tc);
        setSmsCode("");
      } else {
        setResult({ status: "invalid", code: rawDiscount, payload });
      }
    } catch (err) {
      const msg = err instanceof Error ? err.message : "Unexpected error";
      setResult({ status: "error", code: rawDiscount, message: msg });
    } finally {
      setResendBusy(false);
    }
  }

  const canProceedToStep3 =
    smsCode.trim().length >= 4 && !!tempCode && !resendBusy && result.status !== "loading";

  const canRedeem =
    smsCode.trim().length >= 4 && !!tempCode && result.status !== "loading" && step === 3;

  const discountComplete = rawDiscount.length === DISCOUNT_CODE_MAX_LEN;

  const step1HasError = step === 1 && (result.status === "invalid" || result.status === "error");
  const step1ErrorText =
    step === 1 && result.status === "invalid"
      ? result.payload?.message || result.payload?.error || "Incorrect code"
      : step === 1 && result.status === "error"
        ? result.message
        : null;

  const step2HasError = step === 2 && (result.status === "invalid" || result.status === "error");
  const step2ErrorText =
    step === 2 && result.status === "invalid"
      ? result.payload?.message || result.payload?.error || "Incorrect code"
      : step === 2 && result.status === "error"
        ? result.message
        : null;

  const step3HasError = step === 3 && result.status === "error";
  const step3ErrorText = step === 3 && result.status === "error" ? result.message : null;

  const stages = step === 1 ? stagesStep1 : step === 2 ? stagesStep2 : stagesStep3;

  const subtitle =
    step === 1
      ? "Please enter your code to verificate it."
      : step === 2
        ? "Please enter your code from SMS."
        : "The code has been verified. You can redeem your bonuses.";

  return (
    <div className="cv-page">
      <header className="cv-topbar">
        <button className="cv-logout" type="button" onClick={onLogout}>
          <span>Log Out</span>
          <img src={logOutIconUrl} alt="" className="cv-logout__icon" width={14} height={14} />
        </button>
      </header>

      <main className="cv-center">
        <div className={`cv-card${successBurst ? " cv-card--success-pulse" : ""}`}>
          {successBurst ? (
            <div className="cv-success-overlay" aria-live="polite">
              <div className="cv-success-glow" aria-hidden />
              <div className="cv-success-check-wrap">
                <svg className="cv-success-check" viewBox="0 0 64 64" fill="none" aria-hidden>
                  <circle className="cv-success-check__ring" cx="32" cy="32" r="28" stroke="#183D69" strokeWidth="3" />
                  <path
                    className="cv-success-check__mark"
                    d="M20 33l8 8 16-20"
                    stroke="#F5A623"
                    strokeWidth="3.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </div>
              <p className="cv-success-title">Success</p>
              <p className="cv-success-sub">Voucher redeemed</p>
            </div>
          ) : null}

          <div className="cv-title">Code Verification</div>
          <div className="cv-subtitle">{subtitle}</div>

          <StageBar {...stages} />

          {step === 1 ? (
            <form
              className={`cv-form${step1HasError ? " cv-form--error" : ""}`}
              onSubmit={(e) => e.preventDefault()}
            >
              {step1HasError && step1ErrorText ? (
                <div className="cv-error-banner" role="alert">
                  {step1ErrorText}
                </div>
              ) : null}

              <label className={`cv-label${step1HasError ? " cv-label--error" : ""}`} htmlFor="cv-discount">
                Discount code
              </label>
              <input
                id="cv-discount"
                className={`cv-input cv-input--code${step1HasError ? " cv-input--error" : ""}`}
                value={formatCodeForDisplay(rawDiscount)}
                onChange={onDiscountChange}
                onKeyDown={(e) => {
                  if (e.key === "Enter") e.preventDefault();
                }}
                disabled={busy}
                autoComplete="off"
                placeholder="XX - XX - XX - XX"
                inputMode="text"
                spellCheck={false}
                aria-invalid={step1HasError}
              />

              <button
                className={`cv-send cv-send--full${busy ? " cv-send--busy" : ""}${step1HasError ? " cv-send--error-state" : ""}`}
                type="button"
                disabled={busy || !discountComplete}
                onClick={() => void runVoucherCheck()}
              >
                {busy ? <Spinner /> : "Send"}
              </button>
            </form>
          ) : null}

          {step === 2 ? (
            <form
              className={`cv-form cv-form--step2${step2HasError ? " cv-form--error" : ""}`}
              onSubmit={(e) => e.preventDefault()}
            >
              {step2HasError && step2ErrorText ? (
                <div className="cv-error-banner" role="alert">
                  {step2ErrorText}
                </div>
              ) : null}

              <div className="cv-field-group">
                <label className="cv-label cv-label--muted" htmlFor="cv-discount-ro">
                  Discount code
                </label>
                <input
                  id="cv-discount-ro"
                  className="cv-input cv-input--readonly"
                  value={storedDiscountDisplay}
                  readOnly
                  tabIndex={-1}
                />
              </div>

              <div className="cv-field-group">
                <label className={`cv-label${step2HasError ? " cv-label--error" : ""}`} htmlFor="cv-sms">
                  SMS code
                </label>
                <input
                  id="cv-sms"
                  className={`cv-input${step2HasError ? " cv-input--error" : ""}`}
                  value={smsCode}
                  onChange={(e) => {
                    setSmsCode(e.target.value.replace(/\D/g, "").slice(0, 8));
                    if (result.status === "invalid" || result.status === "error") {
                      setResult({ status: "idle" });
                    }
                  }}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") e.preventDefault();
                  }}
                  placeholder="XXXX"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  disabled={busy || resendBusy}
                  aria-invalid={step2HasError}
                />
              </div>

              <p className="cv-resend">
                I didn&apos;t get the code.{" "}
                <button type="button" className="cv-resend__link" onClick={onResendSms} disabled={busy || resendBusy}>
                  {resendBusy ? "Sending…" : "Send Again"}
                </button>
              </p>

              <div className="cv-actions">
                <button type="button" className="cv-btn cv-btn--outline" onClick={onCancelStep2} disabled={busy || resendBusy}>
                  Cancel
                </button>
                <button
                  className={`cv-btn cv-btn--primary${step2HasError ? " cv-btn--error-state" : ""}`}
                  type="button"
                  disabled={!canProceedToStep3}
                  onClick={proceedToStep3}
                >
                  Confirm
                </button>
              </div>
            </form>
          ) : null}

          {step === 3 ? (
            <form
              className={`cv-form cv-form--step2${step3HasError ? " cv-form--error" : ""}`}
              onSubmit={(e) => e.preventDefault()}
            >
              {step3HasError && step3ErrorText ? (
                <div className="cv-error-banner" role="alert">
                  {step3ErrorText}
                </div>
              ) : null}

              <div className="cv-field-group">
                <label className="cv-label cv-label--muted" htmlFor="cv-discount-ro3">
                  Discount code
                </label>
                <input
                  id="cv-discount-ro3"
                  className="cv-input cv-input--readonly"
                  value={storedDiscountDisplay}
                  readOnly
                  tabIndex={-1}
                />
              </div>

              <div className="cv-field-group">
                <label className="cv-label cv-label--muted" htmlFor="cv-sms-ro">
                  SMS code
                </label>
                <input id="cv-sms-ro" className="cv-input cv-input--readonly" value={smsCode} readOnly tabIndex={-1} />
              </div>

              <div className="cv-actions">
                <button
                  type="button"
                  className="cv-btn cv-btn--outline"
                  onClick={onBackFromStep3}
                  disabled={busy}
                >
                  Cancel
                </button>
                <button
                  className={`cv-btn cv-btn--primary${busy ? " cv-btn--busy" : ""}${step3HasError ? " cv-btn--error-state" : ""}`}
                  type="button"
                  disabled={!canRedeem}
                  onClick={() => void redeemVoucher()}
                >
                  {busy ? <Spinner /> : "Redeem Now"}
                </button>
              </div>
            </form>
          ) : null}
        </div>
      </main>
    </div>
  );
}
