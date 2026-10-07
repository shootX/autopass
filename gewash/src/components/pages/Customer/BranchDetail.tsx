import { lazy, Suspense, useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { ArrowLeft } from "lucide-react";
import { loadDefaultBranches } from "@/hooks/fetchFilteredBranches";
import type { Branch } from "@/hooks/useFetchBranches";
import { formatHm, formatPhone } from "@/lib/format";
import { useTranslation } from "@/hooks/useTranslation";
import PageSkeleton from "@/components/Skeletons/PageSkeleton";

const BranchMap = lazy(() => import("../../BranchMap").then((m) => ({ default: m.BranchMap })));

function distanceKm(a: { lat: number; lng: number }, b: { lat: number; lng: number }) {
  const rad = (n: number) => (n * Math.PI) / 180;
  const dLat = rad(b.lat - a.lat);
  const dLng = rad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

export default function BranchDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const t = useTranslation();
  const [branches, setBranches] = useState<Branch[] | null>(null);
  const [here, setHere] = useState<{ lat: number; lng: number } | null>(null);
  const [serviceId, setServiceId] = useState<number | null>(null);

  useEffect(() => {
    loadDefaultBranches()
      .then(setBranches)
      .catch(() => setBranches([]));
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
      (pos) => setHere({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => undefined,
      { enableHighAccuracy: false, timeout: 4000 },
    );
  }, []);

  const branch = useMemo(() => branches?.find((b) => b.id === Number(id)) ?? null, [branches, id]);

  useEffect(() => {
    if (branch?.services?.length && serviceId == null) setServiceId(branch.services[0].id);
  }, [branch, serviceId]);

  if (!branches) return <PageSkeleton />;
  if (!branch) {
    return (
      <div className="ap-sheet">
        <button type="button" className="ap-back" onClick={() => navigate("/branches")} aria-label="უკან"><ArrowLeft /></button>
        <h1 className="ap-title">ფილიალი ვერ მოიძებნა</h1>
      </div>
    );
  }

  const open = formatHm(branch.workStart);
  const close = formatHm(branch.workEnd);
  const hours = open && close ? `${open}–${close}` : null;
  const km = here ? distanceKm(here, branch) : null;
  const phone = branch.phone || branch.manager?.phone;

  return (
    <div className="ap-detail">
      <div className="ap-detail-map">
        <Suspense fallback={<div className="branch-map-placeholder" />}>
          <BranchMap branches={[branch]} selectedBranchId={branch.id} onSelect={() => undefined} variant="strip" />
        </Suspense>
        <button type="button" className="ap-detail-back" onClick={() => navigate(-1)} aria-label="უკან">
          <ArrowLeft size={22} />
        </button>
      </div>
      <div className="ap-sheet">
        <div style={{ display: "flex", alignItems: "flex-start", gap: 12 }}>
          <h1 className="ap-title" style={{ flex: 1 }}>{branch.name}</h1>
          {branch.isOpen === true && <span className="ap-open">{t("BranchMap.panel.status.open")}</span>}
          {branch.isOpen === false && <span className="ap-closed">{t("BranchInfoPanel.status.close")}</span>}
        </div>
        <p className="ap-sub" style={{ marginTop: 6 }}>
          {branch.address}
          {km != null && <> · {km < 10 ? km.toFixed(1) : Math.round(km)} კმ</>}
        </p>
        {hours && <p style={{ marginTop: 4, color: "var(--ap-gray-600)", fontSize: 15 }}>{hours}</p>}
        {phone && (
          <p style={{ marginTop: 4 }}>
            <a className="ap-link" href={`tel:+${String(phone).replace(/\D/g, "")}`}>{formatPhone(phone)}</a>
          </p>
        )}
        {branch.services.length > 0 && (
          <>
            <h2 style={{ margin: "34px 0 6px", fontSize: 18, fontWeight: 800 }}>სერვისები</h2>
            {branch.services.map((service) => (
              <button key={service.id} type="button" className="ap-service" onClick={() => setServiceId(service.id)}>
                <span className={`ap-radio${serviceId === service.id ? " on" : ""}`} />
                <span><b>{service.name}</b></span>
              </button>
            ))}
          </>
        )}
      </div>
      <div className="ap-bar">
        <button
          type="button"
          className="ap-btn"
          onClick={() =>
            navigate("/wash-appointment", {
              state: { selectedBranchId: branch.id, serviceId },
            })
          }
        >
          სერვისზე ჩაწერა
        </button>
      </div>
    </div>
  );
}
