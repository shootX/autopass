import { Navigate, Outlet, useLocation } from "react-router-dom";

export function ProtectedRoute() {
  // В режиме верстки можно включить обход авторизации,
  // установив VITE_BYPASS_AUTH=true в .env
  const bypassAuth = import.meta.env.VITE_BYPASS_AUTH === "true";

  if (bypassAuth) {
    return <Outlet />;
  }

  const token = localStorage.getItem("access_token");
  const location = useLocation();

  const publicPaths = ["/auth", "/register", "/renew-password"];
  const isPublic = publicPaths.includes(location.pathname);

  if (!token && !isPublic) {
    return <Navigate to="/auth" replace />;
  }

  return <Outlet />;
}
