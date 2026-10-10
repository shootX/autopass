import { NavLink } from "react-router-dom";
import { useEffect, useState } from "react";
import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import { logoDarkUrl } from "@/assets/staticUrls";
import { initials, formatPhone } from "@/lib/format";
import {
  Bell,
  House,
  LayoutGrid,
  LifeBuoy,
  MapPin,
  QrCode,
  Settings,
  ShoppingBag,
  Star,
} from "lucide-react";

const main = [
  { to: "/", label: "მთავარი", icon: House, end: true },
  { to: "/branches", label: "ფილიალები", icon: MapPin },
  { to: "/shop", label: "მაღაზია", icon: ShoppingBag },
  { to: "/customer-qr-page", label: "QR კოდი", icon: QrCode },
  { to: "/my-packages", label: "ჩემი პაკეტები", icon: LayoutGrid },
  { to: "/my-points", label: "ჩემი ქულები", icon: Star },
  { to: "/messages", label: "შეტყობინებები", icon: Bell },
];

export default function DesktopSidebar() {
  const user = useSelector((state: RootState) => state.user.data);
  const [promo, setPromo] = useState<{ title: string; description: string; url: string } | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const response = await fetch(`${import.meta.env.VITE_API_URL}/promo`);
        if (!response.ok) return;
        const data = await response.json();
        if (data.success && data.promo?.title) setPromo(data.promo);
      } catch {
        setPromo(null);
      }
    };
    void load();
  }, []);

  return (
    <aside className="ap-side">
      <a className="lg" href="/" aria-label="autopass">
        <img src={logoDarkUrl} alt="autopass" />
      </a>
      <nav>
        {main.map((item) => {
          const Icon = item.icon;
          return (
            <NavLink key={item.to} to={item.to} end={item.end} className={({ isActive }) => `item${isActive ? " active" : ""}`}>
              <Icon size={20} />
              {item.label}
            </NavLink>
          );
        })}
      </nav>
      <div className="sec">სხვა</div>
      <nav className="flat">
        <NavLink to="/settings" className={({ isActive }) => `item${isActive ? " active" : ""}`}>
          <Settings size={20} />
          პარამეტრები
        </NavLink>
        <NavLink to="/help" className={({ isActive }) => `item${isActive ? " active" : ""}`}>
          <LifeBuoy size={20} />
          დახმარება
        </NavLink>
      </nav>
      {promo && (
        <a className="promo" href={promo.url} target="_blank" rel="noreferrer">
          <b>{promo.title}</b>
          {promo.description && <p>{promo.description}</p>}
          <span className="go">გახსნა</span>
        </a>
      )}
      <NavLink to="/profile" className="usr">
        <span className="av">{initials(user?.firstName, user?.lastName)}</span>
        <span className="grow">
          <b style={{ display: "block", fontSize: 14 }}>{[user?.firstName, user?.lastName].filter(Boolean).join(" ") || "პროფილი"}</b>
          <small style={{ color: "#8E9A92", fontSize: 12 }}>{formatPhone(user?.phone)}</small>
        </span>
      </NavLink>
    </aside>
  );
}
