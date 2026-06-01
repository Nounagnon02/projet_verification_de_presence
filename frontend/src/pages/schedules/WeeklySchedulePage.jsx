import { useState, useEffect } from 'react';
import { FiChevronLeft, FiChevronRight, FiMapPin, FiLoader } from 'react-icons/fi';
import api from '../../api/axios';

const DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const HOURS = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00'];

const COLORS = [
  'bg-[#E3F2FD] border-l-4 border-[#1565C0] text-[#1565C0]',
  'bg-[#E8F5E9] border-l-4 border-[#2E7D32] text-[#2E7D32]',
  'bg-[#FFF8E1] border-l-4 border-[#F57F17] text-[#F57F17]',
  'bg-[#F3E5F5] border-l-4 border-[#7B1FA2] text-[#7B1FA2]',
  'bg-[#FFEBEE] border-l-4 border-[#C62828] text-[#C62828]',
  'bg-[#E0F7FA] border-l-4 border-[#00838F] text-[#00838F]',
];

function getWeekDateRange(offset) {
  const now = new Date();
  const day = now.getDay();
  const diff = now.getDate() - day + (day === 0 ? -6 : 1) + offset * 7;
  const monday = new Date(now.setDate(diff));
  const sunday = new Date(new Date(monday).setDate(monday.getDate() + 6));
  return {
    start: monday.toISOString().split('T')[0],
    end: sunday.toISOString().split('T')[0],
    dates: DAYS.map((_, i) => {
      const d = new Date(monday);
      d.setDate(monday.getDate() + i);
      return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
    }),
  };
}

function getHourIndex(time) {
  const h = parseInt(time?.split(':')[0] || '8', 10);
  return h - 8;
}

export default function WeeklySchedulePage() {
  const [weekOffset, setWeekOffset] = useState(0);
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const weekRange = getWeekDateRange(weekOffset);

  useEffect(() => {
    const fetchEvents = async () => {
      setLoading(true);
      try {
        const params = { date_debut: weekRange.start, date_fin: weekRange.end };
        const { data: res } = await api.get('/admin/evenements', { params });
        const list = res.data || res;
        setEvents(Array.isArray(list) ? list : []);
      } catch {
        setEvents([]);
      } finally {
        setLoading(false);
      }
    };
    fetchEvents();
  }, [weekOffset]);

  const mappedEvents = events.map((e, i) => {
    const date = new Date(e.date + 'T12:00:00');
    const dayIdx = (date.getDay() + 6) % 7;
    const startHour = getHourIndex(e.heure_debut);
    const endHour = getHourIndex(e.heure_fin) || startHour + 1;
    return {
      day: dayIdx,
      start: startHour,
      end: endHour,
      title: e.ec?.intitule || e.cours || 'Cours',
      room: e.salle || 'N/A',
      color: i % COLORS.length,
    };
  });

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Emploi du Temps</h1>
          <p className="text-sm text-on-surface-variant">Planning hebdomadaire des cours</p>
        </div>
        <div className="flex items-center gap-3">
          <button onClick={() => setWeekOffset(w => w - 1)} className="p-2 hover:bg-surface-container-high rounded-xl transition-colors">
            <FiChevronLeft />
          </button>
          <span className="text-sm font-semibold text-primary min-w-[140px] text-center">
            {weekOffset === 0 ? 'Cette semaine' : weekOffset === -1 ? 'Semaine dernière' : `S+${Math.abs(weekOffset)}`}
          </span>
          <button onClick={() => setWeekOffset(w => w + 1)} className="p-2 hover:bg-surface-container-high rounded-xl transition-colors">
            <FiChevronRight />
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-64">
          <FiLoader className="animate-spin text-primary w-8 h-8" />
        </div>
      ) : (
        <div className="bg-surface-container-lowest rounded-xxl shadow-sm overflow-hidden">
          <div className="grid grid-cols-[80px_repeat(6,1fr)] border-b border-outline-variant/10">
            <div className="p-3 text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider"></div>
            {DAYS.map((day, i) => (
              <div key={i} className="p-3 text-center">
                <p className="text-xs font-bold text-primary">{day}</p>
                <p className="text-[10px] text-on-surface-variant">{weekRange.dates[i]}</p>
              </div>
            ))}
          </div>

          <div className="grid grid-cols-[80px_repeat(6,1fr)] relative">
            <div>
              {HOURS.map((hour, i) => (
                <div key={i} className="h-[60px] border-b border-outline-variant/5 flex items-start justify-end pr-3 pt-1">
                  <span className="text-[10px] text-on-surface-variant font-mono">{hour}</span>
                </div>
              ))}
            </div>

            {DAYS.map((_, dayIdx) => (
              <div key={dayIdx} className="relative border-l border-outline-variant/5">
                {HOURS.map((_, hourIdx) => (
                  <div key={hourIdx} className="h-[60px] border-b border-outline-variant/5"></div>
                ))}

                {mappedEvents.filter(e => e.day === dayIdx).map((event, i) => (
                  <div
                    key={i}
                    className={`absolute left-0.5 right-0.5 ${COLORS[event.color]} rounded-lg p-2 overflow-hidden cursor-pointer hover:shadow-md transition-shadow z-10`}
                    style={{
                      top: `${(event.start / HOURS.length) * 100}%`,
                      height: `${((event.end - event.start) / HOURS.length) * 100}%`,
                    }}
                  >
                    <p className="text-[11px] font-bold leading-tight mb-0.5">{event.title}</p>
                    <p className="text-[9px] flex items-center gap-1 opacity-80">
                      <FiMapPin size={9} /> {event.room}
                    </p>
                  </div>
                ))}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
