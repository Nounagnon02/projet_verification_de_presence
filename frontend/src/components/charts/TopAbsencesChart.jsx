const TopAbsencesChart = ({ data }) => {
  if (!data || data.length === 0) {
    return (
      <div className="space-y-6">
        <div className="text-center py-8">
          <p className="text-surface-variant">Aucune donnée disponible</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {data.map((item, index) => (
        <div key={index} className="space-y-2">
          <div className="flex justify-between text-xs mb-1">
            <span className="font-semibold text-primary">{item.course}</span>
            <span className={`font-mono text-${item.color === 'error' ? 'error' : item.color === 'tertiary-container' ? 'on-tertiary-container' : 'slate-500'}`}>
              {item.percentage}% d'absences
            </span>
          </div>
          <div className="h-2 w-full bg-surface-container-high rounded-full overflow-hidden">
            <div className={`h-full bg-${item.color === 'error' ? 'error' : item.color === 'tertiary-container' ? 'tertiary-container' : 'slate-400'} rounded-full`} style={{ width: `${item.percentage}%` }} />
          </div>
        </div>
      ))}
    </div>
  );
};

export default TopAbsencesChart;