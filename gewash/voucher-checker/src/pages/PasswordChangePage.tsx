import { useState } from "react";
import logoUrl from "../assets/logo.svg";
import Spinner from "../components/Spinner";
import { changePassword } from "../lib/authApi";

type Props = {
  onChanged: (token: string) => void;
  onLogout: () => void;
};

export default function PasswordChangePage({ onChanged, onLogout }: Props) {
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (password.length < 8) {
      setError("Password must be at least 8 characters");
      return;
    }
    if (password !== confirm) {
      setError("Passwords do not match");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const token = await changePassword(password);
      onChanged(token);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Password change failed");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-wrapper">
        <div className="auth-logo-block">
          <img src={logoUrl} alt="autopass" className="auth-logo-img" style={{ height: 32, width: "auto" }} />
        </div>
        <div className="auth-greetings">
          <h1>New password</h1>
          <p>Replace the temporary password before continuing.</p>
        </div>
        <form className="auth-input-block" onSubmit={onSubmit}>
          {error ? <div className="incorrect-password-error" role="alert">{error}</div> : null}
          <div className="auth-field">
            <label htmlFor="new-password">New password</label>
            <input id="new-password" type="password" className="custom-input" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="new-password" />
          </div>
          <div className="auth-field">
            <label htmlFor="confirm-password">Repeat password</label>
            <input id="confirm-password" type="password" className="custom-input" value={confirm} onChange={(e) => setConfirm(e.target.value)} autoComplete="new-password" />
          </div>
          <button className="sign-in" type="submit" disabled={busy}>
            {busy ? <Spinner /> : "Save password"}
          </button>
          <button className="sign-in" type="button" onClick={onLogout} disabled={busy}>Log out</button>
        </form>
      </div>
    </div>
  );
}
