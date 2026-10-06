import { Outlet, useLocation } from "react-router-dom";
import CustomerNavBar from "../../components/pages/Customer/CustomerNavBar";

export default function CustomerLayout() {
  const { pathname, state } = useLocation();

  const noFooterPaths = ["/register", "/auth", "/add-car"];
  const isAfterRegistration = state?.fromRegistration === true;

  const shouldShowNav = !noFooterPaths.includes(pathname) || (pathname === "/add-car" && !isAfterRegistration);

  return (
    <>
      <Outlet />
      {shouldShowNav && <CustomerNavBar />}
    </>
  );
}
