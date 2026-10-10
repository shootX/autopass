import React, { useState, useEffect } from "react"; // Добавили useEffect
import OTPVerification from "../../OTPVerification";
import { Link, useNavigate } from "react-router-dom";
import { customFetch } from "@/utils/customFetch";
import { fetchUserData } from "@/lib/fetchUserData";
import { useDispatch } from "react-redux";
const API_URL = import.meta.env.VITE_API_URL;
import { useTranslation } from "@/hooks/useTranslation";
import { logoUrl, googleServiceIconUrl, facebookUrl } from "@/assets/staticUrls";

async function generateHash(string: string) {
  const msgUint8 = new TextEncoder().encode(string);
  const hashBuffer = await crypto.subtle.digest('SHA-256', msgUint8);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  return hashHex;
}

const getHash = async (): Promise<string> => {
  try {
    // 1. Получаем IP
    const response = await fetch('https://back.geocar.ge/api/ref/ip');
    const data = await response.json();
    const myIp = data.ip;

    // 2. Собираем системные метрики (fingerprint)
    const fingerprintData = [
      myIp,
      navigator.language,
      screen.width,
      screen.height,
      screen.colorDepth,
      window.devicePixelRatio,
      navigator.maxTouchPoints,
      navigator.hardwareConcurrency ?? '',
      (navigator as any).deviceMemory ?? '' // кастим к any, так как в стандартных типах TS deviceMemory может отсутствовать
    ].join('|');

    // 3. Генерируем и возвращаем хэш
    const hash = await generateHash(fingerprintData);
    return hash;

  } catch (error) {
    console.error("Ошибка при генерации хэша:", error);
    return ""; // Возвращаем пустую строку в случае падения
  }
};

