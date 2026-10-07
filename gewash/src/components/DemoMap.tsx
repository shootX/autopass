import { Droplet } from "lucide-react";
import type { Branch } from "@/hooks/useFetchBranches";

type DemoMapProps = {
  branches: Branch[];
  selectedBranchId: number | null;
  onSelect: (id: number) => void;
};

const BOUNDS = { minLat: 41.68, maxLat: 41.74, minLng: 44.74, maxLng: 44.82 };

function pinPosition(branch: Branch, index: number) {
  const lat = Number(branch.lat);
  const lng = Number(branch.lng);
  if (Number.isFinite(lat) && Number.isFinite(lng)) {
    const x = ((lng - BOUNDS.minLng) / (BOUNDS.maxLng - BOUNDS.minLng)) * 100;
    const y = ((BOUNDS.maxLat - lat) / (BOUNDS.maxLat - BOUNDS.minLat)) * 100;
    return {
      left: `${Math.min(86, Math.max(14, x))}%`,
      top: `${Math.min(78, Math.max(16, y))}%`,
    };
  }
  return { left: `${24 + (index % 3) * 22}%`, top: `${30 + Math.floor(index / 3) * 18}%` };
}

export function DemoMap({ branches, selectedBranchId, onSelect }: DemoMapProps) {
  return (
    <div className="ap-demo-map">
      <svg className="ap-demo-map-art" viewBox="0 0 800 1100" preserveAspectRatio="xMidYMid slice" aria-hidden>
        <rect width="800" height="1100" fill="#E4EDE6" />
        <path d="M-40 180C120 80 200 260 340 170C480 80 560 240 860 120" fill="none" stroke="#C9DDD4" strokeWidth="46" />
        <path d="M-20 640C140 560 250 760 420 680C590 600 640 820 840 740" fill="none" stroke="#D5E4DC" strokeWidth="34" />
        <rect x="70" y="210" width="150" height="90" rx="16" fill="#D3E4D6" />
        <rect x="470" y="360" width="180" height="110" rx="18" fill="#D7E7D4" />
        <rect x="180" y="820" width="140" height="80" rx="16" fill="#D3E4D6" />
        <g fill="none" stroke="#F7F9F4" strokeLinecap="round">
          <path d="M0 160H800" strokeWidth="14" />
          <path d="M0 320H800" strokeWidth="11" />
          <path d="M0 480H800" strokeWidth="16" />
          <path d="M0 660H800" strokeWidth="11" />
          <path d="M0 840H800" strokeWidth="14" />
          <path d="M0 1000H800" strokeWidth="10" />
          <path d="M120 0V1100" strokeWidth="12" />
          <path d="M280 0V1100" strokeWidth="16" />
          <path d="M460 0V1100" strokeWidth="11" />
          <path d="M640 0V1100" strokeWidth="14" />
        </g>
        <g fill="none" stroke="#F3F7F1" strokeWidth="4" strokeLinecap="round">
          <path d="M0 80H800M0 240H800M0 400H800M0 560H800M0 740H800M0 920H800" />
          <path d="M60 0V1100M200 0V1100M360 0V1100M540 0V1100M720 0V1100" />
        </g>
        <g fill="#14482F">
          <rect x="168" y="250" width="28" height="28" rx="8" />
          <rect x="560" y="430" width="26" height="26" rx="8" />
          <rect x="250" y="900" width="26" height="26" rx="8" />
        </g>
      </svg>
      <span className="ap-loc" style={{ left: "62%", top: "42%" }} />
      {branches.map((branch, index) => {
        const pos = pinPosition(branch, index);
        const selected = branch.id === Number(selectedBranchId);
        return (
          <button
            key={branch.id}
            type="button"
            className={`ap-pin${selected ? " sel" : ""}`}
            style={pos}
            aria-label={branch.name}
            onClick={() => onSelect(branch.id)}
          >
            <Droplet size={selected ? 18 : 12} fill="currentColor" />
          </button>
        );
      })}
    </div>
  );
}
