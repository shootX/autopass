import { fetchUserData } from "@/lib/fetchUserData";
import React, { useState } from "react";
import { useDispatch } from "react-redux";
import { Link, useNavigate } from "react-router-dom";
const API_URL = import.meta.env.VITE_API_URL;
import { useTranslation } from "@/hooks/useTranslation";
import {
  logoUrl,
  googleServiceIconUrl,
  facebookUrl,
} from "@/assets/staticUrls";
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
    <div className="auth-wrapper">
      <div className="auth-logo-block">
        <img src={logoUrl} alt={t("Authentication.logoAlt")} />
      </div>

      <div className="auth-greetings">
        <h1>{t("Authentication.greeting.title")}</h1>
        <p>{t("Authentication.greeting.subtitle")}</p>
      </div>

      {error.phone === "required" && (
        <p className="incorrect-password-error">
          {t("Authentication.errors.phoneRequired")}
        </p>
      )}
      {error.password === "required" && (
        <p className="incorrect-password-error">
          {t("Authentication.errors.passwordRequired")}
        </p>
      )}
      {error.phone === "not-found" && (
        <p className="incorrect-password-error">
          {t("Authentication.errors.phoneNotFound")}
        </p>
      )}
      {error.password === "incorrect" && (
        <p className="incorrect-password-error">
          {t("Authentication.errors.passwordIncorrect")}
        </p>
      )}

      <div className="auth-input-block">
        {/* PHONE */}
        <div className="auth-phone-input">
          <label>{t("Authentication.labels.phone")}</label>
          <div className="input-with-prefix">
            <span className="phone-prefix">{t("Authentication.prefix")}</span>
            <input
              type="tel"
              inputMode="numeric"
              value={phone}
              onChange={handlePhoneChange}
              maxLength={9}
              placeholder={t("Authentication.placeholders.phone")}
              className="custom-input"
            />
          </div>
        </div>

        {/* PASSWORD */}
        <div className="auth-password-input">
          <label>{t("Authentication.labels.password")}</label>
          <div className="input-with-icon">
            <input
              type={showPassword ? "text" : "password"}
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
                if (error.password)
                  setError((prev) => ({ ...prev, password: undefined }));
              }}
              placeholder={t("Authentication.placeholders.password")}
              className="custom-input"
            />
            <svg
              className={`input-icon ${showPassword ? "active" : ""}`}
              onClick={togglePassword}
              width="22"
              height="17"
              viewBox="0 0 22 17"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              style={{ cursor: "pointer" }}
            >
              <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M0.0689978 7.92177C1.803 3.47977 5.884 0.285767 11 0.285767C16.116 0.285767 20.197 3.47977 21.932 7.92177C22.0235 8.15581 22.0235 8.41572 21.932 8.64977C20.197 13.0918 16.116 16.2858 11 16.2858C5.884 16.2858 1.803 13.0918 0.0689978 8.64977C-0.0224703 8.41572 -0.0224703 8.15581 0.0689978 7.92177ZM11 11.2858C11.7956 11.2858 12.5587 10.9697 13.1213 10.4071C13.6839 9.84448 14 9.08142 14 8.28577C14 7.49012 13.6839 6.72706 13.1213 6.16445C12.5587 5.60184 11.7956 5.28577 11 5.28577C10.2043 5.28577 9.44129 5.60184 8.87868 6.16445C8.31607 6.72706 8 7.49012 8 8.28577C8 9.08142 8.31607 9.84448 8.87868 10.4071C9.44129 10.9697 10.2043 11.2858 11 11.2858Z"
                fill={showPassword ? "#F5A623" : "#183D69"}
              />
            </svg>
          </div>
        </div>

        {/* Forgot */}
        <div className="forgot-password">
          <Link to="/renew-password">{t("Authentication.forgotPassword")}</Link>
        </div>

        {/* Submit */}
        <button className="sign-in" onClick={handleLogin} disabled={isLoading}>
          {isLoading ? (
            <span className="spinner" />
          ) : (
            t("Authentication.buttons.signIn")
          )}
        </button>
      </div>

      <div className="sign-up-navigate">
        <p>
          {t("Authentication.signupPrompt")}{" "}
          <Link to="/register">{t("Authentication.buttons.signUp")}</Link>
        </p>
      </div>

      <div className="auth-separator">
        <hr />
        <p>{t("Authentication.separator")}</p>
        <hr />
      </div>

      <div className="alternative-servives-auth">
        <div className="google-auth">
          <a href="/">
            <img
              src={googleServiceIconUrl}
              alt={t("Authentication.social.googleAlt")}
            />
          </a>
          <p>{t("Authentication.social.google")}</p>
        </div>
        <div className="facebook-auth">
          <a href="/">
            <img
              src={facebookUrl}
              alt={t("Authentication.social.facebookAlt")}
            />
          </a>
          <p>{t("Authentication.social.facebook")}</p>
        </div>
      </div>
    </div>
  );
}
