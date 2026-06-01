const RecentQRScans = ({ scans }) => {
  if (!scans || scans.length === 0) {
    return <p className="text-surface-variant">Aucun scan récent</p>;
  }

  return (
    <div className="space-y-4">
      {scans.map((scan, index) => (
        <div key={index} className={`flex items-center gap-4 group transition-transform hover:translate-x-1`}>
          <div className="w-8 h-8 rounded-full overflow-hidden flex-shrink-0">
            <img
              className="w-full h-full object-cover"
              alt={`Portrait of ${scan.name}`}
              src={scan.image}
            />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-xs font-bold text-primary truncate">{scan.name}</p>
            <p className="text-[10px] text-slate-500 truncate">{scan.course}</p>
          </div>
          <div className="text-right">
            <p className={`text-[10px] font-mono font-bold text-${scan.status === 'SUCCÈS' ? 'secondary' : scan.status === 'ÉCHEC' ? 'error' : 'secondary'}`}>
              {scan.status}
            </p>
            <p className="text-[9px] text-slate-400 font-mono">{scan.time}</p>
          </div>
        </div>
      ))}
    </div>
  );
};

export default RecentQRScans;