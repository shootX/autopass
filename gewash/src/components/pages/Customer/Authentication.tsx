import { fetchUserData } from "@/lib/fetchUserData";
import React, { useState } from "react";
import { useDispatch } from "react-redux";
import { Link, useNavigate } from "react-router-dom";
const API_URL = import.meta.env.VITE_API_URL;
import { useTranslation } from "@/hooks/useTranslation";
import { logoUrl } from "@/assets/staticUrls";
import { CAR_SEDAN } from "@/lib/v4";
import { Eye, EyeOff } from "lucide-react";
import { georgianLocalDigits } from "@/lib/format";
export default function Authentication() {
  const [showPassword, setShowPassword] = useState(false);
  const [password, setPassword] = useState("");
  const [phone, setPhone] = useState("");
  const [error, setError] = useState<{ phone?: string; password?: string }>({});
  const [isLoading, setIsLoading] = useState(false);
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const t = useTranslation();

  const togglePassword = () => setShowPassword((prev) => !prev);

  const handlePhoneChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
    setPhone(georgianLocalDigits(e.target.value));
    if (error.phone) setError((prev) => ({ ...prev, phone: undefined }));
  };

  const handleLoginSuccess = (token: string) => {
  // Проверяем, запущено ли приложение в среде WebView React Native
  if (window.ReactNativeWebView) {
    window.ReactNativeWebView.postMessage(JSON.stringify({
      type: 'USER_AUTHORIZED',
      payload: { token: token } // опционально
    }));
  }
};

  const handleLogin = async () => {
    const trimmedPassword = password.trim();
    const fullPhone = phone;

    const newError: { phone?: string; password?: string } = {};
    if (!phone) newError.phone = t("Authentication.errors.phoneRequired");
    if (!trimmedPassword)
      newError.password = t("Authentication.errors.passwordRequired");

    if (Object.keys(newError).length > 0) {
      setError(newError);
      console.warn("Validation failed:", newError);
      return;
    }

    setIsLoading(true);

    try {
      const res = await fetch(`${API_URL}/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          phone: fullPhone,
          password: trimmedPassword,
        }),
      });

      const data = await res.json();
      console.log("Response:", data);

      if (res.ok && data.access_token) {
        localStorage.setItem("access_token", data.access_token);
        handleLoginSuccess(data.access_token);
        await fetchUserData(dispatch);
        navigate("/");
      } else {
        const serverError = data?.error || "Login failed";
        setError({ password: "incorrect" });

        if (serverError.includes("phone")) {
          setError({ phone: "not-found" });
        } else if (serverError.includes("password")) {
          setError({ password: "incorrect" });
        }
      }
    } catch (err) {
      console.error("Network error:", err);
    } finally {
      setIsLoading(false);
    }
  };

  const phoneError = error.phone === "not-found"
    ? t("Authentication.errors.phoneNotFound")
    : error.phone;
  const passwordError = error.password === "incorrect"
    ? t("Authentication.errors.passwordIncorrect")
    : error.password;

  return (
    <div className="v4-auth">
      <div className="top">
        <img className="logo" src={logoUrl} alt={t("Authentication.logoAlt")} />
        <img className="car" src={CAR_SEDAN} alt="" />
      </div>
      <div className="panel v4-dk">
        <h1>{t("Authentication.greeting.title")}</h1>
        <p className="sub">{t("Authentication.greeting.subtitle")}</p>
        {phoneError && <p className="ap-error" style={{ marginTop: 16 }}>{phoneError}</p>}
        {passwordError && <p className="ap-error">{passwordError}</p>}

        <div style={{ marginTop: 24 }}>
          <label>{t("Authentication.labels.phone")}</label>
          <div className="v4-dinput">
            <span className="prefix">{t("Authentication.prefix")}</span>
            <span className="bar" />
            <input
              type="tel"
              inputMode="numeric"
              value={phone}
              onChange={handlePhoneChange}
              maxLength={18}
              placeholder={t("Authentication.placeholders.phone")}
            />
          </div>
        </div>

        <div style={{ marginTop: 16 }}>
          <label>{t("Authentication.labels.password")}</label>
          <div className="v4-dinput">
            <input
              type={showPassword ? "text" : "password"}
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
                if (error.password) setError((prev) => ({ ...prev, password: undefined }));
              }}
              placeholder={t("Authentication.placeholders.password")}
            />
            <button type="button" onClick={togglePassword} aria-label={t("Authentication.labels.password")}>
              {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
            </button>
          </div>
        </div>

        <div style={{ textAlign: "right", marginTop: 12 }}>
          <Link to="/renew-password" style={{ color: "var(--ap-lime)", fontWeight: 700, fontSize: 14, textDecoration: "none" }}>
            {t("Authentication.forgotPassword")}
          </Link>
        </div>

        <button className="ap-btn" style={{ marginTop: 20, boxShadow: "none" }} onClick={handleLogin} disabled={isLoading}>
          {isLoading ? <span className="spinner" /> : t("Authentication.buttons.signIn")}
        </button>

        <p style={{ marginTop: 22, textAlign: "center", color: "#97A29A", fontSize: 14.5 }}>
          {t("Authentication.signupPrompt")}{" "}
          <Link to="/register" style={{ color: "#fff", fontWeight: 800, textDecoration: "none" }}>
            {t("Authentication.buttons.signUp")}
          </Link>
        </p>
      </div>
    </div>
  );
}
