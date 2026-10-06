import { useEffect, useState } from "react";
import "./App.css";
import LoginPage from "./pages/LoginPage";
import CodeVerificationPage from "./pages/CodeVerificationPage";
import { clearToken, getToken, setToken } from "./lib/session";

function App() {
  const [token, setTokenState] = useState<string | null>(null);

  useEffect(() => {
    setTokenState(getToken());
  }, []);

  function onLoggedIn(nextToken: string) {
    setToken(nextToken);
    setTokenState(nextToken);
  }

  function onLogout() {
    clearToken();
    setTokenState(null);
  }

  return token ? <CodeVerificationPage onLogout={onLogout} /> : <LoginPage onLoggedIn={onLoggedIn} />;
}

export default App
