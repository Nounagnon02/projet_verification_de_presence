import { useState, useEffect, useCallback, useRef } from 'react';
// Modales rendues dans <body> : placées dans la page, elles héritaient de la
// marge de son conteneur (space-y) et laissaient une bande découverte en haut.
import { createPortal } from 'react-dom';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { FiPlus, FiEdit2, FiTrash2, FiSave, FiX, FiRefreshCw, FiBook, FiBookOpen, FiChevronDown, FiChevronRight, FiAlertTriangle, FiSearch, FiUpload, FiFileText, FiLoader } from 'react-icons/fi';
import api from '../../api/axios';
import useFiltresAcademiques from '../../hooks/useFiltresAcademiques';
import BandeauAnneeClose from '../../components/ui/BandeauAnneeClose';
import useNiveaux, { semestresDuNiveau } from '../../hooks/useNiveaux';
import CsvTemplateDownload from '../../components/import/CsvTemplateDownload';
import { VOLUMES, libelleVolumes } from '../../utils/typesSeance';

const INITIAL_UE = { code: '', intitule: '', filiere_id: '', annee_id: '', semestre: 1, credits: '', filiere_ids: [] };
const INITIAL_EC = { code: '', intitule: '', volume_cm: 0, volume_td: 0, volume_tp: 0, volume_td_tp: 0 };

