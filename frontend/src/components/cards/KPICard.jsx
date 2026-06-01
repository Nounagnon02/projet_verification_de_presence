
const KPICard = ({ label, value, change, icon, trend = 'neutral' }) => {
  const trendClass = trend === 'up' ? 'text-primary' : trend === 'down' ? 'text-error' : 'text-surface-variant';
  const trendIcon = trend === 'up' ? '↗' : trend === 'down' ? '↘' : '';

  return (
    <div className="bg-surface-container-lowest p-6 rounded-xxl shadow-sm border border-transparent hover:border-primary-fixed-dim transition-all">
      <div className="flex justify-between items-start mb-4">
        <div className="p-3 bg-primary/5 rounded-xl">
          {icon}
        </div>
        <span className={`text-[10px] font-bold text-secondary bg-secondary-container/30 px-2 py-1 rounded-full ${trendClass}`}>
          {change} {trendIcon}
        </span>
      </div>
      <p className="text-slate-500 text-xs font-medium uppercase tracking-wider mb-1">{label}</p>
      <p className="text-3xl font-bold font-sora text-primary">{value}</p>
    </div>
  );
};

export default KPICard;