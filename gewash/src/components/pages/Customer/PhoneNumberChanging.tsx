import React, { useState } from "react";
import { useNavigate } from "react-router-dom";
import OTPVerification from "../../OTPVerification";
import { customFetch } from "@/utils/customFetch";
import { useTranslation } from "@/hooks/useTranslation";
import { leftArrowUrl } from "@/assets/staticUrls";

export default function PhoneNumberChanging() {
  const [phoneDigits, setPhoneDigits] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(false);
  const [stage, setStage] = useState<"change" | "verify">("change");
  const [tempCode, setTempCode] = useState<number | null>(null);
  const navigate = useNavigate();
  const t = useTranslation();

  const formatPhoneDigits = (raw: string): string => {
    const onlyDigits = raw.replace(/\D/g, "").slice(0, 9);
    const grouped = onlyDigits.match(/.{1,3}/g) || [];
    return grouped.join(" ");
  };

  const handlePhoneChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setPhoneDigits(formatPhoneDigits(e.target.value));
    if (error) setError(false);
  };

  const handleSaveNewPhone = async () => {
    setIsSubmitting(true);
    setError(false);

    const cleaned = phoneDigits.replace(/\D/g, "");
    if (cleaned.length !== 9) {
      alert(t("PhoneNumberChanging.form.invalidAlert"));
      setIsSubmitting(false);
      return;
    }

    try {
      const token = localStorage.getItem("access_token");
      const response = await customFetch(`${import.meta.env.VITE_API_URL}/change_phone`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ phone: `995${cleaned}` }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || "Failed to initiate phone change");
      }

      setTempCode(data.temp_code);
      setStage("verify");

      sessionStorage.setItem("temp_code", data.temp_code);
      sessionStorage.setItem("new_phone", `995${cleaned}`);
    } catch (err) {
      console.error(err);
      setError(true);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleVerifyOTP = async (code: string) => {
    try {
      const token = localStorage.getItem("access_token");
      const res = await customFetch(`${import.meta.env.VITE_API_URL}/change_phone/verify`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          temp_code: tempCode,
          code,
          phone: `995${phoneDigits.replace(/\D/g, "")}`,
        }),
      });

      const data = await res.json();

      if (data.success) {
        alert(t("PhoneNumberChanging.verification.success"));
        sessionStorage.removeItem("new_phone");
        navigate("/profile");
      } else {
        throw new Error(data.message || "Verification failed");
      }
    } catch (err) {
      console.error("Phone verification error:", err);
      alert(t("PhoneNumberChanging.verification.failure"));
    }
  };

  const isValid = phoneDigits.replace(/\D/g, "").length === 9;

  if (stage === "verify" && tempCode !== null) {
    return <OTPVerification onVerify={handleVerifyOTP} />;
  }

  return (
    <div>
      <header style={{ display: "flex", alignItems: "center", justifyContent: "space-between" }}>
        <img
          src={leftArrowUrl}
          alt={t("PhoneNumberChanging.header.backAlt")}
          onClick={() => navigate(-1)}
        />
        <h2>{t("PhoneNumberChanging.header.title")}</h2>
        <svg
          width="16"
          height="16"
          viewBox="0 0 16 16"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          onClick={() => navigate(-1)}
        >
          <path
            fillRule="evenodd"
            clipRule="evenodd"
            d="M8 10.1221L13.303 15.4251...Z"
            fill="#183D69"
          />
        </svg>
      </header>

      <div
        className='auth-input-block'
        style={{
          borderRadius: "16px",
          boxShadow: "0 2px 4px 2px rgba(0, 0, 0, 0.1)",
          padding: "16px",
          margin: "16px",
        }}
      >
        <h2 style={{ color: "#183D69", fontWeight: "600", fontSize: "20px" }}>
          {t("PhoneNumberChanging.form.title")}
        </h2>

        {error && (
          <div className="phone-number-doesnt-exist-error" style={{ color: "#FF6B6B" }}>
            <p>{t("PhoneNumberChanging.form.error")}</p>
          </div>
        )}

        <div className='auth-phone-input'>
          <label>{t("PhoneNumberChanging.form.label")}</label>
          <div className='input-with-prefix'>
            <span className='phone-prefix'>{t("PhoneNumberChanging.form.prefix")}</span>
            <input
              type='tel'
              inputMode='numeric'
              value={phoneDigits}
              onChange={handlePhoneChange}
              maxLength={11}
              placeholder={t("PhoneNumberChanging.form.placeholder")}
              className='custom-input'
              style={{
                borderColor: error ? "#FF6B6B" : undefined,
              }}
            />
          </div>
        </div>

        <div>
          <button
            onClick={handleSaveNewPhone}
            disabled={!isValid || isSubmitting}
            style={{
              backgroundColor: !isValid ? "#879AB1" : "#183D69",
              color: !isValid ? "#C8D1DC" : "#F7B233",
              padding: "15px 0px",
              borderRadius: "16px",
              fontWeight: 600,
              fontSize: "14px",
              width: "100%",
              border: "none",
              cursor: isValid && !isSubmitting ? "pointer" : "default",
              position: "relative",
              minWidth: "120px",
            }}
          >
            {isSubmitting ? (
              <span
                style={{
                  width: "18px",
                  height: "18px",
                  border: "2px solid #F7B233",
                  borderTopColor: "transparent",
                  borderRadius: "50%",
                  animation: "spin 0.7s linear infinite",
                  display: "inline-block",
                }}
              />
            ) : (
              t("PhoneNumberChanging.form.button.getCode")
            )}
          </button>
        </div>
      </div>

      <style>
        {`
          @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
          }
        `}
      </style>
    </div>
  );
}
