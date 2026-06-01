import cn from '../../utils/cn';

export default function GaugeChart({ value, max = 100, size = 140, label, className = '' }) {
  const pct = Math.min(value / max, 1);
  const radius = 54;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference * (1 - pct);

  const color = pct >= 0.8 ? '#2E7D32' : pct >= 0.5 ? '#F57F17' : '#C62828';

  return (
    <div className={cn('relative inline-flex items-center justify-center', className)}>
      <svg width={size} height={size} viewBox="0 0 120 120">
        <circle
          cx="60" cy="60" r={radius}
          fill="none"
          stroke="#E6E8EC"
          strokeWidth="8"
        />
        <circle
          cx="60" cy="60" r={radius}
          fill="none"
          stroke={color}
          strokeWidth="8"
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={offset}
          transform="rotate(-90 60 60)"
          className="transition-all duration-1000 ease-out"
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className="text-2xl font-bold font-sora text-primary">{Math.round(pct * 100)}%</span>
        {label && <span className="text-[10px] text-on-surface-variant -mt-0.5">{label}</span>}
      </div>
    </div>
  );
}
