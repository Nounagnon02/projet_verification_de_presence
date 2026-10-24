const KPICard = ({ label, value, change, icon, trend = 'neutral' }) => {
  // « neutre » : une information, pas une tendance. text-surface-variant, pâle
  // sur le fond de la pastille, la rendait illisible.
  const trendClass =
    trend === 'up' ? 'text-primary' : trend === 'down' ? 'text-statut-erreur' : 'text-on-surface-variant';
  const trendIcon = trend === 'up' ? '↗' : trend === 'down' ? '↘' : '';

  return (
    <div className="bg-surface-container-lowest p-5 sm:p-6 rounded-xxl shadow-sm border border-outline-variant/20 hover:border-primary/30 transition-all">
      <div className="flex justify-between items-start mb-4">
        <div className="p-3 bg-primary/5 rounded-xl">{icon}</div>
        {change && (
          <span className={`text-[10px] font-bold bg-surface-container-high px-2 py-1 rounded-full ${trendClass}`}>
            {change} {trendIcon}
          </span>
        )}
      </div>
      <p className="text-on-surface-variant text-xs font-medium uppercase tracking-wider mb-1">{label}</p>
      <p className="text-3xl font-bold font-sora text-primary">{value}</p>
    </div>
  );
};

export default KPICard;
