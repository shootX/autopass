import { useState, useRef } from "react";
import { useTranslation } from "@/hooks/useTranslation";

export default function OTPVerification({
  onVerify,
}: {
  onVerify: (code: string) => Promise<void>;
}) {
  const [otp, setOtp] = useState(Array(6).fill(""));
  const [errorCode, setErrorCode] = useState<"none" | "invalid">("none");
  const [isLoading, setIsLoading] = useState(false);
  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);
  const t = useTranslation();

  const handleChange = (value: string, index: number) => {
    if (!/^\d?$/.test(value)) return;
    const newOtp = [...otp];
    newOtp[index] = value;
    setOtp(newOtp);
    setErrorCode("none");
    if (value && index < otp.length - 1) {
      inputRefs.current[index + 1]?.focus();
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent, index: number) => {
    if (e.key === "Backspace" && !otp[index] && index > 0) {
      inputRefs.current[index - 1]?.focus();
    }
  };

  const handleVerify = async () => {
    const code = otp.join("");
    if (!/^\d{6}$/.test(code)) return;
    setIsLoading(true);
    try {
      await onVerify(code);
    } catch (err) {
      console.error("Verification error:", err);
      setErrorCode("invalid");
    } finally {
      setIsLoading(false);
    }
  };

  const allDigitsFilled = otp.every((digit) => /^\d$/.test(digit));
  const buttonIsActive = allDigitsFilled && !isLoading;

  return (
    <div className="otp-wrapper">
      <h2>{t("OTPVerification.title")}</h2>

      {errorCode === "invalid" && (
        <div className="incorrect-code-error">
          <p>{t("OTPVerification.error.invalid")}</p>
        </div>
      )}

      <div className="input-otp-group">
        {otp.map((digit, i) => (
          <input
            key={i}
            type="text"
            inputMode="numeric"
            maxLength={1}
            ref={(el) => (inputRefs.current[i] = el)}
            value={digit}
            onChange={(e) => handleChange(e.target.value, i)}
            onKeyDown={(e) => handleKeyDown(e, i)}
            placeholder="-"
            className={`input-otp-slot ${digit ? "filled" : ""} ${errorCode === "invalid" ? "error" : ""}`}
          />
        ))}
      </div>

      <button
        onClick={handleVerify}
        disabled={!buttonIsActive}
        className={`send-button ${buttonIsActive ? "active" : ""}`}
      >
        {isLoading ? <span className="spinner" /> : t("OTPVerification.button.send")}
      </button>

      <div className="send-code-again">
        <p>
        {t("OTPVerification.resend.text")} <a href="#">{t("OTPVerification.resend.link")}</a>
        </p>
      </div>
    </div>
  );
}
