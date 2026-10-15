import { Outlet } from "react-router-dom";
import { readAccessToken } from "@/lib/accessToken";
import { authBypassEnabled, sandboxRouteEnabled } from "@/lib/productionFlags";
import { useUserRole } from "@/hooks/useUserRole";
import { useTranslation } from "@/hooks/useTranslation";
import ManagerLayout from "./Layouts/ManagerLayout";
import CustomerLayout from "./Layouts/CustomerLayout";
import NotFound from "./NotFound";

/** Logged-out help stays public. A signed-in user keeps the existing shell. */
export function HelpShell() {
  const token = readAccessToken();
  const bypass = authBypassEnabled(import.meta.env);
  const role = useUserRole();
  if (!token && !bypass) return <Outlet />;
  if (role === "manager") return <ManagerLayout />;
  return <CustomerLayout />;
}

/** Card entry stays on the server. This route only exists when test payment is configured. */
export function PaymentSandboxGate() {
  const t = useTranslation();
  if (!sandboxRouteEnabled(import.meta.env)) return <NotFound />;
  return (
    <main className="v4-screen">
      <h1>{t("Sandbox.title")}</h1>
      <p>{t("Sandbox.body")}</p>
    </main>
  );
}
