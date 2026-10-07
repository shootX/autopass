import { NavLink } from "react-router-dom";
import { Calendar, Home, QrCode, UserRound } from "lucide-react";

const items = [
  { to: "/", label: "მთავარი", icon: Home, end: true },
  { to: "/customer-calendar", label: "კალენდარი", icon: Calendar },
  { to: "/customer-qr-page", label: "QR", icon: QrCode, center: true },
  { to: "/profile", label: "პროფილი", icon: UserRound },
];

export default function CustomerNavBar() {
  return (
    <div className="nav-bar-wrapper">
      <nav className="nav-bar-container" aria-label="autopass">
        {items.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) => `ap-tab${item.center ? " qr" : ""}${isActive ? " active" : ""}`}
              aria-label={item.label}
            >
              <span className="ap-tab-ico">
                <Icon size={item.center ? 18 : 22} strokeWidth={1.8} />
              </span>
              <span>{item.label}</span>
            </NavLink>
          );
        })}
      </nav>
    </div>
  );
}
