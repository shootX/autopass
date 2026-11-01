import { createRoot } from "react-dom/client";
import App from "./App.tsx";

// The removed web panel registered /sw.js. Drop it so old installs stop serving the panel.
if ("serviceWorker" in navigator) {
  void navigator.serviceWorker
    .getRegistrations()
    .then((all) => Promise.all(all.map((reg) => reg.unregister())))
    .catch((error: unknown) => console.error("Service worker cleanup failed:", error));
}

createRoot(document.getElementById("root")!).render(<App />);
