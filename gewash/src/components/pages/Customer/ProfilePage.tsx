import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import { customFetch } from "@/utils/customFetch";
import { formatPhone, initials } from "@/lib/format";
import { useFetchCars } from "@/hooks/useFetchCars";
import { useMyPackages } from "@/hooks/useActivePackages";
import { useTranslation } from "@/hooks/useTranslation";
import { Bell, CarFront, ChevronRight, Settings, Share2, ShoppingBag, Star, Ticket } from "lucide-react";

async function loadReferralLink(): Promise<string | null> {
  const token = localStorage.getItem("access_token");
  if (!token) return null;
  try {
    const res = await customFetch(`${import.meta.env.VITE_API_URL}/me/get-referral-link`, {
      headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
    });
    if (!res.ok) return null;
    const data = (await res.json()) as Record<string, unknown>;
    const nested = data.data && typeof data.data === "object" ? (data.data as Record<string, unknown>) : null;
    const link =
      (typeof data.referral_link === "string" && data.referral_link) ||
      (typeof data.referralLink === "string" && data.referralLink) ||
      (typeof data.link === "string" && data.link) ||
      (typeof nested?.referral_link === "string" && nested.referral_link) ||
      "";
    return link || null;
  } catch {
    return null;
  }
}

export default function ProfilePage() {
  const navigate = useNavigate();
  const t = useTranslation();
  const user = useSelector((s: RootState) => s.user.data);
  const { cars } = useFetchCars();
  const { packages } = useMyPackages();
  const [shareNote, setShareNote] = useState("");
  const today = new Date().toISOString().slice(0, 10);
  const active = packages.some((pkg) => (pkg.end_date ?? "") >= today);

  const share = async () => {
    const link = await loadReferralLink();
    if (!link) {
      setShareNote("ბმული ვერ ჩაიტვირთა");
      return;
    }
    setShareNote("");
    if (navigator.share) {
      try {
        await navigator.share({ url: link });
        return;
      } catch {
        /* cancelled or unsupported */
      }
    }
    try {
      await navigator.clipboard.writeText(link);
      setShareNote("ბმული დაკოპირდა");
    } catch {
      setShareNote(link);
    }
  };

  const items = [
    { to: "/customer-my-data#vehicles", label: t("MyVehicles.title"), icon: CarFront, value: cars.length ? String(cars.length) : "" },
    { to: "/my-packages", label: t("MyPackages.header.title"), icon: Ticket, value: active ? t("MyPackages.tabs.active") : "" },
    { to: "/shop", label: t("Shop.header.title"), icon: ShoppingBag, value: "" },
    { to: "/my-points", label: t("MyPoints.header.title"), icon: Star, value: "" },
    { to: "/customer-my-data#notifications", label: t("NotificationSettings.title"), icon: Bell, value: "" },
  ];

  return (
    <div className="v4-screen ap-profile">
      <div className="v4-profile-top">
        <button type="button" className="av" onClick={() => navigate("/customer-my-data")}>
          {initials(user?.firstName, user?.lastName)}
        </button>
        <div style={{ flex: 1 }}>
          <h1>{[user?.firstName, user?.lastName].filter(Boolean).join(" ") || t("CustomerMyData.header.title")}</h1>
          <div className="v4-kicker" style={{ marginTop: 2 }}>{formatPhone(user?.phone)}</div>
        </div>
        <button type="button" className="v4-ib" aria-label="პარამეტრები" onClick={() => navigate("/settings")}>
          <Settings size={20} />
        </button>
      </div>

      <div className="v4-balance v4-dk">
        <div className="v4-kicker" style={{ color: "#A7B1AA" }}>{t("MyPoints.balance.label")}</div>
        <div style={{ marginTop: 4 }}>
          <span className="n">{user?.points ?? 0}</span>
          <span className="unit">{t("MyPoints.balance.unit")}</span>
        </div>
        <span className="v4-limeico" style={{ position: "absolute", right: 16, top: 16 }}>
          <Star size={20} />
        </span>
        <div className="foot">
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 12, color: "#A7B1AA" }}>{t("MyPoints.referrals.label")}</div>
            <div style={{ fontWeight: 800 }}>
              {user?.referralsCount ?? 0} {t("MyPoints.referrals.unit")}
            </div>
          </div>
          <button type="button" className="v4-share" onClick={() => void share()}>
            <Share2 size={16} />
            ბმულის გაზიარება
          </button>
        </div>
        {shareNote && <div style={{ marginTop: 8, fontSize: 12, color: "#D7E3D4" }}>{shareNote}</div>}
      </div>

      <div className="v4-menu">
        {items.map((item) => {
          const Icon = item.icon;
          return (
            <Link key={item.to} to={item.to}>
              <span className="ii"><Icon size={20} /></span>
              <span style={{ flex: 1 }}>{item.label}</span>
              {item.value && <span className="v">{item.value}</span>}
              <ChevronRight size={18} color="#A2ABA4" />
            </Link>
          );
        })}
      </div>
    </div>
  );
}
