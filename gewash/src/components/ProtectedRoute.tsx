import { Navigate, Outlet, useLocation } from "react-router-dom";
import { loginPathForRole, readSessionRole } from "@/lib/session";
import { readAccessToken } from "@/lib/accessToken";
import { authBypassEnabled } from "@/lib/productionFlags";

export function ProtectedRoute() {
  const location = useLocation();
  const bypassAuth = authBypassEnabled(import.meta.env);

  if (bypassAuth) {
    return <Outlet />;
  }

  const token = readAccessToken();

  const publicPaths = ["/auth", "/manager-auth", "/register", "/renew-password", "/privacy", "/help", "/payment-success", "/payment/sandbox"];
  const isPublic = publicPaths.includes(location.pathname);

  if (!token && !isPublic) {
    return <Navigate to={loginPathForRole(readSessionRole())} replace />;
  }

  return <Outlet />;
}
