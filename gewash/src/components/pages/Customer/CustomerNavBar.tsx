import { Link, useLocation } from "react-router-dom";
import { Calendar, Home, QrCode, ShoppingBag, UserRound } from "lucide-react";

const items = [
  { to: "/", label: "მთავარი", icon: Home, on: (p: string) => p === "/" },
  { to: "/customer-calendar", label: "კალენდარი", icon: Calendar, on: (p: string) => p.startsWith("/customer-calendar") },
  { to: "/customer-qr-page", label: "QR", icon: QrCode, on: (p: string) => p.startsWith("/customer-qr"), center: true },
  { to: "/shop", label: "მაღაზია", icon: ShoppingBag, on: (p: string) => p.startsWith("/shop") },
  { to: "/customer-my-data", label: "პროფილი", icon: UserRound, on: (p: string) => p.startsWith("/customer-my-data") },
];

export default function CustomerNavBar() {
  const { pathname } = useLocation();
  return (
    <div className="nav-bar-wrapper">
      <nav className="nav-bar-container" aria-label="GEWASH">
        {items.map((item) => {
          const Icon = item.icon;
          const active = item.on(pathname);
          if (item.center) {
            return (
              <div className="qr-nav-bar-item" key={item.to}>
                <Link to={item.to} className={active ? "qr-active" : ""} aria-label={item.label} aria-current={active ? "page" : undefined}>
                  <Icon size={26} strokeWidth={1.8} color="#2474C1" />
                </Link>
              </div>
            );
          }
          return (
            <Link key={item.to} to={item.to} className={active ? "active" : ""} aria-label={item.label} aria-current={active ? "page" : undefined}>
              <Icon size={22} strokeWidth={1.8} color={active ? "#2474C1" : "#8CA3B9"} />
            </Link>
          );
        })}
      </nav>
    </div>
  );
}