export default function Registration() {
  const t = useTranslation();
  const [phone, setPhone] = useState("");
  const [error, setError] = useState<{ phone?: string; password?: string }>({});
  const [stage, setStage] = useState<"register" | "verify" | "set-password">("register");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [referralCode, setReferralCode] = useState<string | null>(null); // Состояние для хранения кода
  const dispatch = useDispatch();

  const togglePassword = () => setShowPassword((prev) => !prev);
  const [tempCode, setTempCode] = useState<number | null>(null);
  const navigate = useNavigate();

  // Запрос при открытии страницы (монтировании компонента)
  useEffect(() => {
    const checkHash = async () => {
      try {
        // Ждем, пока сгенерируется хэш (ведь там внутри fetch и криптография)
        const hash = await getHash();

        if (!hash) return; // Если хэш пустой (ошибка сети), то и слать бэку нечего

        const res = await fetch(`${API_URL}/ref/check-hash`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
          },
          body: JSON.stringify({ hash }),
        });

        const data = await res.json();
        if (data.success && data.code) {
          setReferralCode(data.code);
        }
      } catch (err) {
        console.error("Ошибка при проверке хэша:", err);
      }
    };

    checkHash();
  }, []);

  const handlePhoneChange = (e: React.ChangeEvent<HTMLInputElement>): void => {
    let raw = e.target.value.replace(/[^\d]/g, "").slice(0, 9);
    setPhone(raw);
    if (error.phone) setError((prev) => ({ ...prev, phone: undefined }));
  };

  const handleRegister = async () => {
    const fullPhone = "995" + phone;

    if (!phone) {
      setError({ phone: "empty" });
      return;
    }

    //check privacy checked
    const privacyChecked = document.getElementById("accept-policy") as HTMLInputElement;
    if (!privacyChecked.checked) {
      setError({ phone: "privacy" });
      return;
    }

    try {
      const res = await fetch(`${API_URL}/register`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        // Передаем телефон и referralCode (если он null, то уйдет null или можно убрать, но для API обычно null устраивает)
        body: JSON.stringify({
          phone: fullPhone,
          code: referralCode
        }),
      });

      const data = await res.json();

      if (data.success) {
        setTempCode(data.temp_code);
        setStage("verify");
      } else {
        const serverPhoneError = data.error?.phone?.[0] || "";

        if (serverPhoneError.includes("уже существует")) {
          setError({ phone: "duplicate" });
        } else if (serverPhoneError.includes("Неверный формат")) {
          setError({ phone: "invalid-format" });
        } else {
          setError({ phone: "unknown" });
        }
      }
    } catch (err) {
      console.error("Ошибка запроса:", err);
    }
  };

  const handleOTPVerified = () => {
    setStage("set-password");
  };

  const handleVerifyOTP = async (code: string) => {
    try {
      const res = await fetch(`${API_URL}/register/verify`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ temp_code: tempCode, code }),
      });

      const data = await res.json();

      if (data.success && data.access_token) {
        localStorage.setItem("access_token", data.access_token);
        handleOTPVerified();
      } else {
        throw new Error("Invalid code");
      }
    } catch (err) {
      console.error("Ошибка верификации:", err);
      throw err;
    }
  };

  const handleFinishRegistration = async () => {
    if (password.length < 6) {
      setError({ password: "too-short" });
      return;
    }

    try {
      const res = await customFetch(`${API_URL}/register/set_password`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("access_token")}`,
        },
        body: JSON.stringify({
          password,
          temp_code: tempCode,
        }),
      });

      const data = await res.json();
      if (data.success) {
        await fetchUserData(dispatch);
        navigate("/add-car");
      } else {
        setError({ password: "server-error" });
      }
    } catch (err) {
      console.error("Ошибка сети:", err);
      setError({ password: "network" });
    }
  };


  if (stage === "verify" && tempCode) {
    return <OTPVerification onVerify={handleVerifyOTP} phone={phone} step="2 / 3" />;
  }

  if (stage === "set-password") {
    return (
      <div className="auth-wrapper">
        <div className="auth-logo-block">
          <img src={logoUrl} alt="LOGO" />
        </div>

        <div className="auth-greetings">
          <h1>{t("Registration.stage.register.title")}</h1>
          <p>{t("Registration.stage.register.subtitle")}</p>
        </div>

        {error.password === "too-short" && (
          <div className="incorrect-password-error">
            <p>{t("Registration.stage.setPassword.title.errors.tooShort")}</p>
          </div>
        )}

        <div className="auth-input-block">
          <div className="auth-password-input">
            <label>{t("Registration.stage.setPassword.label")}</label>
            <div className="input-with-icon">
              <input
                type={showPassword ? "text" : "password"}
                value={password}
                onChange={(e) => {
                  setPassword(e.target.value);
                  if (error.password) setError((prev) => ({ ...prev, password: undefined }));
                }}
                placeholder="Create your password"
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
                  fill={showPassword ? "#8DB523" : "#14482F"}
                />
              </svg>
            </div>
          </div>

          <button className="sign-in" onClick={handleFinishRegistration}>{t("Registration.stage.setPassword.finish")}</button>
        </div>
      </div>
    );
  }

  return (
    <div className='auth-wrapper'>
      <div className='auth-logo-block'>
        <img src={logoUrl} alt='LOGO' />
      </div>

      <div className='auth-greetings'>
        <h1>{t("Registration.stage.register.title")}</h1>
        <p>{t("Registration.stage.register.subtitle")}</p>
      </div>

      {error.phone === "empty" && (
        <div className='incorrect-password-error'>
          <p>{t("Registration.stage.register.errors.empty")}</p>
        </div>
      )}
      
      {error.phone === "privacy" && (
        <div className='incorrect-password-error'>
          <p>{t("Registration.stage.register.errors.privacy")}</p>
        </div>
      )}

      {error.phone === "duplicate" && (
        <div className='incorrect-password-error'>
          <p>{t("Registration.stage.register.errors.duplicate")}</p>
        </div>
      )}

      {error.phone === "invalid-format" && (
        <div className='incorrect-password-error'>
          <p>{t("Registration.stage.register.errors.invalidFormat")}</p>
        </div>
      )}

      <div className='auth-input-block'>
        <div className='auth-phone-input'>
          <label>{t("Registration.stage.register.label")}</label>
          <div className='input-with-prefix'>
            <span className='phone-prefix'>+995</span>
            <input
              type='tel'
              inputMode='numeric'
              value={phone}
              onChange={handlePhoneChange}
              maxLength={9}
              placeholder='555112233'
              className='custom-input'
            />
          </div>
        </div>

        {/* Accept policy */}

        <div className='auth-accept-policy'>
          <input
            type='checkbox'
            id='accept-policy'
          />
          <label htmlFor='accept-policy'>{t("Registration.stage.policy")}</label> <Link className="ap-link" to='/privacy'>{t("Registration.stage.policyLink")}</Link>
        </div>

        <button className='sign-in' onClick={handleRegister}>
          {t("Registration.stage.register.button")}
        </button>
      </div>

      <div className='sign-up-navigate'>
        <p>
          {t("Registration.stage.register.navigate.text")}
          <Link to='/auth'>
            {" "}
            {t("Registration.stage.register.navigate.link")}
          </Link>
        </p>
      </div>

      <div className='auth-separator'>
        <hr />
        <p>OR</p>
        <hr />
      </div>

      <div className='alternative-servives-auth'>
        <div className='google-auth'>
          <a href='/'>
            <img
              src={googleServiceIconUrl}
              alt='google'
            />
          </a>
          <p>Google</p>
        </div>
        <div className='facebook-auth'>
          <a href='/'>
            <img src={facebookUrl} alt='facebook' />
          </a>
          <p>Facebook</p>
        </div>
      </div>
    </div>
  );
}