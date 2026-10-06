import { useMemo, useState } from "react";
import logoUrl from "../assets/logo.svg";
import Spinner from "../components/Spinner";
import { login as loginRequest } from "../lib/authApi";

type Props = {
  onLoggedIn: (token: string) => void;
};

export default function LoginPage({ onLoggedIn }: Props) {
  const [loginValue, setLoginValue] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const togglePassword = () => setShowPassword((prev) => !prev);

  const canSubmit = useMemo(
    () => loginValue.trim().length > 0 && password.trim().length > 0 && !busy,
    [loginValue, password, busy],
  );

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!canSubmit) return;
    setError(null);
    setBusy(true);
    try {
      const { token } = await loginRequest(loginValue, password);
      onLoggedIn(token);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Sign in failed");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-wrapper">
        <div className="auth-logo-block">
          <img src={logoUrl} alt="Geocar" className="auth-logo-img" width={200} height={59} />
        </div>

        <div className="auth-greetings">
          <h1>Sign In</h1>
          <p>Please enter your details to create an account.</p>
        </div>

        <form className="auth-input-block" onSubmit={onSubmit}>
          {error ? (
            <div className="incorrect-password-error" role="alert">
              {error}
            </div>
          ) : null}
          <div className="auth-field">
            <label htmlFor="login">Login</label>
            <input
              id="login"
              value={loginValue}
              onChange={(e) => setLoginValue(e.target.value)}
              placeholder="Enter your login name"
              className="custom-input"
              autoComplete="username"
              disabled={busy}
            />
          </div>

          <div className="auth-field">
            <label htmlFor="password">Password</label>
            <div className="input-with-icon">
              <input
                id="password"
                type={showPassword ? "text" : "password"}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter your password"
                className="custom-input"
                autoComplete="current-password"
                disabled={busy}
              />

              <button
                type="button"
                className="pwd-toggle"
                onClick={togglePassword}
                aria-label={showPassword ? "Hide password" : "Show password"}
                disabled={busy}
              >
                {/* Same eye icon as main app `Authentication.tsx` */}
                <svg
                  className={`input-icon${showPassword ? " active" : ""}`}
                  width="22"
                  height="17"
                  viewBox="0 0 22 17"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                  aria-hidden
                >
                  <path
                    fillRule="evenodd"
                    clipRule="evenodd"
                    d="M0.0689978 7.92177C1.803 3.47977 5.884 0.285767 11 0.285767C16.116 0.285767 20.197 3.47977 21.932 7.92177C22.0235 8.15581 22.0235 8.41572 21.932 8.64977C20.197 13.0918 16.116 16.2858 11 16.2858C5.884 16.2858 1.803 13.0918 0.0689978 8.64977C-0.0224703 8.41572 -0.0224703 8.15581 0.0689978 7.92177ZM11 11.2858C11.7956 11.2858 12.5587 10.9697 13.1213 10.4071C13.6839 9.84448 14 9.08142 14 8.28577C14 7.49012 13.6839 6.72706 13.1213 6.16445C12.5587 5.60184 11.7956 5.28577 11 5.28577C10.2043 5.28577 9.44129 5.60184 8.87868 6.16445C8.31607 6.72706 8 7.49012 8 8.28577C8 9.08142 8.31607 9.84448 8.87868 10.4071C9.44129 10.9697 10.2043 11.2858 11 11.2858Z"
                    fill={showPassword ? "#F5A623" : "#183D69"}
                  />
                </svg>
              </button>
            </div>
          </div>

          <button className={`sign-in${busy ? " sign-in--busy" : ""}`} type="submit" disabled={!canSubmit || busy}>
            {busy ? <Spinner /> : "Sign In"}
          </button>
        </form>
      </div>
    </div>
  );
}
