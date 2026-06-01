import cn from '../../utils/cn';

export default function ProgressBar({ value, max = 100, label, showValue = true, color = 'primary', size = 'md', className = '' }) {
  const pct = Math.min((value / max) * 100, 100);
  const colors = {
    primary: 'bg-primary',
    success: 'bg-[#2E7D32]',
    warning: 'bg-[#F57F17]',
    error: 'bg-[#C62828]',
  };
  const heights = { sm: 'h-1.5', md: 'h-2.5', lg: 'h-4' };

  return (
    <div className={cn('space-y-1', className)}>
      {(label || showValue) && (
        <div className="flex justify-between text-xs">
          {label && <span className="text-on-surface-variant">{label}</span>}
          {showValue && <span className="text-on-surface font-medium">{Math.round(pct)}%</span>}
        </div>
      )}
      <div className={cn('w-full bg-surface-container-high rounded-full overflow-hidden', heights[size])}>
        <div
          className={cn('h-full rounded-full transition-all duration-500', colors[color] || colors.primary)}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}