export default function UEManagementPage() {
  const navigate = useNavigate();
  const [ues, setUes] = useState([]);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [search, setSearch] = useState('');

  // Modal UE
  // « id » : l'UE modifiée. On la retrouvait par son code, qui n'est unique que
  // dans sa filière et son année — et qu'on peut justement modifier.
  const [ueModal, setUeModal] = useState({ open: false, editing: false, id: null, data: INITIAL_UE, saving: false });
  // Modal EC
  const [ecModal, setEcModal] = useState({ open: false, editing: false, ueId: null, data: INITIAL_EC, saving: false });
  // Expanded UEs
  const [expanded, setExpanded] = useState({});

  // Import PDF state
  const [showImportModal, setShowImportModal] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importDragOver, setImportDragOver] = useState(false);
  const [importUploading, setImportUploading] = useState(false);
  // Deux formats pour la meme modale : PDF analyse par l'IA, ou CSV structure.
  // L'import CSV vivait dans une section « Imports » separee, loin des UE et EC
  // qu'il alimente ; il rejoint l'ecran qui les gere.
  const [importFormat, setImportFormat] = useState('pdf');
  const [importResultat, setImportResultat] = useState(null);
  const [importError, setImportError] = useState('');
  const importFileRef = useRef(null);

  // Filtres
  // Filtres en cascade : l'année restreint les filières, qui déterminent le
  // niveau. Les formulaires, eux, gardent la liste complète.
  // La grille des filières mène ici avec ?filiere=…&annee=… : les filtres s'y ouvrent.
  const [parametres] = useSearchParams();
  const filtres = useFiltresAcademiques({ initial: { annee: parametres.get('annee'), filiere: parametres.get('filiere') } });
  const { annees, filieres, filieresToutes } = filtres;
  const niveaux = useNiveaux();

  const handleImportDrop = (e) => {
    e.preventDefault();
    setImportDragOver(false);
    const f = e.dataTransfer.files[0];
    if (!f) return;

    const estPdf = f.type === 'application/pdf' || f.name.endsWith('.pdf');
    const estCsv = f.name.endsWith('.csv');

    if (importFormat === 'pdf' ? estPdf : estCsv) {
      setImportFile(f);
      setImportError('');
    } else {
      setImportError(importFormat === 'pdf'
        ? 'Veuillez sélectionner un fichier PDF.'
        : 'Veuillez sélectionner un fichier CSV.');
    }
  };

  /** Import CSV : le serveur repond directement, sans etape de validation. */
  const handleImportCsv = async () => {
    if (!importFile) return;
    setImportUploading(true);
    setImportError('');
    setImportResultat(null);
    try {
      const formData = new FormData();
      formData.append('file', importFile);
      const { data } = await api.post('/admin/import/csv/courses', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const d = data?.data ?? data;
      setImportResultat({
        crees: d.created ?? d.success ?? 0,
        ignores: d.skipped ?? 0,
        erreurs: Array.isArray(d.errors) ? d.errors : [],
      });
      rafraichir();
    } catch (err) {
      setImportError(err.response?.data?.message || "Erreur lors de l'import du fichier.");
    } finally {
      setImportUploading(false);
    }
  };

  const handleImportUpload = async () => {
    if (!importFile) return;
    if (importFormat === 'csv') return handleImportCsv();
    setImportUploading(true);
    setImportError('');
    try {
      const formData = new FormData();
      formData.append('file', importFile);
      const { data } = await api.post('/admin/import/courses', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      // L'API repond { success, message, data: { analysis_id, status } }.
      // On lisait « data.data.id » puis « data.analysis_id » a la racine :
      // aucune des deux n'existe, si bien que l'identifiant etait TOUJOURS
      // indefini et que l'ecran de progression n'avait rien a suivre.
      const charge = data?.data ?? data;
      const analysisId = charge?.analysis_id ?? charge?.id ?? null;
      if (!analysisId) {
        // Sans identifiant, l'ecran de progression n'a rien a suivre : on le dit
        // ici plutot que de l'y envoyer se plaindre.
        setImportError("Le serveur n'a pas renvoyé d'identifiant d'analyse. Relancez l'import.");
        setImportUploading(false);
        return;
      }

      // Passage de relais vers l'ecran de progression. Une seule cle, un seul
      // objet : deux cles separees ne pouvaient pas etre ecrites de facon
      // atomique, et surtout l'ecran de progression n'en lisait aucune des
      // deux — il attendait « import_analysis », que personne n'ecrivait. Le
      // serveur analysait le document, l'interface jetait le resultat et
      // affichait « Aucune analyse en cours ».
      //
      // La cle est distincte de « import_analysis », qui porte le RESULTAT de
      // l'analyse d'un emploi du temps : la meme cle pour le passage de relais
      // et pour le resultat se serait ecrasee d'un import a l'autre.
      sessionStorage.setItem('import_en_cours', JSON.stringify({
        analysis_id: analysisId,
        type: 'courses',
      }));

      navigate('/import/ai-analysis');
    } catch (err) {
      setImportError(err.response?.data?.message || 'Erreur lors de l\'import du fichier.');
      setImportUploading(false);
    }
  };

  const resetImport = () => {
    setImportFile(null);
    setImportError('');
    setImportUploading(false);
    setImportResultat(null);
  };


  // Compteur de rechargement : les actions qui modifient les données
  // l'incrémentent au lieu d'appeler une seconde fonction de chargement. La
  // requête n'est émise qu'à un seul endroit, et l'annulation y est
  // systématique — une réponse tardive ne peut plus écraser un état plus récent.
  const [rechargement, setRechargement] = useState(0);
  const rafraichir = useCallback(() => setRechargement((n) => n + 1), []);

  useEffect(() => {
    let annule = false;

    (async () => {
      try {
        setLoading(true);
        if (!annule) setError('');
        const params = {};
        if (filtres.annee) params.annee_id = filtres.annee;
        if (filtres.filiere) params.filiere_id = filtres.filiere;
        if (filtres.niveau) params.niveau = filtres.niveau;
        // Les listes de référence sont chargées par useFiltresAcademiques :
        // les redemander ici les aurait figées à leur version non filtrée.
        const uesRes = await api.get('/admin/ues', { params });
        if (!annule) setUes(uesRes.data?.data ?? uesRes.data ?? []);
      } catch (err) {
        if (!annule) setError('Erreur lors du chargement des données.');
        console.error('[UE]', err);
      } finally {
        if (!annule) setLoading(false);
      }
  
    })();

    return () => { annule = true; };
  }, [filtres.annee, filtres.filiere, filtres.niveau, rechargement]);

  const filteredUes = ues.filter(ue =>
    !search || ue.code?.toLowerCase().includes(search.toLowerCase()) ||
    ue.intitule?.toLowerCase().includes(search.toLowerCase())
  );

  // ─── UE CRUD ────────────────────────────────────────────

  // Semestres permis par le niveau de la filière : S1-S2 en L1… S9-S10 en M2.
  // Le formulaire proposait « Semestre 1 à 6 » pour toutes : le Master était
  // impossible à saisir, et un S3 pouvait atterrir dans une filière de L1.
  const semestresPour = (filiereId) =>
    semestresDuNiveau(niveaux, filieresToutes.find((f) => String(f.id) === String(filiereId))?.niveau);

  const openCreateUe = () => {
    const filiereId = filtres.filiere || filieres[0]?.id || '';
    setUeModal({
      open: true, editing: false, id: null,
      data: {
        ...INITIAL_UE,
        filiere_id: filiereId,
        annee_id: filtres.annee || annees.find((a) => a.active)?.id || annees[0]?.id || '',
        semestre: semestresPour(filiereId)[0] ?? 1,
      },
      saving: false,
    });
  };
  const openEditUe = (ue) => setUeModal({
    open: true, editing: true, id: ue.id,
    data: { code: ue.code, intitule: ue.intitule, filiere_id: ue.filiere?.id || ue.filiere_id || '', annee_id: ue.annee?.id || ue.annee_id || '', semestre: ue.semestre, credits: ue.credits ?? '', filiere_ids: (ue.filieres || []).map((f) => String(f.id)).filter((id) => id !== String(ue.filiere?.id || ue.filiere_id)) },
    saving: false,
  });

  // Une UE ancienne peut porter un semestre hors du niveau : il reste affiché,
  // signalé, plutôt que remplacé en silence par le premier semestre permis.
  const semestresPermis = semestresPour(ueModal.data.filiere_id);
  const semestresProposes = semestresPermis.includes(Number(ueModal.data.semestre))
    ? semestresPermis
    : [...semestresPermis, Number(ueModal.data.semestre)];

  // Cours commun : les autres filières du même niveau peuvent suivre l'UE.
  const porteuseModal = filieresToutes.find((f) => String(f.id) === String(ueModal.data.filiere_id));
  const autresFilieres = porteuseModal
    ? filieresToutes.filter((f) => f.niveau === porteuseModal.niveau && String(f.id) !== String(porteuseModal.id))
    : [];

  const handleSaveUe = async (e) => {
    e.preventDefault();
    setUeModal(prev => ({ ...prev, saving: true }));
    setError('');
    setSuccess('');
    try {
      if (ueModal.editing) {
        await api.put(`/admin/ues/${ueModal.id}`, ueModal.data);
        setSuccess('UE mise à jour avec succès.');
      } else {
        await api.post('/admin/ues', ueModal.data);
        setSuccess('UE créée avec succès.');
      }
      setUeModal({ open: false, editing: false, id: null, data: INITIAL_UE, saving: false });
      rafraichir();
    } catch (err) {
      const msg = err.response?.data?.message || (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : null) || 'Erreur lors de la sauvegarde.';
      setError(msg);
      setUeModal(prev => ({ ...prev, saving: false }));
    }
  };

  const handleDeleteUe = async (ue) => {
    if (!window.confirm(`Supprimer l'UE "${ue.code} — ${ue.intitule}" ? Cette action est irréversible.`)) return;
    try {
      await api.delete(`/admin/ues/${ue.id}`);
      setSuccess('UE supprimée.');
      rafraichir();
    } catch {
      setError('Erreur lors de la suppression.');
    }
  };

  // ─── EC CRUD ────────────────────────────────────────────

  const openCreateEc = (ueId) => setEcModal({ open: true, editing: false, ueId, data: { ...INITIAL_EC }, saving: false });
  const openEditEc = (ec, ueId) => setEcModal({ open: true, editing: true, ueId, id: ec.id, data: { code: ec.code, intitule: ec.intitule, volume_cm: ec.volume_cm ?? 0, volume_td: ec.volume_td ?? 0, volume_tp: ec.volume_tp ?? 0, volume_td_tp: ec.volume_td_tp ?? 0, volume_horaire: ec.volume_horaire, aVentiler: Boolean(ec.volume_a_ventiler) }, saving: false });

  const handleSaveEc = async (e) => {
    e.preventDefault();
    setEcModal(prev => ({ ...prev, saving: true }));
    setError('');
    setSuccess('');
    try {
      const payload = { ...ecModal.data, ue_id: ecModal.ueId };
      if (ecModal.editing) {
        // Par son identifiant : retrouvé par son code, un EC dont on changeait
        // le code n'était pas modifié, et l'écran annonçait pourtant un succès.
        await api.put(`/admin/ecs/${ecModal.id}`, payload);
        setSuccess('EC mis à jour avec succès.');
      } else {
        await api.post('/admin/ecs', payload);
        setSuccess('EC créé avec succès.');
      }
      setEcModal({ open: false, editing: false, ueId: null, data: INITIAL_EC, saving: false });
      rafraichir();
    } catch (err) {
      const msg = err.response?.data?.message || (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : null) || 'Erreur lors de la sauvegarde.';
      setError(msg);
      setEcModal(prev => ({ ...prev, saving: false }));
    }
  };

  const handleDeleteEc = async (ec) => {
    if (!window.confirm(`Supprimer l'EC "${ec.code} — ${ec.intitule}" ?`)) return;
    try {
      await api.delete(`/admin/ecs/${ec.id}`);
      setSuccess('EC supprimé.');
      rafraichir();
    } catch {
      setError('Erreur lors de la suppression.');
    }
  };

  const toggleExpand = (ueId) => setExpanded(prev => ({ ...prev, [ueId]: !prev[ueId] }));

  // ─── Helpers ────────────────────────────────────────────

  const getFiliere = (id) => filieres.find(f => String(f.id) === String(id))?.intitule || filieres.find(f => String(f.id) === String(id))?.code || '—';

  const StatutBadge = ({ statut, size = 'sm' }) => {
    const variants = {
      termine: { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-700 dark:text-green-400', dot: 'bg-green-500', label: 'Terminé' },
      en_cours: { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-400', dot: 'bg-amber-500', label: 'En cours' },
      non_demarre: { bg: 'bg-gray-100 dark:bg-gray-800', text: 'text-gray-500 dark:text-gray-400', dot: 'bg-gray-400', label: 'Non démarré' },
    };
    const v = variants[statut] || variants.non_demarre;
    const sizeClass = size === 'xs' ? 'text-[9px] px-1.5 py-0.5' : 'text-[10px] px-2 py-0.5';
    return (
      <span className={`inline-flex items-center gap-1.5 rounded-full font-semibold ${v.bg} ${v.text} ${sizeClass}`}>
        <span className={`w-1.5 h-1.5 rounded-full ${v.dot}`} />
        {v.label}
      </span>
    );
  };

  // ─── RENDER ────────────────────────────────────────────

  // Année close pour l'établissement : consultation seulement (le serveur
  // refuse en 409). Chaque ligne suit l'année de sa propre ressource.
  const anneeFermee = (id) => Boolean(annees.find((a) => String(a.id) === String(id))?.close);

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Gestion des UE / EC</h1>
          <p className="text-sm text-on-surface-variant">Unités d'Enseignement et Éléments Constitutifs</p>
        </div>
        <div className="flex items-center gap-3">
          <button onClick={() => setShowImportModal(true)} disabled={filtres.anneeClose}
            className="flex items-center gap-2 px-5 py-2.5 bg-surface-container-high text-on-surface rounded-xl font-bold text-sm border border-outline-variant/20 hover:bg-surface-container-low transition-all disabled:opacity-30 disabled:cursor-not-allowed">
            <FiUpload size={16} /> Import en masse
          </button>
          <button onClick={openCreateUe} disabled={filtres.anneeClose}
            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all disabled:opacity-30 disabled:cursor-not-allowed">
            <FiPlus size={16} /> Nouvelle UE
          </button>
        </div>
      </div>

      {/* Filtres */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[180px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Année académique</label>
            <select value={filtres.annee} onChange={(e) => filtres.setAnnee(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Toutes les années</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (Active)' : ''}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[180px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
            <select value={filtres.filiere} onChange={(e) => filtres.setFiliere(e.target.value)}
              disabled={filtres.anneeVide} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
              <option value="">{filtres.anneeVide ? 'Aucune filière cette année' : 'Toutes les filières'}</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px]">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Niveau</label>
            <select value={filtres.niveau} onChange={(e) => filtres.setNiveau(e.target.value)}
              disabled={filtres.niveaux.length === 0} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
              <option value="">Tous les niveaux</option>
              {filtres.niveaux.map(n => <option key={n} value={n}>{n}</option>)}
            </select>
          </div>
        </div>
      </div>

      {filtres.anneeClose && <BandeauAnneeClose annee={filtres.anneeChoisie} />}

      {/* Alertes */}
      {error && (
        <div className="flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle size={16} className="flex-shrink-0" />
          <span className="flex-1">{error}</span>
          <button onClick={() => setError('')} className="text-on-error-container/60 hover:text-on-error-container">&times;</button>
        </div>
      )}
      {success && (
        <div className="flex items-center gap-2 p-3 bg-secondary-container/30 rounded-xl text-on-secondary-container text-sm border border-secondary/10">
          <FiSave size={16} className="flex-shrink-0" />
          <span className="flex-1">{success}</span>
          <button onClick={() => setSuccess('')} className="text-on-secondary-container/60 hover:text-on-secondary-container">&times;</button>
        </div>
      )}

      {/* Recherche */}
      <div className="relative">
        <FiSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-outline" size={16} />
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Rechercher une UE (code ou intitulé)..."
          className="w-full pl-10 pr-4 py-2.5 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
        />
      </div>

      {/* Loading */}
      {loading ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center">
          <FiRefreshCw className="animate-spin mx-auto text-primary text-3xl mb-4" />
          <p className="text-on-surface-variant">Chargement des UE...</p>
        </div>
      ) : filteredUes.length === 0 ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center border border-dashed border-outline-variant/30">
          <div className="w-16 h-16 bg-surface-container-high rounded-full flex items-center justify-center mx-auto mb-6">
            <FiBook className="text-outline" size={28} />
          </div>
          <h3 className="text-lg font-semibold text-on-surface mb-2">
            {search ? 'Aucune UE ne correspond à votre recherche' : 'Aucune UE'}
          </h3>
          <p className="text-sm text-on-surface-variant">
            {search ? 'Essayez un autre terme de recherche.' : 'Créez votre première Unité d\'Enseignement.'}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {filteredUes.map((ue) => (
            <div key={ue.id} className="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/10 overflow-hidden">
              {/* En-tête UE */}
              <div className="p-4 flex items-center gap-3 cursor-pointer hover:bg-surface-container-low/50 transition-colors"
                onClick={() => toggleExpand(ue.id)}>
                <button className="p-1 text-outline hover:text-primary transition-colors">
                  {expanded[ue.id] ? <FiChevronDown size={18} /> : <FiChevronRight size={18} />}
                </button>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="px-2.5 py-0.5 bg-primary/10 text-primary rounded-md text-xs font-bold font-mono">{ue.code}</span>
                    <h3 className="text-sm font-bold text-on-surface truncate">{ue.intitule}</h3>
                  </div>
                  <div className="flex items-center gap-3 mt-1 text-[10px] text-on-surface-variant">
                    <span>{getFiliere(ue.filiere?.id || ue.filiere_id)}</span>
                    {ue.filieres?.length > 1 && (
                      <span className="font-semibold text-secondary">Commune à {ue.filieres.map((f) => f.code).join(', ')}</span>
                    )}
                    <span>Semestre {ue.semestre}</span>
                    <span>{ue.volume_horaire}h</span>
                    <StatutBadge statut={ue.statut} />
                    <span>{ue.ecs?.length || ue.ecs_count || 0} EC{((ue.ecs?.length || ue.ecs_count || 0) > 1) ? 's' : ''}</span>
                  </div>
                </div>
                <div className="flex items-center gap-1 flex-shrink-0">
                  <button onClick={(e) => { e.stopPropagation(); openCreateEc(ue.id); }} disabled={anneeFermee(ue.annee_id)}
                    className="p-2 disabled:opacity-30 disabled:cursor-not-allowed text-outline hover:text-secondary hover:bg-secondary/10 rounded-lg transition-all" title="Ajouter un EC">
                    <FiPlus size={14} />
                  </button>
                  <button onClick={(e) => { e.stopPropagation(); openEditUe(ue); }} disabled={anneeFermee(ue.annee_id)}
                    className="p-2 disabled:opacity-30 disabled:cursor-not-allowed text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Modifier l'UE">
                    <FiEdit2 size={14} />
                  </button>
                  <button onClick={(e) => { e.stopPropagation(); handleDeleteUe(ue); }} disabled={anneeFermee(ue.annee_id)}
                    className="p-2 disabled:opacity-30 disabled:cursor-not-allowed text-outline hover:text-error hover:bg-error/10 rounded-lg transition-all" title="Supprimer l'UE">
                    <FiTrash2 size={14} />
                  </button>
                </div>
              </div>

              {/* Liste ECs */}
              {expanded[ue.id] && (
                <div className="border-t border-outline-variant/10 bg-surface/40 px-4 py-3 space-y-2">
                  {(!ue.ecs || ue.ecs.length === 0) ? (
                    <p className="text-xs text-on-surface-variant text-center py-4">
                      Aucun EC pour cette UE.
                      {!anneeFermee(ue.annee_id) && (
                        <button onClick={() => openCreateEc(ue.id)} className="ml-1 text-primary font-semibold hover:underline">Ajouter un EC</button>
                      )}
                    </p>
                  ) : (
                    ue.ecs.map((ec) => (
                      <div key={ec.id} className="flex items-center gap-3 px-3 py-2 rounded-lg bg-surface-container-lowest/60 border border-outline-variant/5">
                        <FiBookOpen size={14} className="text-outline flex-shrink-0" />
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2">
                            <span className="text-xs font-mono font-bold text-secondary">{ec.code}</span>
                            <span className="text-sm text-on-surface truncate">{ec.intitule}</span>
                          </div>
                          <p className="text-[10px] text-on-surface-variant">{libelleVolumes(ec)}</p>
                          <StatutBadge statut={ec.statut} size="xs" />
                        </div>
                        <div className="flex items-center gap-1">
                          <button onClick={() => openEditEc(ec, ue.id)} disabled={anneeFermee(ue.annee_id)}
                            className="p-1.5 disabled:opacity-30 disabled:cursor-not-allowed text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Modifier">
                            <FiEdit2 size={12} />
                          </button>
                          <button onClick={() => handleDeleteEc(ec, ue.id)} disabled={anneeFermee(ue.annee_id)}
                            className="p-1.5 disabled:opacity-30 disabled:cursor-not-allowed text-outline hover:text-error hover:bg-error/10 rounded-lg transition-all" title="Supprimer">
                            <FiTrash2 size={12} />
                          </button>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* ─── Modal UE ─────────────────────────────────── */}
      {ueModal.open && createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          onClick={() => setUeModal(prev => ({ ...prev, open: false }))}>
          <div className="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-lg shadow-xl"
            onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-lg font-bold text-primary">{ueModal.editing ? 'Modifier l\'UE' : 'Nouvelle UE'}</h2>
              <button onClick={() => setUeModal(prev => ({ ...prev, open: false }))} className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX size={20} className="text-outline" />
              </button>
            </div>
            <form onSubmit={handleSaveUe} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label htmlFor="ue-code" className="block text-xs font-semibold text-on-surface mb-1">Code *</label>
                  <input id="ue-code" type="text" value={ueModal.data.code} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, code: e.target.value } }))}
                    required maxLength={20} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="EX: UE-MIAGE-101" />
                </div>
                <div>
                  <label htmlFor="ue-semestre" className="block text-xs font-semibold text-on-surface mb-1">Semestre *</label>
                  <select id="ue-semestre" value={ueModal.data.semestre} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, semestre: parseInt(e.target.value) } }))}
                    className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    {semestresProposes.map(s => (
                      <option key={s} value={s}>Semestre {s}{semestresPermis.includes(s) ? '' : ' (hors niveau de la filière)'}</option>
                    ))}
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1">Intitulé *</label>
                <input type="text" value={ueModal.data.intitule} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, intitule: e.target.value } }))}
                  required maxLength={255} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: Programmation Web Avancée" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label htmlFor="ue-filiere" className="block text-xs font-semibold text-on-surface mb-1">Filière *</label>
                  <select id="ue-filiere" value={ueModal.data.filiere_id} onChange={(e) => {
                    // Changer de filière ramène le semestre dans ceux de son niveau.
                    const filiereId = e.target.value;
                    const permis = semestresPour(filiereId);
                    setUeModal(prev => ({ ...prev, data: { ...prev.data, filiere_id: filiereId, filiere_ids: [], semestre: permis.includes(Number(prev.data.semestre)) ? prev.data.semestre : (permis[0] ?? prev.data.semestre) } }));
                  }}
                    required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Sélectionner...</option>
                    {filieresToutes.map(f => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Année académique *</label>
                  <select value={ueModal.data.annee_id} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, annee_id: e.target.value } }))}
                    required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Sélectionner...</option>
                    {annees.map(a => <option key={a.id} value={a.id} disabled={a.close}>{a.libelle}{a.active ? ' (Active)' : ''}{a.close ? ' (close)' : ''}</option>)}
                  </select>
                </div>
              </div>
              {autresFilieres.length > 0 && (
                <fieldset>
                  <legend className="block text-xs font-semibold text-on-surface mb-1">Aussi suivie par</legend>
                  <div className="flex flex-wrap gap-x-4 gap-y-1">
                    {autresFilieres.map((f) => (
                      <label key={f.id} htmlFor={`ue-commune-${f.id}`} className="flex items-center gap-1.5 text-sm">
                        <input
                          id={`ue-commune-${f.id}`}
                          type="checkbox"
                          checked={(ueModal.data.filiere_ids || []).map(String).includes(String(f.id))}
                          onChange={(e) => setUeModal(prev => {
                            const autres = (prev.data.filiere_ids || []).map(String).filter((id) => id !== String(f.id));
                            return { ...prev, data: { ...prev.data, filiere_ids: e.target.checked ? [...autres, String(f.id)] : autres } };
                          })}
                        />
                        {f.code}
                      </label>
                    ))}
                  </div>
                  <p className="text-xs text-on-surface-variant mt-1">
                    Un cours commun est une seule UE : séances communes, chaque filière comptant ses propres étudiants.
                  </p>
                </fieldset>
              )}
              <div>
                <label htmlFor="ue-credits" className="block text-xs font-semibold text-on-surface mb-1">Crédits</label>
                <input id="ue-credits" type="number" value={ueModal.data.credits} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, credits: e.target.value } }))}
                  min={0} max={60} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                {/* Le volume d'une UE est la somme de ses EC : il n'est plus saisi. */}
                <p className="text-xs text-on-surface-variant mt-1">Le volume horaire de l'UE est la somme de ses EC.</p>
              </div>
              <div className="flex gap-3 pt-2">
                <button type="submit" disabled={ueModal.saving}
                  className="flex-1 flex items-center justify-center gap-2 py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all disabled:opacity-50">
                  {ueModal.saving ? <FiRefreshCw className="animate-spin" size={16} /> : <FiSave size={16} />}
                  {ueModal.editing ? 'Mettre à jour' : 'Créer l\'UE'}
                </button>
                <button type="button" onClick={() => setUeModal(prev => ({ ...prev, open: false }))}
                  className="px-6 py-2.5 bg-surface-container-high text-on-surface-variant rounded-xl font-semibold text-sm hover:bg-surface-container-high/80 transition-all">
                  Annuler
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body,
      )}

      {/* ─── Modal EC ─────────────────────────────────── */}
      {ecModal.open && createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          onClick={() => setEcModal(prev => ({ ...prev, open: false }))}>
          <div className="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-md shadow-xl"
            onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-lg font-bold text-primary">{ecModal.editing ? "Modifier l'EC" : "Nouvel EC"}</h2>
              <button onClick={() => setEcModal(prev => ({ ...prev, open: false }))} className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX size={20} className="text-outline" />
              </button>
            </div>
            <form onSubmit={handleSaveEc} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1">Code *</label>
                <input type="text" value={ecModal.data.code} onChange={(e) => setEcModal(prev => ({ ...prev, data: { ...prev.data, code: e.target.value } }))}
                  required maxLength={20} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: EC-MIAGE-101-1" />
              </div>
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1">Intitulé *</label>
                <input type="text" value={ecModal.data.intitule} onChange={(e) => setEcModal(prev => ({ ...prev, data: { ...prev.data, intitule: e.target.value } }))}
                  required maxLength={255} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: Développement Frontend" />
              </div>
              <fieldset>
                <legend className="block text-xs font-semibold text-on-surface mb-1">Heures en présentiel *</legend>
                <div className="grid grid-cols-4 gap-2">
                  {VOLUMES.map(([champ, libelle]) => (
                    <div key={champ}>
                      <label htmlFor={`ec-${champ}`} className="block text-[11px] text-on-surface-variant mb-0.5">{libelle}</label>
                      <input id={`ec-${champ}`} type="number" min={0} max={999} value={ecModal.data[champ] ?? 0}
                        onChange={(e) => setEcModal(prev => ({ ...prev, data: { ...prev.data, [champ]: parseInt(e.target.value) || 0 } }))}
                        className="w-full px-2 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                    </div>
                  ))}
                </div>
                <p className="text-xs text-on-surface-variant mt-1">
                  Le TPE n'y figure pas. TP/TD : quand la maquette ne sépare pas les deux, les séances de TD comme de TP y puisent.
                  {ecModal.data.aVentiler && ` Volume actuel : ${ecModal.data.volume_horaire}h, à répartir entre ces colonnes.`}
                </p>
              </fieldset>
              <div className="flex gap-3 pt-2">
                <button type="submit" disabled={ecModal.saving}
                  className="flex-1 flex items-center justify-center gap-2 py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all disabled:opacity-50">
                  {ecModal.saving ? <FiRefreshCw className="animate-spin" size={16} /> : <FiSave size={16} />}
                  {ecModal.editing ? "Mettre à jour" : "Créer l'EC"}
                </button>
                <button type="button" onClick={() => setEcModal(prev => ({ ...prev, open: false }))}
                  className="px-6 py-2.5 bg-surface-container-high text-on-surface-variant rounded-xl font-semibold text-sm hover:bg-surface-container-high/80 transition-all">
                  Annuler
                </button>
              </div>
            </form>
          </div>
        </div>,
        document.body,
      )}

      {/* ─── Modal Import PDF UE/EC ────────────────────────── */}
      {showImportModal && createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          onClick={() => { if (!importUploading) { setShowImportModal(false); resetImport(); } }}>
          <div className="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto"
            onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-lg font-bold text-primary">Import en masse des UE/EC</h2>
              <button onClick={() => { setShowImportModal(false); resetImport(); }} disabled={importUploading}
                className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX size={20} className="text-outline" />
              </button>
            </div>

            {/* Choix du format. Les deux chemins aboutissent aux memes UE et EC :
                le PDF passe par une extraction IA suivie d'une validation, le CSV
                est structure et s'applique directement. */}
            <div className="flex gap-1 mb-5 bg-surface-container-high rounded-xl p-1">
              {[
                ['pdf', 'PDF — analyse par IA'],
                ['csv', 'CSV — structuré'],
              ].map(([cle, libelle]) => (
                <button
                  key={cle}
                  type="button"
                  onClick={() => { setImportFormat(cle); resetImport(); }}
                  disabled={importUploading}
                  className={`flex-1 px-3 py-2 rounded-lg text-xs font-bold transition-all disabled:opacity-50 ${
                    importFormat === cle
                      ? 'bg-primary text-white shadow-sm'
                      : 'text-on-surface-variant hover:text-primary'
                  }`}
                >
                  {libelle}
                </button>
              ))}
            </div>

            {/* Drop zone */}
            <div onDragOver={(e) => { e.preventDefault(); setImportDragOver(true); }} onDragLeave={() => setImportDragOver(false)} onDrop={handleImportDrop}
              className={`border-2 border-dashed rounded-xl p-10 text-center transition-all cursor-pointer ${importDragOver ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-primary/40'} ${importFile ? 'bg-surface-container-low' : ''}`}
              onClick={() => importFileRef.current?.click()}>
              <input ref={importFileRef} type="file" accept={importFormat === 'pdf' ? '.pdf' : '.csv'} className="hidden" onChange={(e) => {
                const f = e.target.files[0]; if (f) { setImportFile(f); setImportError(''); }
              }} />
              {!importFile ? (
                <>
                  <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                    <FiUpload className="text-2xl text-primary" />
                  </div>
                  <h3 className="text-sm font-semibold text-on-surface mb-1">
                    {importFormat === 'pdf' ? 'Importez un fichier PDF' : 'Importez un fichier CSV'}
                  </h3>
                  <p className="text-xs text-on-surface-variant mb-4">
                    {importFormat === 'pdf' ? 'Analyse par IA' : 'Une ligne par EC'} — ou{' '}
                    <span className="text-primary font-semibold cursor-pointer hover:underline">parcourez</span>
                  </p>
                  <p className="text-[10px] text-on-surface-variant/60">
                    {importFormat === 'pdf' ? 'PDF uniquement — 10 Mo max' : 'CSV uniquement — 5 Mo max'}
                  </p>
                </>
              ) : (
                <div className="flex items-center gap-4 justify-center">
                  <FiFileText className="text-2xl text-primary" />
                  <div className="text-left">
                    <p className="text-sm font-medium text-on-surface">{importFile.name}</p>
                    <p className="text-[10px] text-on-surface-variant">{(importFile.size / 1024).toFixed(1)} Ko</p>
                  </div>
                  <button onClick={(e) => { e.stopPropagation(); resetImport(); }} className="p-2 hover:bg-surface-container-high rounded-lg transition-colors">
                    <FiTrash2 className="text-outline" />
                  </button>
                </div>
              )}
            </div>

            {/* Erreur */}
            {importError && (
              <div className="mt-4 flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
                <FiAlertTriangle /> {importError}
              </div>
            )}

            {/* Upload button */}
            {importFile && !importUploading && (
              <button onClick={handleImportUpload}
                className="mt-6 w-full flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all">
                <FiUpload /> {importFormat === 'pdf' ? "Analyser avec l'IA" : 'Importer les cours'}
              </button>
            )}

            {/* Attente */}
            {importUploading && (
              <div className="mt-6 bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10 text-center">
                <FiLoader className="animate-spin mx-auto text-primary text-2xl mb-3" />
                <p className="font-semibold text-primary text-sm">
                  {importFormat === 'pdf' ? 'Analyse IA en cours…' : 'Import CSV en cours…'}
                </p>
                {importFormat === 'pdf' && (
                  <p className="text-xs text-on-surface-variant mt-1">Redirection vers la page d'analyse</p>
                )}
              </div>
            )}

            {/* Resultat de l'import CSV : le serveur repond directement, il n'y a
                pas d'etape de validation comme pour le PDF. */}
            {importResultat && (
              <div className="mt-6 rounded-xl p-4 bg-surface-container-low border border-outline-variant/10">
                <p className="text-sm font-bold text-primary mb-1">
                  {importResultat.crees} élément(s) créé(s)
                  {importResultat.ignores > 0 && `, ${importResultat.ignores} ignoré(s)`}
                </p>
                {importResultat.erreurs.length > 0 ? (
                  <>
                    <p className="text-xs font-semibold text-error mt-2 mb-1">
                      {importResultat.erreurs.length} ligne(s) refusée(s) :
                    </p>
                    <ul className="text-[11px] text-on-surface-variant list-disc list-inside space-y-0.5 max-h-40 overflow-y-auto">
                      {importResultat.erreurs.slice(0, 20).map((e, i) => (
                        <li key={i}>{typeof e === 'string' ? e : JSON.stringify(e)}</li>
                      ))}
                    </ul>
                  </>
                ) : (
                  <p className="text-xs text-on-surface-variant">Aucune ligne refusée.</p>
                )}
              </div>
            )}

            <div className="mt-6 bg-surface-container-high rounded-xl p-4">
              <h4 className="text-xs font-bold text-primary mb-2">Format attendu</h4>
              {importFormat === 'pdf' ? (
                <p className="text-[11px] text-on-surface-variant">
                  Le PDF est analysé pour en extraire les UE et EC. Rien n'est enregistré
                  avant votre validation, ligne par ligne.
                </p>
              ) : (
                <>
                  <p className="text-[11px] text-on-surface-variant font-mono">
                    code_ue, intitule_ue, filiere_code, niveau, annee_libelle, semestre,
                    credits_ue, code_ec, intitule_ec, volume_cm, volume_td, volume_tp, volume_td_tp
                  </p>
                  <p className="text-[11px] text-on-surface-variant mt-2">
                    Une UE avec plusieurs EC occupe plusieurs lignes, une par EC.
                  </p>
                  <div className="mt-3">
                    <CsvTemplateDownload types={['ue-ec']} avecColonnes={false} />
                  </div>
                </>
              )}
            </div>
          </div>
        </div>,
        document.body,
      )}
    </div>
  );
}
