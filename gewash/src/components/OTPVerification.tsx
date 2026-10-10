import { useState, useRef } from "react";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "@/hooks/useTranslation";
import { ArrowLeft } from "lucide-react";
import { formatPhone } from "@/lib/format";

export default function OTPVerification({
  onVerify,
  phone,
  step = "2 / 3",
  notice,
}: {
  onVerify: (code: string) => Promise<void>;
  phone?: string;
  step?: string;
  notice?: string | null;
}) {
  const [otp, setOtp] = useState(Array(6).fill(""));
  const [errorCode, setErrorCode] = useState<"none" | "invalid">("none");
  const [isLoading, setIsLoading] = useState(false);
  const inputRefs = useRef<(HTMLInputElement | null)[]>([]);
  const t = useTranslation();
  const navigate = useNavigate();

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
    <div className="ap-otp">
      <button type="button" className="ap-back" onClick={() => navigate(-1)} aria-label="უკან">
        <ArrowLeft size={24} />
      </button>
      <div style={{ marginTop: 28 }}>
        <div className="ap-step">{step}</div>
        <h1 className="ap-title" style={{ marginTop: 6 }}>{t("Registration.stage.register.title")}</h1>
        <p className="ap-sub">
          {phone ? <><b style={{ color: "var(--ap-ink)" }}>{formatPhone(phone.startsWith("995") || phone.startsWith("+") ? phone : `995${phone}`)}</b>. </> : null}
          {t("OTPVerification.title")}
        </p>
        {notice ? <p className="ap-sub">{notice}</p> : null}
      </div>
      {errorCode === "invalid" && <p className="ap-error" style={{ marginTop: 16 }}>{t("OTPVerification.error.invalid")}</p>}
      <div className="ap-otp-boxes">
        {otp.map((digit, i) => (
          <input
            key={i}
            type="text"
            inputMode="numeric"
            maxLength={1}
            ref={(el) => { inputRefs.current[i] = el; }}
            value={digit}
            onChange={(e) => handleChange(e.target.value, i)}
            onKeyDown={(e) => handleKeyDown(e, i)}
          />
        ))}
      </div>
      <p style={{ marginTop: 24, fontSize: 15, color: "var(--ap-gray-600)" }}>
        {t("OTPVerification.resend.text")}{" "}
        <span className="ap-link">{t("OTPVerification.resend.link")}</span>
      </p>
      <button type="button" className="ap-btn" style={{ marginTop: 36 }} onClick={handleVerify} disabled={!buttonIsActive}>
        {isLoading ? <span className="spinner" /> : t("OTPVerification.button.send")}
      </button>
    </div>
  );
}
