import { Navigate, Outlet, useLocation } from "react-router-dom";
import { loginPathForRole, readSessionRole } from "@/lib/session";

export function ProtectedRoute() {
  const location = useLocation();
  // В режиме верстки можно включить обход авторизации,
  // установив VITE_BYPASS_AUTH=true в .env
  const bypassAuth = import.meta.env.VITE_BYPASS_AUTH === "true";

  if (bypassAuth) {
    return <Outlet />;
  }

  const token = localStorage.getItem("access_token");

  const publicPaths = ["/auth", "/manager-auth", "/register", "/renew-password"];
  const isPublic = publicPaths.includes(location.pathname);

  if (!token && !isPublic) {
    return <Navigate to={loginPathForRole(readSessionRole())} replace />;
  }

  return <Outlet />;
}
