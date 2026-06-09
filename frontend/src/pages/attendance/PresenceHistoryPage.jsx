import { useState, useEffect } from 'react';
import { FiDownload, FiRefreshCw } from 'react-icons/fi';
import { useToastCtx } from '../../context/ToastContext';
import DataTable from '../../components/ui/DataTable';
import SearchInput from '../../components/ui/SearchInput';
import Badge from '../../components/ui/Badge';
import api from '../../api/axios';

const NIVEAUX = ['L1', 'L2', 'L3', 'M1', 'M2'];
const SEMESTRES = Array.from({ length: 10 }, (_, i) => ({ value: i + 1, label: `S${i + 1}` }));

const PresenceHistoryPage = () => {
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('all');
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const { addToast } = useToastCtx();

  // Filtres supplémentaires
  const [filieres, setFilieres] = useState([]);
  const [annees, setAnnees] = useState([]);
  const [filtreAnnee, setFiltreAnnee] = useState('');
  const [filtreFiliere, setFiltreFiliere] = useState('');
  const [filtreNiveau, setFiltreNiveau] = useState('');
  const [filtreSemestre, setFiltreSemestre] = useState('');
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');

  // Chargement initial des listes de filtres
  useEffect(() => {
    const init = async () => {
      try {
        const [filRes, anRes] = await Promise.all([
          api.get('/admin/filieres'),
          api.get('/admin/annees-academiques'),
        ]);
        setFilieres(filRes.data?.data ?? filRes.data ?? []);
        setAnnees(anRes.data?.data ?? anRes.data ?? []);
      } catch {
        // silencieux
      }
    };
    init();
  }, []);

  const fetchHistory = async () => {
    setLoading(true);
    try {
      const params = { page, per_page: 20 };
      if (search.trim()) params.search = search;
      if (filter !== 'all') params.statut = filter;
      if (filtreAnnee) params.annee_id = filtreAnnee;
      if (filtreFiliere) params.filiere_id = filtreFiliere;
      if (filtreNiveau) params.niveau = filtreNiveau;
      if (filtreSemestre) params.semestre = filtreSemestre;
      if (dateDebut) params.date_debut = dateDebut;
      if (dateFin) params.date_fin = dateFin;

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

  useEffect(() => { fetchHistory(); }, [page, search, filter, filtreAnnee, filtreFiliere, filtreNiveau, filtreSemestre, dateDebut, dateFin]);

  const resetFilters = () => {
    setFiltreAnnee('');
    setFiltreFiliere('');
    setFiltreNiveau('');
    setFiltreSemestre('');
    setDateDebut('');
    setDateFin('');
    setSearch('');
    setFilter('all');
    setPage(1);
  };

  const hasActiveFilters = filtreAnnee || filtreFiliere || filtreNiveau || filtreSemestre || dateDebut || dateFin;

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
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
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

      {/* Barre de filtres */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 mb-4">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[160px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Année académique</label>
            <select value={filtreAnnee} onChange={e => { setFiltreAnnee(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Toutes</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[160px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
            <select value={filtreFiliere} onChange={e => { setFiltreFiliere(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Toutes</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Niveau</label>
            <select value={filtreNiveau} onChange={e => { setFiltreNiveau(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Tous</option>
              {NIVEAUX.map(n => <option key={n} value={n}>{n}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Semestre</label>
            <select value={filtreSemestre} onChange={e => { setFiltreSemestre(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Tous</option>
              {SEMESTRES.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Date début</label>
            <input type="date" value={dateDebut} onChange={e => { setDateDebut(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20" />
          </div>
          <div className="space-y-1 min-w-[140px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Date fin</label>
            <input type="date" value={dateFin} onChange={e => { setDateFin(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20" />
          </div>
          {hasActiveFilters && (
            <div className="min-w-[100px]">
              <button onClick={resetFilters}
                className="w-full flex items-center justify-center gap-1.5 px-3 py-2 bg-surface-container-high text-on-surface-variant rounded-lg text-sm font-semibold hover:bg-surface-container-high/80 transition-all">
                <FiRefreshCw size={14} /> Réinitialiser
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Barre de recherche et statuts */}
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
