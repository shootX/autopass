import { useState } from "react";
import "./App.css";
import LoginPage from "./pages/LoginPage";
import CodeVerificationPage from "./pages/CodeVerificationPage";
import PasswordChangePage from "./pages/PasswordChangePage";
import { clearToken, getToken, mustChangePassword, setMustChangePassword, setToken } from "./lib/session";
import { partnerScreen } from "./lib/partnerGate";

function App() {
  const [token, setTokenState] = useState<string | null>(() => getToken());
  const [mustChange, setMustChange] = useState(() => mustChangePassword());

  function onLoggedIn(nextToken: string, changeRequired: boolean) {
    setToken(nextToken);
    setMustChangePassword(changeRequired);
    setTokenState(nextToken);
    setMustChange(changeRequired);
  }

  function onChanged(nextToken: string) {
    setToken(nextToken);
    setMustChangePassword(false);
    setTokenState(nextToken);
    setMustChange(false);
  }

  function onLogout() {
    clearToken();
    setTokenState(null);
    setMustChange(false);
  }

  function onMustChange() {
    setMustChangePassword(true);
    setMustChange(true);
  }

  const screen = partnerScreen(token, mustChange);
  if (screen === "login") return <LoginPage onLoggedIn={onLoggedIn} />;
  if (screen === "password") return <PasswordChangePage onChanged={onChanged} onLogout={onLogout} />;
  return <CodeVerificationPage onLogout={onLogout} onMustChange={onMustChange} />;
}

export default App
