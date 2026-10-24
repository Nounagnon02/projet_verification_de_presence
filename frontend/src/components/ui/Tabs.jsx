import cn from '../../utils/cn';

// Onglets internes à une page (le contenu change sans changer d'adresse) :
// role tablist/tab et aria-selected disent lequel est actif. overflow-x-auto :
// trois libellés débordaient de 15 px sur un écran de 390 px.
export default function Tabs({ tabs, activeTab, onChange, className = '' }) {
  return (
    <div
      role="tablist"
      className={cn('flex max-w-full gap-1 overflow-x-auto bg-surface-container-high p-1 rounded-xl', className)}
    >
      {tabs.map((tab) => {
        const cle = tab.key || tab;
        const actif = cle === activeTab;
        return (
          <button
            type="button"
            role="tab"
            aria-selected={actif}
            key={cle}
            onClick={() => onChange(cle)}
            className={cn(
              'shrink-0 whitespace-nowrap px-4 py-2 text-sm font-medium rounded-lg transition-all',
              actif
                ? 'bg-surface-container-lowest text-primary shadow-sm'
                : 'text-on-surface-variant hover:text-on-surface',
            )}
          >
            {tab.label || tab}
          </button>
        );
      })}
    </div>
  );
}
