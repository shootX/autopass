import { createRoot } from 'react-dom/client'
import App from './App.tsx'
import "./index.css"
import "./styles/global.scss";

if (import.meta.env.VITE_BYPASS_AUTH === "true") {
  const { installPreviewFetch } = await import("./dev/previewFixtures");
  installPreviewFetch();
}

createRoot(document.getElementById('root')!).render(
    <App />
);