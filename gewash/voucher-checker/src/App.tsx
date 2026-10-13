import { useEffect, useState } from "react";
import "./App.css";
import LoginPage from "./pages/LoginPage";
import CodeVerificationPage from "./pages/CodeVerificationPage";
import PasswordChangePage from "./pages/PasswordChangePage";
import { clearToken, getToken, mustChangePassword, setMustChangePassword, setToken } from "./lib/session";

function App() {
  const [token, setTokenState] = useState<string | null>(null);
  const [mustChange, setMustChange] = useState(false);

  useEffect(() => {
    setTokenState(getToken());
    setMustChange(mustChangePassword());
  }, []);

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

  if (!token) return <LoginPage onLoggedIn={onLoggedIn} />;
  if (mustChange) return <PasswordChangePage onChanged={onChanged} onLogout={onLogout} />;
  return <CodeVerificationPage onLogout={onLogout} />;
}

export default App
