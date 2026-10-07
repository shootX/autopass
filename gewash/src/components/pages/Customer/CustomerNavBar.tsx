import { NavLink } from "react-router-dom";
import { Calendar, Home, MapPin, QrCode, UserRound } from "lucide-react";
import { useTranslation } from "@/hooks/useTranslation";

const items = [
  { to: "/", key: "home", icon: Home, end: true },
  { to: "/customer-calendar", key: "calendar", icon: Calendar },
  { to: "/customer-qr-page", key: "qr", icon: QrCode },
  { to: "/branches", key: "branches", icon: MapPin },
  { to: "/profile", key: "myData", icon: UserRound },
] as const;

export default function CustomerNavBar() {
  const t = useTranslation();

  return (
    <div className="nav-bar-wrapper v4-nav">
      <nav className="nav-bar-container" aria-label="autopass">
        {items.map((item) => {
          const Icon = item.icon;
          const label = t(`CustomerNavBar.routes.${item.key}`);
          return (
            <NavLink
              key={item.to}
              to={item.to}
              end={"end" in item ? item.end : undefined}
              className={({ isActive }) => `ap-tab${isActive ? " active" : ""}`}
              aria-label={label}
            >
              <span className="ap-tab-ico">
                <Icon size={22} strokeWidth={1.8} />
              </span>
              <span className="nl">{label}</span>
            </NavLink>
          );
        })}
      </nav>
    </div>
  );
}
