import { useState, useEffect } from 'react';
import { FiDownload } from 'react-icons/fi';
import { useToastCtx } from '../../context/ToastContext';
import DataTable from '../../components/ui/DataTable';
import SearchInput from '../../components/ui/SearchInput';
import Badge from '../../components/ui/Badge';
import api from '../../api/axios';

const PresenceHistoryPage = () => {
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('all');
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const { addToast } = useToastCtx();

  const fetchHistory = async () => {
    setLoading(true);
    try {
      const params = { page, per_page: 20 };
      if (search.trim()) params.search = search;
      if (filter !== 'all') params.statut = filter;
      const { data } = await api.get('/admin/presence/history', { params });
      if (data.success) {
        setRecords(data.data || []);
        setPagination(data.meta || null);
      } else {
        setRecords(data.data || []);
      }
    } catch {
      setRecords([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchHistory(); }, [page, search, filter]);

  const badgeVariant = { valide: 'success', absent: 'error', suspect: 'warning', en_retard: 'warning' };
  const badgeLabel = { valide: 'Présent', absent: 'Absent', suspect: 'Suspect', en_retard: 'Retard' };

  const columns = [
    {
      key: 'etudiant',
      label: 'Étudiant',
      sortable: true,
      render: (val) => val ? `${val.prenom || ''} ${val.nom || ''}`.trim() || '—' : '—',
    },
    { key: 'matricule', label: 'Matricule', className: 'hidden md:table-cell',
      render: (_, row) => row.etudiant?.matricule || '—' },
    { key: 'evenement', label: 'Cours', sortable: true,
      render: (val) => val?.cours || '—' },
    { key: 'date', label: 'Date', className: 'hidden lg:table-cell', sortable: true,
      render: (_, row) => row.heure_scan?.split(' ')[0] || row.evenement?.date || '—' },
    { key: 'heure', label: 'Heure', className: 'hidden sm:table-cell',
      render: (_, row) => row.heure_scan?.split(' ')[1] || '—' },
    {
      key: 'statut',
      label: 'Statut',
      render: (val) => <Badge variant={badgeVariant[val] || 'neutral'}>{badgeLabel[val] || val || '—'}</Badge>,
    },
  ];

  const mappedRecords = records.map(r => ({
    ...r,
    matricule: r.etudiant?.matricule,
    date: r.heure_scan?.split(' ')[0] || r.evenement?.date,
    cours: r.evenement?.cours,
  }));

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Historique des Présences</h1>
          <p className="text-sm text-on-surface-variant">Consultez l'historique complet des validations</p>
        </div>
        <button
          onClick={() => addToast?.('Export CSV en cours de développement', 'info')}
          className="flex items-center gap-2 px-4 py-2 bg-surface-container-low rounded-xl text-sm text-on-surface-variant hover:bg-surface-container-high transition-colors"
        >
          <FiDownload /> Exporter CSV
        </button>
      </div>

      <div className="flex flex-col md:flex-row gap-4 mb-6">
        <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Rechercher par nom ou matricule..." className="flex-1 max-w-md" />
        <div className="flex gap-2">
          {[['all', 'Tous'], ['valide', 'Présents'], ['absent', 'Absents'], ['suspect', 'Suspects']].map(([key, label]) => (
            <button key={key} onClick={() => { setFilter(key); setPage(1); }}
              className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${filter === key ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-primary'}`}>
              {label}
            </button>
          ))}
        </div>
      </div>

      <div className="bg-surface-container-lowest rounded-xxl shadow-sm border border-outline-variant/10 overflow-hidden">
        <DataTable
          columns={columns}
          data={mappedRecords}
          loading={loading}
          emptyMessage="Aucun enregistrement trouvé"
          pagination={pagination || null}
          onPageChange={setPage}
        />
      </div>
    </div>
  );
};

export default PresenceHistoryPage;
