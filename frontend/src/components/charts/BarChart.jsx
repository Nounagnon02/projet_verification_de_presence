import cn from '../../utils/cn';

export default function BarChart({ data, bars, height = 200, showAxis = true, className = '' }) {
  if (!data || data.length === 0) return null;

  const maxValue = Math.max(
    ...data.map((d) => {
      if (typeof bars === 'string') return Number(d[bars]) || 0;
      return Math.max(...bars.map((b) => Number(d[b.key]) || 0));
    }),
    1,
  );

  // Au-delà de 8 barres, un libellé sur « pas » (8 au plus) : 60 libellés
  // tronqués à 6 lettres ne se lisaient pas et élargissaient le graphique.
  const pas = data.length > 8 ? Math.ceil(data.length / 8) : 1;
  const valeurDe = (item) => (typeof bars === 'string' ? Number(item[bars]) || 0 : Number(item[bars[0]?.key]) || 0);
  const libelleDe = (item) => item.label || item.name || item.date || '';
  const resume = data.map((item) => `${libelleDe(item)} : ${valeurDe(item)}`).join(', ');

  return (
    <div
      className={cn('flex items-end', data.length > 24 ? 'gap-0.5' : 'gap-1.5', className)}
      style={{ height }}
      role="img"
      aria-label={`Diagramme en barres : ${resume}`}
    >
      {data.map((item, i) => {
        const value = valeurDe(item);
        const pct = (value / maxValue) * 100;
        const label = libelleDe(item);
        return (
          // min-w-0 : sans lui, chaque colonne flex-1 gardait la largeur de son
          // libellé et 60 barres débordaient de 1 700 px sur un téléphone.
          <div key={i} className="flex-1 min-w-0 flex flex-col items-center gap-1 h-full justify-end">
            <div
              className="w-full bg-gradient-to-t from-primary to-primary-container rounded-t-lg transition-all duration-500 hover:opacity-80 min-h-[4px]"
              style={{ height: `${Math.max(pct, 2)}%` }}
              title={`${label}: ${value}`}
            />
            {showAxis && (
              <span
                className="text-[9px] text-on-surface-variant w-full text-center leading-tight whitespace-nowrap"
                aria-hidden="true"
              >
                {i % pas === 0 ? (label.length > 6 ? label.slice(0, 6) + '…' : label) : ' '}
              </span>
            )}
          </div>
        );
      })}
    </div>
  );
}
