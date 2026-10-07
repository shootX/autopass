import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import { customFetch } from "@/utils/customFetch";
import { loadDefaultBranches } from "@/hooks/fetchFilteredBranches";
import type { Branch } from "@/hooks/useFetchBranches";
import { formatKaDate, formatPhone, initials } from "@/lib/format";
import { CarFront, ChevronRight, LayoutGrid, Settings, ShoppingBag, UserRound } from "lucide-react";

type Row = {
  id: number;
  title: string;
  meta: string;
};

export default function ProfilePage() {
  const user = useSelector((s: RootState) => s.user.data);
  const [rows, setRows] = useState<Row[]>([]);

  useEffect(() => {
    let branches: Branch[] = [];
    const token = localStorage.getItem("access_token");
    Promise.all([
      loadDefaultBranches().catch(() => [] as Branch[]),
      customFetch(`${import.meta.env.VITE_API_URL}/myappointments`, {
        headers: { Authorization: `Bearer ${token}` },
      })
        .then((res) => (res.ok ? res.json() : { appointments: [] }))
        .catch(() => ({ appointments: [] })),
    ]).then(([list, data]) => {
      branches = list;
      const today = new Date().toISOString().slice(0, 10);
      const past = (data.appointments ?? [])
        .filter((row: { date?: string; approved?: number }) => (row.date ?? "") < today && row.approved !== 2)
        .sort((a: { date?: string }, b: { date?: string }) => String(b.date).localeCompare(String(a.date)))
        .slice(0, 6);
      setRows(
        past.map((row: { id: number; date?: string; services?: { name?: string }[]; car_wash_id?: number; washing?: { name?: string } }) => {
          const branch = row.washing?.name || branches.find((b) => b.id === row.car_wash_id)?.name || "";
          const when = row.date ? formatKaDate(row.date, "short") : "";
          return {
            id: row.id,
            title: row.services?.[0]?.name || "რეცხვა",
            meta: [branch, when].filter(Boolean).join(" · "),
          };
        }),
      );
    });
  }, []);

  const items = [
    { to: "/customer-my-data", label: "ჩემი მონაცემები", icon: UserRound },
    { to: "/customer-my-data#vehicles", label: "ჩემი ავტომობილები", icon: CarFront },
    { to: "/my-packages", label: "ჩემი პაკეტები", icon: LayoutGrid },
    { to: "/shop", label: "მაღაზია", icon: ShoppingBag },
    { to: "/settings", label: "პარამეტრები", icon: Settings },
  ];

  return (
    <div className="ap-profile">
      <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
        <div className="ap-avatar" style={{ width: 64, height: 64, fontSize: 21 }}>{initials(user?.firstName, user?.lastName)}</div>
        <div>
          <h1 className="ap-title" style={{ fontSize: 24 }}>{[user?.firstName, user?.lastName].filter(Boolean).join(" ") || "პროფილი"}</h1>
          <div style={{ marginTop: 2, color: "var(--ap-gray-600)", fontVariantNumeric: "tabular-nums" }}>{formatPhone(user?.phone)}</div>
        </div>
      </div>

      <div className="ap-menu" style={{ marginTop: 28 }}>
        {items.map((item) => {
          const Icon = item.icon;
          return (
            <Link key={item.label} to={item.to}>
              <Icon size={21} />
              {item.label}
              <ChevronRight className="ch" size={19} />
            </Link>
          );
        })}
      </div>

      {rows.length > 0 && (
        <div style={{ marginTop: 28 }}>
          <h2 style={{ fontFamily: "Noto Sans Georgian, sans-serif", fontSize: 18, fontWeight: 800, margin: "0 0 6px" }}>ბოლო რეცხვები</h2>
          {rows.map((row) => (
            <div className="ap-wash" key={row.id}>
              <div style={{ flex: 1 }}>
                <b>{row.title}</b>
                {row.meta && <small>{row.meta}</small>}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
