import cn from '../../utils/cn';

export default function BarChart({ data, bars, height = 200, showAxis = true, className = '' }) {
  if (!data || data.length === 0) return null;

  const maxValue = Math.max(...data.map(d => {
    if (typeof bars === 'string') return Number(d[bars]) || 0;
    return Math.max(...bars.map(b => Number(d[b.key]) || 0));
  }), 1);

  return (
    <div className={cn('flex items-end gap-1.5', className)} style={{ height }}>
      {data.map((item, i) => {
        const value = typeof bars === 'string' ? Number(item[bars]) || 0 : Number(item[bars[0]?.key]) || 0;
        const pct = (value / maxValue) * 100;
        const label = item.label || item.name || item.date || '';
        return (
          <div key={i} className="flex-1 flex flex-col items-center gap-1 h-full justify-end">
            <div
              className="w-full bg-gradient-to-t from-primary to-primary-container rounded-t-lg transition-all duration-500 hover:opacity-80 min-h-[4px]"
              style={{ height: `${Math.max(pct, 2)}%` }}
              title={`${label}: ${value}`}
            />
            {showAxis && (
              <span className="text-[9px] text-on-surface-variant truncate w-full text-center leading-tight">
                {label.length > 6 ? label.slice(0, 6) + '…' : label}
              </span>
            )}
          </div>
        );
      })}
    </div>
  );
}
