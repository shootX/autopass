import { lazy, Suspense, useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useSelector } from "react-redux";
import type { RootState } from "@/store";
import { useFetchCars } from "@/hooks/useFetchCars";
import { useMyPackages } from "@/hooks/useActivePackages";
import { useTranslation } from "@/hooks/useTranslation";
import { customFetch } from "@/utils/customFetch";
import { loadDefaultBranches } from "@/hooks/fetchFilteredBranches";
import type { Branch } from "@/hooks/useFetchBranches";
import { formatKaDate, initials } from "@/lib/format";
import { bodyLabel, carParts, carPhoto, washCells } from "@/lib/v4";
import PageSkeleton from "@/components/Skeletons/PageSkeleton";
import { ArrowRight, Bell, CalendarCheck, CalendarDays, Car, CarFront, ChevronRight, Clock3, MapPin, Plus, QrCode, Search, Star } from "lucide-react";

const BranchMap = lazy(() => import("../BranchMap").then((m) => ({ default: m.BranchMap })));

type NextBooking = { label: string; branch?: string; rawDate: string; time: string };

export default function Home() {
  const user = useSelector((state: RootState) => state.user.data);
  const { cars, loading: carsLoading } = useFetchCars();
  const { packages, isLoading: packagesLoading } = useMyPackages();
  const t = useTranslation();
  const navigate = useNavigate();
  const [carIndex, setCarIndex] = useState(0);
  const [nextBooking, setNextBooking] = useState<NextBooking | null>(null);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);
  const [query, setQuery] = useState("");
  const [bookDate, setBookDate] = useState<Date | null>(null);
  const [bookTime, setBookTime] = useState("");

  const selectedCar = cars[carIndex] ?? null;
  const activePackage = selectedCar ? packages.find((p) => p.car.id === selectedCar.id) ?? null : null;
  const remaining = activePackage ? activePackage.number_of_washes - activePackage.used_washes : null;
  const total = activePackage?.number_of_washes ?? null;
  const daysLeft = activePackage?.end_date
    ? Math.max(0, Math.ceil((new Date(activePackage.end_date).getTime() - Date.now()) / 86400000))
    : null;
  const cells = washCells(total, remaining);
  const selectedParts = carParts(selectedCar);
  const carTitle = selectedParts.title;
  const name = user?.firstName || "";

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    customFetch(`${import.meta.env.VITE_API_URL}/myappointments`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then((res) => res.json())
      .then((data) => {
        const today = new Date().toISOString().slice(0, 10);
        const next = (data.appointments ?? [])
          .filter((row: { approved?: number; date?: string }) => row.approved !== 2 && (row.date ?? "") >= today)
          .sort((a: { date?: string; time?: string }, b: { date?: string; time?: string }) =>
            `${a.date}${a.time}`.localeCompare(`${b.date}${b.time}`),
          )[0];
        if (!next) return;
        const label = formatKaDate(next.date);
        const time = String(next.time ?? "").slice(0, 5);
        const branch =
          next.washing?.name || next.car_wash?.name || branches.find((b) => b.id === next.car_wash_id)?.name || "";
        setNextBooking({ label, branch, rawDate: next.date, time });
      })
      .catch(() => undefined);
  }, [branches]);

  useEffect(() => {
    loadDefaultBranches()
      .then((list) => {
        setBranches(list);
        setSelectedBranchId((id) => id ?? list[0]?.id ?? null);
      })
      .catch(() => undefined);
  }, []);

  if (carsLoading || packagesLoading) return <PageSkeleton />;

  const week = Array.from({ length: 7 }, (_, i) => {
    const date = new Date();
    date.setHours(0, 0, 0, 0);
    date.setDate(date.getDate() + i);
    return date;
  });
  const dayNames = ["კვ", "ორ", "სამ", "ოთხ", "ხუთ", "პარ", "შაბ"];
  const slots = ["10:00", "11:00", "12:30", "14:30", "15:00", "16:30"];
  const chosen = bookDate ?? week[0];
  const slotOff = (time: string) => {
    const [h, m] = time.split(":").map(Number);
    const slot = new Date(chosen);
    slot.setHours(h, m, 0, 0);
    return slot.getTime() < Date.now();
  };
  const branch = branches.find((b) => b.id === selectedBranchId) ?? branches[0];
  const todayLabel = formatKaDate(new Date(), "weekday");

  const goBook = () => {
    const date = chosen;
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    navigate("/wash-appointment", {
      state: {
        selectedBranchId: branch?.id,
        date: `${y}-${m}-${d}`,
        time: bookTime || undefined,
      },
    });
  };

  return (
    <div className="ap-home">
      <section className="v4-screen ap-home-m" style={{ paddingTop: 12 }}>
        <div className="v4-hello">
          <button type="button" className="av" onClick={() => navigate("/profile")} aria-label="პროფილი">
            {initials(user?.firstName, user?.lastName)}
          </button>
          <div style={{ flex: 1 }}>
            <div className="v4-kicker">{t("Home.greeting")}</div>
            <b>{name || t("Home.washes.title")}</b>
          </div>
          <button type="button" className="v4-ib" aria-label="შეტყობინებები" onClick={() => navigate("/messages")}>
            <Bell size={20} />
          </button>
        </div>

        {selectedCar && (
          <>
            <div className="v4-carname">
              <h2>{carTitle || selectedCar.plate}</h2>
              {selectedCar.plate && <span className="ap-plate"><b>GE</b><span>{selectedCar.plate}</span></span>}
            </div>
            <div className="v4-kicker" style={{ marginTop: 2 }}>
              {[bodyLabel(selectedParts.type), activePackage ? "აქტიური პაკეტი" : ""].filter(Boolean).join(" · ")}
            </div>
            <div className="v4-hero">
              <img src={carPhoto(selectedParts.type, selectedParts.image)} alt="" />
            </div>
          </>
        )}

        <div className="v4-ctiles" style={{ marginTop: selectedCar ? 8 : 22, gridTemplateColumns: `repeat(${Math.min(Math.max(cars.length + 1, 1), 3)}, minmax(0, 1fr))` }}>
          {cars.map((car, index) => {
            const parts = carParts(car);
            const on = index === carIndex;
            return (
              <button key={car.id} type="button" className={`v4-ct${on ? " on" : ""}`} onClick={() => setCarIndex(index)}>
                <span className="ci">{on ? <CarFront size={19} /> : <Car size={19} />}</span>
                {parts.short || car.plate}
              </button>
            );
          })}
          <button type="button" className="v4-ct add" onClick={() => navigate("/add-car")}>
            <Plus size={20} strokeWidth={2.4} />
            <span style={{ fontSize: 12 }}>დამატება</span>
          </button>
        </div>

        {activePackage && remaining != null ? (
          <div className="v4-stats">
            <div className="v4-st v4-dk">
              <div className="v4-kicker" style={{ color: "#A7B1AA" }}>დარჩენილი რეცხვა</div>
              <div className="n">{remaining}{total != null && <span> / {total}</span>}</div>
              {cells.length > 0 && (
                <div className="sq">{cells.map((on, i) => <i key={i} className={on ? "on" : ""} />)}</div>
              )}
            </div>
            <div className="v4-st">
              <div className="v4-kicker">პაკეტის ვადა</div>
              <div className="n">{daysLeft ?? "—"}{daysLeft != null && <span> დღე</span>}</div>
              {activePackage.end_date && (
                <div className="v4-kicker" style={{ marginTop: 8 }}>მდე {activePackage.end_date.slice(0, 10).split("-").reverse().join(".")}</div>
              )}
              <span className="v4-ib" style={{ position: "absolute", right: 14, top: 14, width: 32, height: 32, borderRadius: 10, background: "#fff" }}>
                <Clock3 size={17} />
              </span>
            </div>
          </div>
        ) : (
          <button type="button" className="ap-btn" style={{ marginTop: 16 }} onClick={() => navigate("/my-packages")}>
            შეარჩიე პაკეტი
          </button>
        )}

        <button type="button" className="v4-next" onClick={() => navigate("/customer-calendar")}>
          <span className="v4-ib" style={{ width: 40, height: 40, borderRadius: 12, background: "#fff" }}>
            <CalendarDays size={19} />
          </span>
          <span style={{ flex: 1 }}>
            <span className="v4-kicker">შემდეგი ჯავშანი{nextBooking?.branch ? ` · ${nextBooking.branch}` : ""}</span>
            <b>{nextBooking ? `${nextBooking.label} · ${nextBooking.time}` : "ჯავშანი ჯერ არ არის"}</b>
          </span>
          <ChevronRight size={20} color="#A2ABA4" />
        </button>

        <button type="button" className="ap-btn" style={{ marginTop: 12 }} onClick={() => navigate("/wash-appointment", { state: { selectedBranchId: branch?.id } })}>
          რეცხვის დაჯავშნა <ArrowRight size={20} strokeWidth={2.4} />
        </button>
      </section>

      <section className="ap-home-d">
        <div className="ap-d-top">
          <div className="hi">
            <small style={{ textTransform: "capitalize" }}>{todayLabel}</small>
            <b>{t("Home.greeting")}{name ? ` ${name}` : ""}</b>
          </div>
          <form
            className="ap-d-search"
            onSubmit={(e) => {
              e.preventDefault();
              navigate("/branches", { state: { query } });
            }}
          >
            <Search size={19} />
            <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="მოძებნე ფილიალი" aria-label="მოძებნე ფილიალი" />
            <kbd>⌘K</kbd>
          </form>
          <button type="button" className="ap-ib" aria-label="შეტყობინებები" onClick={() => navigate("/messages")}>
            <Bell size={21} />
          </button>
          <button type="button" className="ap-book-btn" onClick={goBook}>
            რეცხვის დაჯავშნა
            <span className="go"><ArrowRight size={18} /></span>
          </button>
        </div>

        <div className="ap-grid">
          <div className="ap-col">
            <div className="ap-pass-d">
              <div style={{ display: "flex", gap: 12 }}>
                <div style={{ flex: 1 }}>
                  <div className="tier">ჩემი პაკეტი</div>
                  <div className="car">
                    {carTitle || "ავტომობილი"}{" "}
                    {selectedCar?.plate && <span className="ap-plate" style={{ height: 24, verticalAlign: "middle" }}><b>GE</b><span>{selectedCar.plate}</span></span>}
                  </div>
                </div>
                <button type="button" className="ap-book-btn" style={{ height: 44 }} onClick={() => navigate("/customer-qr-page")}>
                  <QrCode size={18} /> QR კოდი
                </button>
              </div>
              {activePackage && remaining != null ? (
                <div className="big">
                  <div className="n">{remaining}{total != null && <span>/{total}</span>}</div>
                  <div style={{ fontSize: 14, color: "#C9D8CD", paddingBottom: 4, lineHeight: 1.3 }}>დარჩენილი<br />რეცხვა</div>
                </div>
              ) : (
                <button type="button" className="ap-btn" style={{ marginTop: 18, width: "auto", padding: "0 18px" }} onClick={() => navigate("/my-packages")}>
                  შეარჩიე პაკეტი
                </button>
              )}
              {cells.length > 0 && (
                <div className="ap-sq sq" style={{ marginTop: 18 }}>
                  {cells.map((on, i) => <i key={i} className={on ? "on" : ""} />)}
                </div>
              )}
              <div style={{ display: "flex", justifyContent: "space-between", marginTop: 12, fontSize: 13, color: "#A9BDB0" }}>
                {activePackage && <span>ავტომატური განახლება · <b style={{ color: "#fff" }}>{activePackage.renewal ? "ჩართ." : "გამორთ."}</b></span>}
                {daysLeft != null && <span>პაკეტის ვადა · <b style={{ color: "#fff" }}>{daysLeft}</b> დღე</span>}
              </div>
            </div>

            <div className="ap-stats">
              <button type="button" className="ap-stat" onClick={() => navigate("/customer-calendar")}>
                <span className="ic" style={{ background: "var(--ap-mint)", color: "var(--ap-forest)" }}><CalendarCheck size={19} /></span>
                <small>შემდეგი ჯავშანი</small>
                <b>{nextBooking ? `${nextBooking.label} · ${nextBooking.time}` : "არ არის"}</b>
              </button>
              <button type="button" className="ap-stat" onClick={() => navigate("/my-points")}>
                <span className="ic" style={{ background: "#FFF1D6", color: "#B87300" }}><Star size={19} /></span>
                <small>ჩემი ქულები</small>
                <b>{user?.points ?? 0} ქულა</b>
              </button>
              <button type="button" className="ap-stat" onClick={() => navigate("/customer-my-data")}>
                <span className="ic" style={{ background: "#E3EFFA", color: "var(--ap-info)" }}><CarFront size={19} /></span>
                <small>ავტომობილები</small>
                <b>{cars.length} ავტო</b>
              </button>
            </div>

            <div className="ap-card">
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", gap: 8 }}>
                <strong style={{ fontSize: 19 }}>სწრაფი ჩაწერა</strong>
                {branch && (
                  <span style={{ display: "inline-flex", alignItems: "center", gap: 6, background: "var(--ap-gray-100)", height: 32, padding: "0 12px", borderRadius: 10, fontSize: 13, color: "var(--ap-gray-600)" }}>
                    <MapPin size={14} />
                    {branch.name}
                  </span>
                )}
              </div>
              <div className="qweek" style={{ marginTop: 16, gridTemplateColumns: "repeat(7, 1fr)" }}>
                {week.map((date) => {
                  const on = chosen.toDateString() === date.toDateString();
                  return (
                    <button key={date.toISOString()} type="button" className={on ? "on" : ""} onClick={() => { setBookDate(date); setBookTime(""); }} style={{ background: on ? undefined : "#fff", border: "1.5px solid var(--ap-gray-200)" }}>
                      <small>{dayNames[date.getDay()]}</small>
                      {date.getDate()}
                    </button>
                  );
                })}
              </div>
              <div className="qtimes" style={{ marginTop: 12, gridTemplateColumns: "repeat(6, 1fr)" }}>
                {slots.map((time) => (
                  <button key={time} type="button" disabled={slotOff(time)} className={bookTime === time ? "on" : ""} onClick={() => setBookTime(time)} style={{ background: bookTime === time ? "var(--ap-lime)" : "#fff", color: "var(--ap-ink)", border: "1.5px solid var(--ap-gray-200)" }}>
                    {time}
                  </button>
                ))}
              </div>
              <div style={{ display: "flex", justifyContent: "flex-end", marginTop: 16 }}>
                <button type="button" className="ap-book-btn" onClick={goBook}>სერვისზე ჩაწერა <ArrowRight size={18} /></button>
              </div>
            </div>
          </div>

          <div className="ap-home-map">
            {branches.length > 0 && (
              <Suspense fallback={<div className="branch-map-placeholder" />}>
                <BranchMap branches={branches} selectedBranchId={selectedBranchId} onSelect={setSelectedBranchId} variant="panel" />
              </Suspense>
            )}
          </div>
        </div>
      </section>
    </div>
  );
}
