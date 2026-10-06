import React from 'react';

interface WorkloadGraphProps {
  graphData: Record<string, number> | null | undefined;
  svgWidth?: number;
  svgHeight?: number;
}

export const WorkloadGraph: React.FC<WorkloadGraphProps> = ({
  graphData,
  svgWidth = 300,
  svgHeight = 82,
}) => {
  if (!graphData || Object.keys(graphData).length === 0) {
    return (
      <div
        style={{
          height: '110px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          opacity: 0.5,
        }}
      >
        No graph for selected period
      </div>
    );
  }

  const formatGraphDate = (dateStr: string) => {
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const month = d.toLocaleString('en-US', { month: 'short' }).toUpperCase();
    return `${day}${month}`;
  };

  const points = Object.entries(graphData)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([date, count]) => ({
      date,
      label: formatGraphDate(date),
      count: Number(count),
    }));

  const paddingTop = 22;
  const paddingBottom = 6;
  const maxVal = Math.max(...points.map((p) => p.count), 1);

  const coords = points.map((p, i) => {
    const x = points.length > 1 ? (i / (points.length - 1)) * svgWidth : svgWidth / 2;
    const y =
      svgHeight - paddingBottom - (p.count / maxVal) * (svgHeight - paddingTop - paddingBottom);

    const xPct = points.length > 1 ? (i / (points.length - 1)) * 100 : 50;
    const yPct = (y / svgHeight) * 100;

    return { ...p, x, y, xPct, yPct };
  });

  const linePath = coords.map((c, i) => `${i === 0 ? 'M' : 'L'} ${c.x} ${c.y}`).join(' ');
  const areaPath = `${linePath} L ${coords[coords.length - 1].x} ${svgHeight} L ${coords[0].x} ${svgHeight} Z`;
  return (
    <>
      <div className="graph" style={{ width: '100%', height: '82px', position: 'relative' }}>
        <svg
          viewBox={`0 0 ${svgWidth} ${svgHeight}`}
          preserveAspectRatio="none"
          style={{ width: '100%', height: '100%', display: 'block' }}
        >
          <defs>
            <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#dce5f2" stopOpacity="0.8" />
              <stop offset="100%" stopColor="#eef3f9" stopOpacity="0.2" />
            </linearGradient>
          </defs>

          <path d={areaPath} fill="url(#chartGradient)" />
          <path d={linePath} fill="none" stroke="#b0c3de" strokeWidth="2" />
        </svg>

        <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none' }}>
          {coords.map((c, idx) => (
            <div
              key={idx}
              style={{
                position: 'absolute',
                left: `${c.xPct}%`,
                top: `${c.yPct}%`,
                transform: 'translate(-50%, -100%)',
                paddingBottom: '4px',
                fontSize: '11px',
                fontWeight: 'bold',
                color: '#1e293b',
                lineHeight: 1,
                whiteSpace: 'nowrap',
              }}
            >
              {c.count}
            </div>
          ))}
        </div>
      </div>

      <div className="graph-info" style={{ display: 'flex', justifyContent: 'space-between' }}>
        {coords.map((c, idx) => (
          <div key={idx} className="graph-info-item">
            {c.label}
          </div>
        ))}
      </div>
    </>
  );
};
