import { useEffect } from "react";
import PrivacyPage from "./components/PrivacyPage";
import { privacyLang, shellRoute } from "./lib/shellRoute";

function storedLang(): string | null {
  try {
    return localStorage.getItem("lang");
  } catch {
    return null;
  }
}

/** Old panel addresses (bookmarks, home-screen icons) go to the landing page at "/". */
function Leave() {
  useEffect(() => {
    window.location.replace("/");
  }, []);
  return null;
}

export default function App() {
  const route = shellRoute(window.location.pathname);
  if (route === "privacy") {
    return <PrivacyPage lang={privacyLang(window.location.search, storedLang())} />;
  }
  if (route === "home") {
    return (
      <p style={{ padding: 24, fontFamily: "sans-serif" }}>
        Autopass: <a href="https://autopass.ge">autopass.ge</a>
      </p>
    );
  }
  return <Leave />;
}
