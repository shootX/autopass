import { CAR_TOP } from "@/lib/v4";

export function WashRing({ remaining, total }: { remaining: number; total: number }) {
  const r = 118;
  const c = 2 * Math.PI * r;
  const safeTotal = total > 0 ? total : 0;
  const ratio = safeTotal ? Math.max(0, Math.min(1, remaining / safeTotal)) : 0;
  const dots = Math.min(Math.max(safeTotal, 0), 16);
  const cx = 131;
  const cy = 131;

  return (
    <div className="v4-ring" aria-hidden>
      <svg viewBox="0 0 262 262">
        <circle cx="131" cy="131" r={r} fill="none" stroke="#EEF1EC" strokeWidth="16" />
        <circle
          cx="131"
          cy="131"
          r={r}
          fill="none"
          stroke="#B5DD3A"
          strokeWidth="16"
          strokeLinecap="round"
          strokeDasharray={`${ratio * c} ${c}`}
          transform="rotate(-90 131 131)"
        />
        <circle cx="131" cy="131" r="96" fill="#F6F9F1" />
        {Array.from({ length: dots }, (_, i) => {
          const angle = (i / dots) * Math.PI * 2 - Math.PI / 2;
          const on = i < Math.round(ratio * dots);
          return (
            <circle
              key={i}
              cx={cx + Math.cos(angle) * r}
              cy={cy + Math.sin(angle) * r}
              r="2.6"
              fill={on ? "#0E1712" : "#B8BFB6"}
            />
          );
        })}
      </svg>
      <img src={CAR_TOP} alt="" />
    </div>
  );
}
