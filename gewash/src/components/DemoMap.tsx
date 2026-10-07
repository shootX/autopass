import { Droplet } from "lucide-react";
import type { Branch } from "@/hooks/useFetchBranches";
import { MAP_V4 } from "@/lib/v4";

type DemoMapProps = {
  branches: Branch[];
  selectedBranchId: number | null;
  onSelect: (id: number) => void;
};

const BOUNDS = { minLat: 41.68, maxLat: 41.74, minLng: 44.74, maxLng: 44.82 };

function pinPosition(branch: Branch, index: number) {
  const lat = Number(branch.lat);
  const lng = Number(branch.lng);
  if (Number.isFinite(lat) && Number.isFinite(lng) && !(lat === 0 && lng === 0)) {
    const x = ((lng - BOUNDS.minLng) / (BOUNDS.maxLng - BOUNDS.minLng)) * 100;
    const y = ((BOUNDS.maxLat - lat) / (BOUNDS.maxLat - BOUNDS.minLat)) * 100;
    return {
      left: `${Math.min(86, Math.max(14, x))}%`,
      top: `${Math.min(72, Math.max(22, y))}%`,
    };
  }
  return { left: `${22 + (index % 3) * 24}%`, top: `${28 + Math.floor(index / 3) * 16}%` };
}

export function DemoMap({ branches, selectedBranchId, onSelect }: DemoMapProps) {
  return (
    <div className="ap-demo-map" style={{ backgroundImage: `url(${MAP_V4})` }}>
      <span className="ap-loc" style={{ left: "58%", top: "46%" }} />
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
            <Droplet size={selected ? 18 : 13} fill="currentColor" />
          </button>
        );
      })}
      <span style={{ position: "absolute", right: 16, bottom: 280, fontSize: 9, color: "#8E958F", zIndex: 3 }}>© OpenStreetMap</span>
    </div>
  );
}
