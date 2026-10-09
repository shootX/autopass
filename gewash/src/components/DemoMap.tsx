import { Droplet } from "lucide-react";
import type { Branch } from "@/hooks/useFetchBranches";
import { MAP_V4 } from "@/lib/v4";

type DemoMapProps = {
  branches: Branch[];
  selectedBranchId: number | null;
  onSelect: (id: number) => void;
};

function hasCoords(branch: Branch) {
  const lat = Number(branch.lat);
  const lng = Number(branch.lng);
  return Number.isFinite(lat) && Number.isFinite(lng) && !(lat === 0 && lng === 0);
}

/** Fit every pin into the map area that sits above the bottom card. */
function pinPositions(branches: Branch[]) {
  const known = branches.filter(hasCoords);
  const lats = known.map((b) => Number(b.lat));
  const lngs = known.map((b) => Number(b.lng));
  const latSpan = known.length ? Math.max(Math.max(...lats) - Math.min(...lats), 0.004) : 0.012;
  const lngSpan = known.length ? Math.max(Math.max(...lngs) - Math.min(...lngs), 0.004) : 0.012;
  const minLat = (known.length ? Math.min(...lats) : 41.69) - latSpan * 0.55;
  const maxLat = (known.length ? Math.max(...lats) : 41.74) + latSpan * 0.55;
  const minLng = (known.length ? Math.min(...lngs) : 44.74) - lngSpan * 0.4;
  const maxLng = (known.length ? Math.max(...lngs) : 44.82) + lngSpan * 0.4;

  return branches.map((branch, index) => {
    if (!hasCoords(branch)) {
      const col = index % 3;
      const row = Math.floor(index / 3);
      return { left: `${18 + col * 28}%`, top: `${22 + row * 26}%` };
    }
    const x = ((Number(branch.lng) - minLng) / (maxLng - minLng)) * 100;
    const y = ((maxLat - Number(branch.lat)) / (maxLat - minLat)) * 100;
    return {
      left: `${Math.min(88, Math.max(12, x))}%`,
      top: `${Math.min(84, Math.max(16, y))}%`,
    };
  });
}

export function DemoMap({ branches, selectedBranchId, onSelect }: DemoMapProps) {
  const positions = pinPositions(branches);
  return (
    <div className="ap-demo-map" style={{ backgroundImage: `url(${MAP_V4})` }}>
      <div className="ap-demo-frame">
        <span className="ap-loc" style={{ left: "78%", top: "72%" }} />
        {branches.map((branch, index) => {
          const selected = branch.id === Number(selectedBranchId);
          return (
            <button
              key={branch.id}
              type="button"
              className={`ap-pin${selected ? " sel" : ""}`}
              style={positions[index]}
              aria-label={branch.name}
              onClick={() => onSelect(branch.id)}
            >
              <Droplet size={selected ? 18 : 13} fill="currentColor" />
            </button>
          );
        })}
        <span className="ap-demo-credit">© OpenStreetMap</span>
      </div>
    </div>
  );
}
