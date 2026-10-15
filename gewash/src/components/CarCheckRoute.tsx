import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useFetchCars } from "@/hooks/useFetchCars";
import { useUserRole } from "@/hooks/useUserRole";
import { shouldCheckCars } from "@/lib/sessionRouting";
import { readAccessToken } from "@/lib/accessToken";
import { authBypassEnabled } from "@/lib/productionFlags";

export function CarCheckRoute() {
  const role = useUserRole();
  const bypassAuth = authBypassEnabled(import.meta.env);
  if (!shouldCheckCars(role, bypassAuth)) {
    return <Outlet />;
  }
  return <CustomerCarCheck />;
}

function CustomerCarCheck() {
  const { cars, loading, error } = useFetchCars();
  const token = readAccessToken();
  const location = useLocation();

  const publicPaths = ["/auth", "/register", "/add-car"];
  const isPublic = publicPaths.includes(location.pathname);

  if (isPublic) return <Outlet />;

  if (!token) return <Navigate to="/auth" replace />;

  if (loading) return null;

  if (error) {
    console.error(error);
    // Если API сейчас недоступно, не редиректим на add-car,
    // иначе пользователь "застревает" на одной странице.
    return <Outlet />;
  }

  if (cars.length === 0) {
    return <Navigate to="/add-car" replace />;
  }

  return <Outlet />;
}
