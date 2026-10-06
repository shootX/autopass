import React, { useEffect, useState } from 'react';

interface PercentBarProps {
  percent: number;
  label: string;
}

const PercentBar: React.FC<PercentBarProps> = ({ percent, label }) => {
  const [animatedPercent, setAnimatedPercent] = useState(0);

  useEffect(() => {
    const timeout = setTimeout(() => {
      setAnimatedPercent(percent);
    }, 100);

    return () => clearTimeout(timeout);
  }, [percent]);

  return (
    <div>
      <div className="pcent-bar">
        <div className="filledBar" style={{ width: `${animatedPercent}%` }}></div>
      </div>
      <div className="bottom-bar">
        <p className="yellow">{percent}%</p>
        <p>{label}</p>
        <span>-</span>
      </div>
    </div>
  );
};

export default PercentBar;
