import { useState } from 'react';
import { Link } from 'react-router-dom';
import { FiPlus, FiSearch, FiChevronRight, FiClock, FiMessageSquare } from 'react-icons/fi';
import Badge from '../../components/ui/Badge';
import useApi from '../../hooks/useApi';

const statusMap = { ouvert: 'success', en_cours: 'warning', resolu: 'info', ferme: 'neutral' };
const statusLabels = { ouvert: 'Ouvert', en_cours: 'En cours', resolu: 'Résolu', ferme: 'Fermé' };
const priorityColors = { basse: 'text-on-surface-variant', moyenne: 'text-primary', haute: 'text-warning', critique: 'text-error' };

export default function TicketsListPage() {
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('all');
  const params = {};
  if (filter !== 'all') params.status = filter;

  const { data: tickets, loading, pagination } = useApi('/admin/tickets', params);

  const ticketsList = Array.isArray(tickets) ? tickets : [];
  const filtered = ticketsList.filter((t) => {
    if (!search) return true;
    const q = search.toLowerCase();
    return t.subject?.toLowerCase().includes(q) || t.id?.toString().includes(q);
  });

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Mes Tickets</h1>
          <p className="text-sm text-on-surface-variant">{pagination?.total ?? 0} tickets au total</p>
        </div>
        <Link to="/support/contact" className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
          <FiPlus /> Nouveau ticket
        </Link>
      </div>

      <div className="flex flex-wrap items-center gap-3 mb-6">
        <div className="relative flex-1 max-w-md">
          <FiSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-outline" />
          <input className="w-full pl-10 pr-4 py-2.5 bg-surface-container-low rounded-xl border border-outline-variant/20 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
            placeholder="Rechercher un ticket..." value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
        <div className="flex gap-2">
          {[
            { key: 'all', label: 'Tous' },
            { key: 'ouvert', label: 'Ouverts' },
            { key: 'en_cours', label: 'En cours' },
            { key: 'resolu', label: 'Résolus' },
          ].map((f) => (
            <button key={f.key} onClick={() => setFilter(f.key)}
              className={`px-3 py-2 rounded-xl text-xs font-semibold transition-all ${filter === f.key ? 'bg-primary text-white' : 'bg-surface-container-high text-on-surface-variant hover:text-primary'}`}>
              {f.label}
            </button>
          ))}
        </div>
      </div>

      {loading ? (
        <div className="text-center py-12 text-on-surface-variant">Chargement...</div>
      ) : filtered.length === 0 ? (
        <div className="text-center py-12 bg-surface-container-lowest rounded-xxl border border-outline-variant/10">
          <FiMessageSquare className="text-3xl mx-auto mb-3 opacity-40 text-on-surface-variant" />
          <p className="text-sm text-on-surface-variant">Aucun ticket trouvé</p>
        </div>
      ) : (
        <div className="space-y-3">
          {filtered.map((ticket) => (
            <Link key={ticket.id} to={`/support/tickets/${ticket.id}`}
              className="flex items-start gap-4 bg-surface-container-lowest rounded-xxl p-5 shadow-sm border border-outline-variant/10 hover:shadow-md hover:border-primary/20 transition-all group">
              <div className={`w-2 h-2 rounded-full mt-2 shrink-0 ${priorityColors[ticket.priority] || 'text-primary'}`} style={{ backgroundColor: 'currentColor' }} />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-3 mb-1">
                  <span className="text-[10px] font-mono text-on-surface-variant">#{ticket.id}</span>
                  <Badge variant={statusMap[ticket.status] || 'neutral'}>{statusLabels[ticket.status] || ticket.status}</Badge>
                  {ticket.priority === 'critique' && <Badge variant="error">Critique</Badge>}
                  {ticket.priority === 'haute' && <Badge variant="warning">Haute</Badge>}
                </div>
                <h3 className="font-semibold text-sm text-primary group-hover:text-primary transition-colors">{ticket.subject}</h3>
                <div className="flex items-center gap-4 mt-2 text-[10px] text-on-surface-variant">
                  <span>{ticket.category}</span>
                  <span className="flex items-center gap-1"><FiClock size={12} />{ticket.created_at}</span>
                  <span className="flex items-center gap-1"><FiMessageSquare size={12} />{ticket.messages_count}</span>
                </div>
              </div>
              <FiChevronRight className="text-outline shrink-0 mt-2 group-hover:text-primary transition-colors" />
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
