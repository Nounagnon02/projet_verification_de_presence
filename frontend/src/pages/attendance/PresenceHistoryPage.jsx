import { useState, useEffect, useRef } from 'react';
import { FiDownload, FiRefreshCw, FiChevronDown, FiInfo } from 'react-icons/fi';
import { Link } from 'react-router-dom';
import { useToastCtx } from '../../context/ToastContext';
import DataTable from '../../components/ui/DataTable';
import SearchInput from '../../components/ui/SearchInput';
import Badge from '../../components/ui/Badge';
import api from '../../api/axios';
import { enregistrer, nomFichierServeur } from '../../utils/telechargement';
import useFiltresAcademiques from '../../hooks/useFiltresAcademiques';


const PresenceHistoryPage = () => {
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState('all');
  const [page, setPage] = useState(1);
  // Tri demandé au serveur : il porte sur toute la sélection, pas sur la page.
  const [tri, setTri] = useState({ champ: 'date', sens: 'desc' });
  const [pagination, setPagination] = useState(null);
  const { addToast } = useToastCtx();

  // Filtres supplémentaires
  // Filtres académiques en cascade : l'année restreint les filières, qui
  // restreignent niveaux et semestres. Le hook porte aussi la remise à zéro
  // des filtres devenus impossibles.
  const filtres = useFiltresAcademiques({ onChangement: () => setPage(1) });
  const { annees, filieres } = filtres;
  const [dateDebut, setDateDebut] = useState('');
  const [dateFin, setDateFin] = useState('');
  const [exportMenuOpen, setExportMenuOpen] = useState(false);
  const [exporting, setExporting] = useState(false);
  const exportRef = useRef(null);


  // Chargement intégré à l'effet, son unique appelant, et annulable : neuf
  // filtres pilotent cette liste, et deux changements rapprochés faisaient
  // partir deux requêtes dont l'ordre de retour n'était pas garanti.
  useEffect(() => {
    let annule = false;

    (async () => {
      setLoading(true);
      try {
        const params = { page, per_page: 20 };
        if (search.trim()) params.search = search;
        if (filter !== 'all') params.statut = filter;
        if (filtres.annee) params.annee_id = filtres.annee;
        if (filtres.filiere) params.filiere_id = filtres.filiere;
        if (filtres.niveau) params.niveau = filtres.niveau;
        if (filtres.semestre) params.semestre = filtres.semestre;
        if (dateDebut) params.date_debut = dateDebut;
        if (dateFin) params.date_fin = dateFin;
        params.tri = tri.champ;
        params.sens = tri.sens;

        const { data } = await api.get('/admin/presence/history', { params });
        if (data.success) {
          if (!annule) setRecords(data.data || []);
          if (!annule) setPagination(data.meta || null);
        } else {
          if (!annule) setRecords(data.data || []);
        }
      } catch {
        if (!annule) setRecords([]);
      } finally {
        if (!annule) setLoading(false);
      }
  
    })();

    return () => { annule = true; };
  }, [page, search, filter, filtres.annee, filtres.filiere, filtres.niveau, filtres.semestre, dateDebut, dateFin, tri.champ, tri.sens]);

  const resetFilters = () => {
    // Remettre l'année à zéro suffit : le hook en cascade vide filière,
    // niveau et semestre.
    filtres.setAnnee('');
    setDateDebut('');
    setDateFin('');
    setSearch('');
    setFilter('all');
    setPage(1);
  };

  const hasActiveFilters = filtres.annee || filtres.filiere || filtres.niveau || filtres.semestre || dateDebut || dateFin;

  // Fermer le menu d'export si on clique ailleurs
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (exportRef.current && !exportRef.current.contains(e.target)) {
        setExportMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleExport = async (format) => {
    setExportMenuOpen(false);
    setExporting(true);
    try {
      const params = {};
      if (search.trim()) params.search = search;
      if (filter !== 'all') params.statut = filter;
      if (filtres.annee) params.annee_id = filtres.annee;
      if (filtres.filiere) params.filiere_id = filtres.filiere;
      if (filtres.niveau) params.niveau = filtres.niveau;
      if (filtres.semestre) params.semestre = filtres.semestre;
      if (dateDebut) params.date_debut = dateDebut;
      if (dateFin) params.date_fin = dateFin;
      params.tri = tri.champ;
      params.sens = tri.sens;
      params.format = format;

      const { data, headers } = await api.get('/admin/presence/export', {
        params,
        responseType: 'blob',
      });

      const ext = format === 'pdf' ? 'pdf' : format === 'xlsx' ? 'xlsx' : 'csv';
      // Le nom donné par le serveur résume les filtres appliqués.
      enregistrer(data, nomFichierServeur(headers, `historique_presences.${ext}`));
      addToast?.('Export terminé avec succès', 'success');
    } catch {
      addToast?.("Erreur lors de l'export", 'error');
    } finally {
      setExporting(false);
    }
  };

  const badgeVariant = { valide: 'success', suspect: 'warning', rejete: 'error', absent: 'error', en_retard: 'warning' };
  const badgeLabel = { valide: 'Présent', suspect: 'Suspect', rejete: 'Rejeté', absent: 'Absent', en_retard: 'Retard' };

  // Colonne -> critère de tri accepté par le serveur. Les en-têtes affichaient
  // une flèche de tri, mais aucun gestionnaire n'était branché.
  const CRITERES_TRI = { etudiant: 'etudiant', evenement: 'cours', date: 'date' };
  const colonneTriee = Object.keys(CRITERES_TRI).find((cle) => CRITERES_TRI[cle] === tri.champ);
  const trier = (cle) => {
    const champ = CRITERES_TRI[cle];
    if (!champ) return;
    setTri((t) => ({ champ, sens: t.champ === champ && t.sens === 'asc' ? 'desc' : 'asc' }));
    setPage(1);
  };

  const formaterDate = (iso) => (iso ? iso.split('-').reverse().join('/') : '—');

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
      render: (val) => (
        <div>
          <p>{val?.cours || '—'}</p>
          {val?.heure_debut && val?.heure_fin && (
            <p className="text-[11px] text-on-surface-variant">Séance {val.heure_debut} – {val.heure_fin}</p>
          )}
        </div>
      ) },
    { key: 'date', label: 'Date', className: 'hidden lg:table-cell', sortable: true,
      render: (_, row) => formaterDate(row.heure_scan?.split(' ')[0] || row.evenement?.date) },
    { key: 'heure', label: 'Heure', className: 'hidden sm:table-cell',
      render: (_, row) => row.heure_scan?.split(' ')[1]?.slice(0, 5) || '—' },
    {
      key: 'statut',
      label: 'Statut',
      render: (val) => <Badge variant={badgeVariant[val] || 'neutral'}>{badgeLabel[val] || val || '—'}</Badge>,
    },
    {
      // Scan, décision après examen, saisie manuelle ou rattrapage — et, pour
      // toute décision de l'administration, qui l'a prise et pourquoi.
      key: 'origine',
      label: 'Origine',
      render: (val) => (
        <div className="min-w-[140px] max-w-[220px]">
          <p className="text-xs text-on-surface">{val?.libelle || 'Scan'}</p>
          {val?.decide_par && <p className="text-[11px] text-on-surface-variant">par {val.decide_par}</p>}
          {val?.motif && <p className="text-[11px] text-on-surface-variant italic truncate" title={val.motif}>« {val.motif} »</p>}
        </div>
      ),
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
        <div ref={exportRef} className="relative">
          <button
            onClick={() => setExportMenuOpen(prev => !prev)}
            disabled={exporting}
            className="flex items-center gap-2 px-4 py-2 bg-surface-container-low rounded-xl text-sm text-on-surface-variant hover:bg-surface-container-high transition-colors disabled:opacity-50"
          >
            {exporting ? (
              <FiRefreshCw className="animate-spin" size={16} />
            ) : (
              <FiDownload size={16} />
            )}
            {exporting ? 'Export en cours...' : 'Exporter'}
            <FiChevronDown size={14} className={`transition-transform ${exportMenuOpen ? 'rotate-180' : ''}`} />
          </button>
          {exportMenuOpen && (
            <div className="absolute right-0 mt-2 w-48 bg-surface-container-lowest rounded-xl shadow-xl border border-outline-variant/10 overflow-hidden z-50">
              <button onClick={() => handleExport('csv')}
                className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface hover:bg-surface-container-high transition-colors text-left">
                <span className="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center text-xs font-bold">CSV</span>
                <div>
                  <p className="font-medium">Fichier CSV</p>
                  <p className="text-[10px] text-on-surface-variant">Tableur (texte)</p>
                </div>
              </button>
              <button onClick={() => handleExport('xlsx')}
                className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface hover:bg-surface-container-high transition-colors text-left border-t border-outline-variant/5">
                <span className="w-7 h-7 rounded-lg bg-success-container text-on-success-container flex items-center justify-center text-xs font-bold">XLSX</span>
                <div>
                  <p className="font-medium">Fichier Excel</p>
                  <p className="text-[10px] text-on-surface-variant">Tableur (formaté)</p>
                </div>
              </button>
              <button onClick={() => handleExport('pdf')}
                className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface hover:bg-surface-container-high transition-colors text-left border-t border-outline-variant/5">
                <span className="w-7 h-7 rounded-lg bg-error/10 text-error flex items-center justify-center text-xs font-bold">PDF</span>
                <div>
                  <p className="font-medium">Fichier PDF</p>
                  <p className="text-[10px] text-on-surface-variant">Document imprimable</p>
                </div>
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Barre de filtres */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 mb-4">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[160px] flex-1">
            <label htmlFor="historique-annee" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Année académique</label>
            <select id="historique-annee" value={filtres.annee} onChange={e => filtres.setAnnee(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Toutes</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[160px] flex-1">
            <label htmlFor="historique-filiere" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
            <select id="historique-filiere" value={filtres.filiere} onChange={e => filtres.setFiliere(e.target.value)}
              disabled={filtres.anneeVide} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
              <option value="">{filtres.anneeVide ? 'Aucune filière cette année' : 'Toutes'}</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px] flex-1">
            <label htmlFor="historique-niveau" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Niveau</label>
            <select id="historique-niveau" value={filtres.niveau} onChange={e => filtres.setNiveau(e.target.value)}
              disabled={filtres.niveaux.length === 0} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
              <option value="">Tous</option>
              {filtres.niveaux.map(n => <option key={n} value={n}>{n}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px] flex-1">
            <label htmlFor="historique-semestre" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Semestre</label>
            <select id="historique-semestre" value={filtres.semestre} onChange={e => filtres.setSemestre(e.target.value)}
              disabled={filtres.semestres.length === 0} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
              <option value="">Tous</option>
              {filtres.semestres.map(s => <option key={s} value={s}>S{s}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px] flex-1">
            <label htmlFor="historique-date-debut" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Date début</label>
            <input id="historique-date-debut" type="date" value={dateDebut} onChange={e => { setDateDebut(e.target.value); setPage(1); }}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20" />
          </div>
          <div className="space-y-1 min-w-[140px] flex-1">
            <label htmlFor="historique-date-fin" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Date fin</label>
            <input id="historique-date-fin" type="date" value={dateFin} onChange={e => { setDateFin(e.target.value); setPage(1); }}
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
      <div className="flex flex-col md:flex-row gap-4 mb-2">
        <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Rechercher par nom ou matricule..." className="flex-1 max-w-md" />
        <div className="flex gap-2">
          {[['all', 'Tous'], ['valide', 'Présents'], ['suspect', 'Suspects'], ['rejete', 'Rejetés']].map(([key, label]) => (
            <button key={key} onClick={() => { setFilter(key); setPage(1); }}
              className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${filter === key ? 'bg-primary text-on-primary shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-primary'}`}>
              {label}
            </button>
          ))}
        </div>
      </div>
      {/* Le filtre « Absents » ne pouvait rien trouver : un absent n'a pas de ligne ici. */}
      <p className="flex items-center gap-1.5 text-[11px] text-on-surface-variant mb-6">
        <FiInfo size={12} aria-hidden="true" />
        Un absent n'a pas de ligne ici, faute de scan. Les absences se consultent séance par séance dans la{' '}
        <Link to="/attendance/scan" className="font-semibold text-primary hover:underline">saisie manuelle</Link>.
      </p>

      <div className="bg-surface-container-lowest rounded-xxl shadow-sm border border-outline-variant/10 overflow-hidden">
        <DataTable
          columns={columns}
          data={mappedRecords}
          loading={loading}
          emptyMessage="Aucun enregistrement trouvé"
          sortField={colonneTriee}
          sortDirection={tri.sens}
          onSort={trier}
          pagination={pagination || null}
          onPageChange={setPage}
        />
      </div>
    </div>
  );
};

export default PresenceHistoryPage;
