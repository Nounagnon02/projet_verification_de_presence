import cn from '../../utils/cn';

export default function Tabs({ tabs, activeTab, onChange, className = '' }) {
  return (
    <div className={cn('flex gap-1 bg-surface-container-high p-1 rounded-xl', className)}>
      {tabs.map(tab => (
        <button
          key={tab.key || tab}
          onClick={() => onChange(tab.key || tab)}
          className={cn(
            'px-4 py-2 text-sm font-medium rounded-lg transition-all',
            (tab.key || tab) === activeTab
              ? 'bg-white text-primary shadow-sm'
              : 'text-on-surface-variant hover:text-on-surface',
          )}
        >
          {tab.label || tab}
        </button>
      ))}
    </div>
  );
}
