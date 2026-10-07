import { Outlet, useLocation } from "react-router-dom";
import CustomerNavBar from "../pages/Customer/CustomerNavBar";
import DesktopSidebar from "./DesktopSidebar";

export default function CustomerLayout() {
  const { pathname, state } = useLocation();
  const noFooterPaths = ["/register", "/auth", "/add-car"];
  const isAfterRegistration = state?.fromRegistration === true;
  const hideForFlow = /^\/branches\/[^/]+$/.test(pathname) || pathname === "/wash-appointment";
  const shouldShowNav =
    !hideForFlow && (!noFooterPaths.includes(pathname) || (pathname === "/add-car" && !isAfterRegistration));
  const bleed = pathname === "/branches" || /^\/branches\/[^/]+$/.test(pathname);

  return (
    <div className={`ap-shell${bleed ? " ap-shell-bleed" : ""}`}>
      <DesktopSidebar />
      <div className="ap-main">
        <Outlet />
      </div>
      {shouldShowNav && <CustomerNavBar />}
    </div>
  );
}
