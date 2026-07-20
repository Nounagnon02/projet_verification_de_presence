import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  FiPlus, FiEdit2, FiTrash2, FiSave, FiX, FiRefreshCw,
  FiBook, FiBookOpen, FiChevronDown, FiChevronRight,
  FiAlertTriangle, FiSearch, FiUpload, FiCheck, FiFileText, FiLoader
} from 'react-icons/fi';
import api from '../../api/axios';

const INITIAL_UE = { code: '', intitule: '', filiere_id: '', annee_id: '', semestre: 1, volume_horaire: 30 };
const INITIAL_EC = { code: '', intitule: '', volume_horaire: 15 };

export default function UEManagementPage() {
  const navigate = useNavigate();
  const [ues, setUes] = useState([]);
  const [filieres, setFilieres] = useState([]);
  const [annees, setAnnees] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [search, setSearch] = useState('');

  // Modal UE
  const [ueModal, setUeModal] = useState({ open: false, editing: false, data: INITIAL_UE, saving: false });
  // Modal EC
  const [ecModal, setEcModal] = useState({ open: false, editing: false, ueId: null, data: INITIAL_EC, saving: false });
  // Expanded UEs
  const [expanded, setExpanded] = useState({});

  // Import PDF state
  const [showImportModal, setShowImportModal] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importDragOver, setImportDragOver] = useState(false);
  const [importUploading, setImportUploading] = useState(false);
  const [importError, setImportError] = useState('');
  const importFileRef = useRef(null);

  // Filtres
  const [filtreAnnee, setFiltreAnnee] = useState('');
  const [filtreFiliere, setFiltreFiliere] = useState('');
  const [filtreNiveau, setFiltreNiveau] = useState('');

  const handleImportDrop = (e) => {
    e.preventDefault();
    setImportDragOver(false);
    const f = e.dataTransfer.files[0];
    if (f && (f.type === 'application/pdf' || f.name.endsWith('.pdf'))) {
      setImportFile(f);
      setImportError('');
    } else {
      setImportError('Veuillez sélectionner un fichier PDF.');
    }
  };

  const handleImportUpload = async () => {
    if (!importFile) return;
    setImportUploading(true);
    setImportError('');
    try {
      const formData = new FormData();
      formData.append('file', importFile);
      const { data } = await api.post('/admin/import/courses', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const analysisId = data?.data?.id || data?.analysis_id;
      if (analysisId) {
        sessionStorage.setItem('analysis_id', analysisId);
        sessionStorage.setItem('import_type', 'courses');
      }
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
  };

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError('');
      const params = {};
      if (filtreAnnee) params.annee_id = filtreAnnee;
      if (filtreFiliere) params.filiere_id = filtreFiliere;
      if (filtreNiveau) params.niveau = filtreNiveau;
      const [uesRes, filieresRes, anneesRes] = await Promise.all([
        api.get('/admin/ues', { params }),
        api.get('/admin/filieres'),
        api.get('/admin/annees-academiques'),
      ]);
      setUes(uesRes.data?.data ?? uesRes.data ?? []);
      setFilieres(filieresRes.data?.data ?? filieresRes.data ?? []);
      setAnnees(anneesRes.data?.data ?? anneesRes.data ?? []);
    } catch (err) {
      setError('Erreur lors du chargement des données.');
      console.error('[UE]', err);
    } finally {
      setLoading(false);
    }
  }, [filtreAnnee, filtreFiliere, filtreNiveau]);

  useEffect(() => { load(); }, [load]);

  const filteredUes = ues.filter(ue =>
    !search || ue.code?.toLowerCase().includes(search.toLowerCase()) ||
    ue.intitule?.toLowerCase().includes(search.toLowerCase())
  );

  // ─── UE CRUD ────────────────────────────────────────────

  const openCreateUe = () => setUeModal({ open: true, editing: false, data: { ...INITIAL_UE, filiere_id: filieres[0]?.id || '', annee_id: annees[0]?.id || '' }, saving: false });
  const openEditUe = (ue) => setUeModal({
    open: true, editing: true,
    data: { code: ue.code, intitule: ue.intitule, filiere_id: ue.filiere?.id || ue.filiere_id || '', annee_id: ue.annee?.id || ue.annee_id || '', semestre: ue.semestre, volume_horaire: ue.volume_horaire },
    saving: false,
  });

  const handleSaveUe = async (e) => {
    e.preventDefault();
    setUeModal(prev => ({ ...prev, saving: true }));
    setError('');
    setSuccess('');
    try {
      if (ueModal.editing) {
        const { data } = await api.put(`/admin/ues/${ues.find(u => u.code === ueModal.data.code)?.id}`, ueModal.data);
        setSuccess('UE mise à jour avec succès.');
      } else {
        await api.post('/admin/ues', ueModal.data);
        setSuccess('UE créée avec succès.');
      }
      setUeModal({ open: false, editing: false, data: INITIAL_UE, saving: false });
      load();
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
      load();
    } catch (err) {
      setError('Erreur lors de la suppression.');
    }
  };

  // ─── EC CRUD ────────────────────────────────────────────

  const openCreateEc = (ueId) => setEcModal({ open: true, editing: false, ueId, data: { ...INITIAL_EC }, saving: false });
  const openEditEc = (ec, ueId) => setEcModal({ open: true, editing: true, ueId, data: { code: ec.code, intitule: ec.intitule, volume_horaire: ec.volume_horaire }, saving: false });

  const handleSaveEc = async (e) => {
    e.preventDefault();
    setEcModal(prev => ({ ...prev, saving: true }));
    setError('');
    setSuccess('');
    try {
      const payload = { ...ecModal.data, ue_id: ecModal.ueId };
      if (ecModal.editing) {
        const ue = ues.find(u => u.id === ecModal.ueId);
        const ec = ue?.ecs?.find(ec => ec.code === ecModal.data.code);
        if (ec) await api.put(`/admin/ecs/${ec.id}`, payload);
        setSuccess('EC mis à jour avec succès.');
      } else {
        await api.post('/admin/ecs', payload);
        setSuccess('EC créé avec succès.');
      }
      setEcModal({ open: false, editing: false, ueId: null, data: INITIAL_EC, saving: false });
      load();
    } catch (err) {
      const msg = err.response?.data?.message || (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : null) || 'Erreur lors de la sauvegarde.';
      setError(msg);
      setEcModal(prev => ({ ...prev, saving: false }));
    }
  };

  const handleDeleteEc = async (ec, ueId) => {
    if (!window.confirm(`Supprimer l'EC "${ec.code} — ${ec.intitule}" ?`)) return;
    try {
      await api.delete(`/admin/ecs/${ec.id}`);
      setSuccess('EC supprimé.');
      load();
    } catch (err) {
      setError('Erreur lors de la suppression.');
    }
  };

  const toggleExpand = (ueId) => setExpanded(prev => ({ ...prev, [ueId]: !prev[ueId] }));

  // ─── Helpers ────────────────────────────────────────────

  const getFiliere = (id) => filieres.find(f => String(f.id) === String(id))?.intitule || filieres.find(f => String(f.id) === String(id))?.code || '—';
  const getAnnee = (id) => annees.find(a => String(a.id) === String(id))?.libelle || '—';

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

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Gestion des UE / EC</h1>
          <p className="text-sm text-on-surface-variant">Unités d'Enseignement et Éléments Constitutifs</p>
        </div>
        <div className="flex items-center gap-3">
          <button onClick={() => setShowImportModal(true)}
            className="flex items-center gap-2 px-5 py-2.5 bg-surface-container-high text-on-surface rounded-xl font-bold text-sm border border-outline-variant/20 hover:bg-surface-container-low transition-all">
            <FiUpload size={16} /> Import en masse
          </button>
          <button onClick={openCreateUe}
            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all">
            <FiPlus size={16} /> Nouvelle UE
          </button>
        </div>
      </div>

      {/* Filtres */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[180px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Année académique</label>
            <select value={filtreAnnee} onChange={(e) => setFiltreAnnee(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Toutes les années</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (Active)' : ''}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[180px] flex-1">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
            <select value={filtreFiliere} onChange={(e) => setFiltreFiliere(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Toutes les filières</option>
              {filieres.map(f => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px]">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Niveau</label>
            <select value={filtreNiveau} onChange={(e) => setFiltreNiveau(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
              <option value="">Tous les niveaux</option>
              <option value="L1">L1 — Licence 1</option>
              <option value="L2">L2 — Licence 2</option>
              <option value="L3">L3 — Licence 3</option>
              <option value="M1">M1 — Master 1</option>
              <option value="M2">M2 — Master 2</option>
            </select>
          </div>
        </div>
      </div>

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
                    <span>Semestre {ue.semestre}</span>
                    <span>{ue.volume_horaire}h</span>
                    <StatutBadge statut={ue.statut} />
                    <span>{ue.ecs?.length || ue.ecs_count || 0} EC{((ue.ecs?.length || ue.ecs_count || 0) > 1) ? 's' : ''}</span>
                  </div>
                </div>
                <div className="flex items-center gap-1 flex-shrink-0">
                  <button onClick={(e) => { e.stopPropagation(); openCreateEc(ue.id); }}
                    className="p-2 text-outline hover:text-secondary hover:bg-secondary/10 rounded-lg transition-all" title="Ajouter un EC">
                    <FiPlus size={14} />
                  </button>
                  <button onClick={(e) => { e.stopPropagation(); openEditUe(ue); }}
                    className="p-2 text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Modifier l'UE">
                    <FiEdit2 size={14} />
                  </button>
                  <button onClick={(e) => { e.stopPropagation(); handleDeleteUe(ue); }}
                    className="p-2 text-outline hover:text-error hover:bg-error/10 rounded-lg transition-all" title="Supprimer l'UE">
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
                      <button onClick={() => openCreateEc(ue.id)} className="ml-1 text-primary font-semibold hover:underline">Ajouter un EC</button>
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
                          <p className="text-[10px] text-on-surface-variant">{ec.volume_horaire}h</p>
                          <StatutBadge statut={ec.statut} size="xs" />
                        <div className="flex items-center gap-1">
                          <button onClick={() => openEditEc(ec, ue.id)}
                            className="p-1.5 text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Modifier">
                            <FiEdit2 size={12} />
                          </button>
                          <button onClick={() => handleDeleteEc(ec, ue.id)}
                            className="p-1.5 text-outline hover:text-error hover:bg-error/10 rounded-lg transition-all" title="Supprimer">
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
      {ueModal.open && (
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
                  <label className="block text-xs font-semibold text-on-surface mb-1">Code *</label>
                  <input type="text" value={ueModal.data.code} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, code: e.target.value } }))}
                    required maxLength={20} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="EX: UE-MIAGE-101" />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Semestre *</label>
                  <select value={ueModal.data.semestre} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, semestre: parseInt(e.target.value) } }))}
                    className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    {[1,2,3,4,5,6].map(s => <option key={s} value={s}>Semestre {s}</option>)}
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
                  <label className="block text-xs font-semibold text-on-surface mb-1">Filière *</label>
                  <select value={ueModal.data.filiere_id} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, filiere_id: e.target.value } }))}
                    required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Sélectionner...</option>
                    {filieres.map(f => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Année académique *</label>
                  <select value={ueModal.data.annee_id} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, annee_id: e.target.value } }))}
                    required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Sélectionner...</option>
                    {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (Active)' : ''}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1">Volume horaire (heures) *</label>
                <input type="number" value={ueModal.data.volume_horaire} onChange={(e) => setUeModal(prev => ({ ...prev, data: { ...prev.data, volume_horaire: parseInt(e.target.value) || 0 } }))}
                  required min={1} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
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
        </div>
      )}

      {/* ─── Modal EC ─────────────────────────────────── */}
      {ecModal.open && (
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
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1">Volume horaire (heures) *</label>
                <input type="number" value={ecModal.data.volume_horaire} onChange={(e) => setEcModal(prev => ({ ...prev, data: { ...prev.data, volume_horaire: parseInt(e.target.value) || 0 } }))}
                  required min={1} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
              </div>
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
        </div>
      )}

      {/* ─── Modal Import PDF UE/EC ────────────────────────── */}
      {showImportModal && (
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

            {/* Drop zone */}
            <div onDragOver={(e) => { e.preventDefault(); setImportDragOver(true); }} onDragLeave={() => setImportDragOver(false)} onDrop={handleImportDrop}
              className={`border-2 border-dashed rounded-xl p-10 text-center transition-all cursor-pointer ${importDragOver ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-primary/40'} ${importFile ? 'bg-surface-container-low' : ''}`}
              onClick={() => importFileRef.current?.click()}>
              <input ref={importFileRef} type="file" accept=".pdf" className="hidden" onChange={(e) => {
                const f = e.target.files[0]; if (f) { setImportFile(f); setImportError(''); }
              }} />
              {!importFile ? (
                <>
                  <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                    <FiUpload className="text-2xl text-primary" />
                  </div>
                  <h3 className="text-sm font-semibold text-on-surface mb-1">Importez un fichier PDF</h3>
                  <p className="text-xs text-on-surface-variant mb-4">Analyse par IA Gemini — ou <span className="text-primary font-semibold cursor-pointer hover:underline">parcourez</span></p>
                  <p className="text-[10px] text-on-surface-variant/60">PDF uniquement — 10 Mo max</p>
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
                <FiUpload /> Analyser avec l'IA
              </button>
            )}

            {/* Loading */}
            {importUploading && (
              <div className="mt-6 bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10 text-center">
                <FiLoader className="animate-spin mx-auto text-primary text-2xl mb-3" />
                <p className="font-semibold text-primary text-sm">Analyse IA en cours...</p>
                <p className="text-xs text-on-surface-variant mt-1">Redirection vers la page d'analyse</p>
              </div>
            )}

            <div className="mt-6 bg-surface-container-high rounded-xl p-4">
              <h4 className="text-xs font-bold text-primary mb-2">Informations</h4>
              <p className="text-[11px] text-on-surface-variant">Le fichier PDF sera analysé par l'IA Gemini pour extraire automatiquement les UE et EC. Vous pourrez valider les données avant l'import final.</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
