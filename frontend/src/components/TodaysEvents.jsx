const TodaysEvents = ({ events }) => {
  if (!events || events.length === 0) {
    return <p className="text-surface-variant">Aucun événement prévu pour aujourd'hui</p>;
  }

  return (
    <div className="space-y-6">
      {events.map((event, index) => {
        const statusClasses = {
          Terminé: 'text-[10px] font-mono text-secondary-container bg-secondary-container px-2 py-0.5 rounded',
          'En cours': 'text-[10px] font-mono text-white bg-primary px-2 py-0.5 rounded',
          'À venir': 'text-[10px] font-mono text-slate-500 bg-surface-container-high px-2 py-0.5 rounded'
        };

        return (
          <div key={index} className={`relative pl-6 border-l-2 ${
            event.status === 'Terminé' ? 'border-secondary-container' :
            event.status === 'En cours' ? 'border-primary-fixed-dim' :
            'border-surface-container-high'
          }`}>
            <div className={`absolute -left-[9px] top-0 w-4 h-4 ${
              event.status === 'Terminé' ? 'bg-secondary-container' :
              event.status === 'En cours' ? 'bg-primary' :
              'bg-surface-container-high'
            } rounded-full border-4 border-white ${
              event.status === 'En cours' ? 'animate-pulse' : ''
            }`}></div>
            <div className="mb-2">
              <div className="flex justify-between items-center">
                <span className={statusClasses[event.status]}>
                  {event.status}
                </span>
                <span className="text-[10px] text-slate-400 font-mono">
                  {event.time}
                </span>
              </div>
              <p className="text-xs font-bold text-primary mt-1">{event.title}</p>
              <p className="text-[10px] text-slate-500">{event.location}</p>
              {event.progress && (
                <>
                  <div className="mt-3">
                    <div className="flex justify-between text-[9px] text-slate-400 mb-1">
                      <span>Progression</span>
                      <span>{event.progress}%</span>
                    </div>
                    <div className="h-1.5 w-full bg-surface-container-high rounded-full">
                      <div className="h-full bg-primary rounded-full" style={{ width: `${event.progress}%` }} />
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
};

export default TodaysEvents;