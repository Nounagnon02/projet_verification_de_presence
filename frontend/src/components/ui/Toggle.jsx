import cn from '../../utils/cn';

export default function Toggle({ enabled, onChange, label, description }) {
  return (
    <label className="flex items-center justify-between py-3 cursor-pointer group">
      <div>
        {label && <p className="text-sm font-medium text-on-surface">{label}</p>}
        {description && <p className="text-xs text-on-surface-variant mt-0.5">{description}</p>}
      </div>
      <div className={cn(
        'relative w-11 h-6 rounded-full transition-colors ml-4 shrink-0',
        enabled ? 'bg-primary' : 'bg-surface-container-high',
      )}>
        <div className={cn(
          'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow-sm transition-transform',
          enabled ? 'translate-x-5' : 'translate-x-0',
        )} />
        <input type="checkbox" checked={enabled} onChange={onChange} className="sr-only" />
      </div>
    </label>
  );
}
