import { fetchUserData } from "@/lib/fetchUserData";
import React, { useState } from "react";
import { useDispatch } from "react-redux";
import { Link, useNavigate } from "react-router-dom";
const API_URL = import.meta.env.VITE_API_URL;
import { useTranslation } from "@/hooks/useTranslation";
import { logoUrl } from "@/assets/staticUrls";
import { Eye, EyeOff } from "lucide-react";
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
    let raw = e.target.value;

    raw = raw.replace(/[^\d+]/g, "");

    if (raw.includes("+")) {
      raw = "+" + raw.replace(/\+/g, "").slice(0, 12);
    }

    setPhone(raw);
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
    const fullPhone = "995" + phone.trim();

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
          phone: fullPhone.replace("+", ""), // сервер ожидает без "+"
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

  return (
    <div className="ap-auth">
      <img src={logoUrl} alt={t("Authentication.logoAlt")} style={{ height: 30, width: "auto" }} />
      <div style={{ marginTop: 48 }}>
        <h1 className="ap-title">{t("Authentication.greeting.title")}</h1>
        <p className="ap-sub">{t("Authentication.greeting.subtitle")}</p>
      </div>

      <div style={{ marginTop: 28 }}>
        {error.phone === "required" && <p className="ap-error">{t("Authentication.errors.phoneRequired")}</p>}
        {error.password === "required" && <p className="ap-error">{t("Authentication.errors.passwordRequired")}</p>}
        {error.phone === "not-found" && <p className="ap-error">{t("Authentication.errors.phoneNotFound")}</p>}
        {error.password === "incorrect" && <p className="ap-error">{t("Authentication.errors.passwordIncorrect")}</p>}

        <div className="ap-field">
          <label>{t("Authentication.labels.phone")}</label>
          <div className="ap-input">
            <span className="prefix">{t("Authentication.prefix")}</span>
            <span className="bar" />
            <input
              type="tel"
              inputMode="numeric"
              value={phone}
              onChange={handlePhoneChange}
              maxLength={9}
              placeholder={t("Authentication.placeholders.phone")}
            />
          </div>
        </div>

        <div className="ap-field" style={{ marginTop: 20 }}>
          <label>{t("Authentication.labels.password")}</label>
          <div className="ap-input">
            <input
              type={showPassword ? "text" : "password"}
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
                if (error.password) setError((prev) => ({ ...prev, password: undefined }));
              }}
              placeholder={t("Authentication.placeholders.password")}
            />
            <button type="button" onClick={togglePassword} aria-label={t("Authentication.labels.password")} style={{ border: 0, background: "transparent", color: "var(--ap-gray-400)", display: "grid" }}>
              {showPassword ? <EyeOff size={22} /> : <Eye size={22} />}
            </button>
          </div>
        </div>

        <div style={{ textAlign: "right", marginTop: 16 }}>
          <Link className="ap-link" to="/renew-password" style={{ fontSize: 14 }}>{t("Authentication.forgotPassword")}</Link>
        </div>

        <button className="ap-btn" style={{ marginTop: 32 }} onClick={handleLogin} disabled={isLoading}>
          {isLoading ? <span className="spinner" /> : t("Authentication.buttons.signIn")}
        </button>
      </div>

      <p style={{ marginTop: 36, textAlign: "center", color: "var(--ap-gray-600)", fontSize: 15 }}>
        {t("Authentication.signupPrompt")}{" "}
        <Link className="ap-link" to="/register">{t("Authentication.buttons.signUp")}</Link>
      </p>
    </div>
  );
}
