import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useFetchCars } from "@/hooks/useFetchCars";

export function CarCheckRoute() {
  const bypassAuth = import.meta.env.VITE_BYPASS_AUTH === "true";
  if (bypassAuth) {
    return <Outlet />;
  }

  const { cars, loading, error } = useFetchCars();
  const token = localStorage.getItem("access_token");
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
