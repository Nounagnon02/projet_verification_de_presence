import { useState, useEffect, useCallback, useRef } from 'react';
import { FiPlus, FiEdit2, FiTrash2, FiAlertTriangle, FiLoader, FiRefreshCw, FiUpload, FiCheck, FiFileText, FiTrendingUp } from 'react-icons/fi';
import api from '../../api/axios';
import SearchInput from '../../components/ui/SearchInput';
import Pagination from '../../components/ui/Pagination';
import Modal from '../../components/ui/Modal';
import useFiltresAcademiques from '../../hooks/useFiltresAcademiques';
import { useToastCtx } from '../../context/ToastContext';

const StudentManagementPage = () => {
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState('');
  const [pagination, setPagination] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ nom: '', prenom: '', email: '', matricule: '', filiere_id: '', annee_id: '', est_responsable: false });
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const [deleteId, setDeleteId] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const { addToast } = useToastCtx();

  // Filtres académiques en cascade : choisir une année restreint les filières,
  // qui restreignent à leur tour niveaux et semestres. Le hook porte aussi la
  // remise à zéro des filtres enfants, sans quoi on garderait une filière
  // absente de la nouvelle année et la liste se viderait sans explication.
  const [page, setPageCourante] = useState(1);

  const filtres = useFiltresAcademiques({ onChangement: () => setPageCourante(1) });
  const { annees, filieres, filieresToutes } = filtres;

  // Indépendant de l'année : un étudiant est responsable ou non, quelle que
  // soit la promotion. Le mettre en cascade n'aurait aucun sens.
  const [filtreResponsable, setFiltreResponsable] = useState('');

  // Promotion (passage d'année / de niveau d'une promotion entière)
  const [showPromote, setShowPromote] = useState(false);
  const [promoteForm, setPromoteForm] = useState({ from_filiere_id: '', to_filiere_id: '', to_annee_id: '' });
  const [promotePreview, setPromotePreview] = useState(null);
  const [promoteError, setPromoteError] = useState('');
  const [promoting, setPromoting] = useState(false);

  // Import CSV
  const [showImportModal, setShowImportModal] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importDragOver, setImportDragOver] = useState(false);
  const [importUploading, setImportUploading] = useState(false);
  const [importResult, setImportResult] = useState(null);
  const [importError, setImportError] = useState('');
  const importFileRef = useRef(null);

  const abortFetchRef = useRef(null);


  // Compteur de rechargement : les six actions qui rafraîchissaient la liste
  // l'incrémentent, au lieu d'appeler une fonction de chargement partagée. La
  // requête part d'un seul endroit, et l'AbortController déjà en place annule
  // la précédente à chaque relance.
  const [rechargement, setRechargement] = useState(0);
  const rafraichir = useCallback(() => setRechargement((n) => n + 1), []);

  useEffect(() => {
    (async () => {
      abortFetchRef.current?.abort();
      const controller = new AbortController();
      abortFetchRef.current = controller;
      setLoading(true);
      setError(null);
      try {
        const params = { page, per_page: 15 };
        if (search.trim()) params.search = search;
        if (filtres.annee) params.annee_id = filtres.annee;
        if (filtres.filiere) params.filiere_id = filtres.filiere;
        if (filtres.semestre) params.semestre = filtres.semestre;
        if (filtres.niveau) params.niveau = filtres.niveau;
        if (filtreResponsable) params.responsable = filtreResponsable;
        const response = await api.get('/admin/students', { params, signal: controller.signal });
        const result = response.data;

        if (result.success) {
          setStudents(result.data ?? []);
          setPagination(result.meta ?? null);
        } else {
          setError(result.message || 'Erreur lors du chargement');
          setStudents([]);
        }
      } catch (err) {
        if (err.name === 'CanceledError' || err.name === 'AbortError') return;
        const message = err.response?.data?.message || err.message || 'Erreur de connexion au serveur';
        setError(message);
        setStudents([]);
      } finally {
        setLoading(false);
      }
  
    })();

    return () => abortFetchRef.current?.abort();
  }, [page, search, filtres.annee, filtres.filiere, filtres.semestre, filtres.niveau, filtreResponsable, rechargement]);

  // L'inscription se fait toujours dans l'année active : on ne la fait pas
  // choisir, on l'impose (le serveur la ré-applique de toute façon).
  const activeYear = annees.find((a) => a.active || a.is_active) || null;

  const openCreate = () => {
    setEditing(null);
    setForm({ nom: '', prenom: '', email: '', matricule: '', filiere_id: '', annee_id: activeYear?.id?.toString() || '', est_responsable: false });
    setFormError('');
    setShowModal(true);
  };

  const openEdit = (s) => {
    setEditing(s);
    setForm({
      nom: s.nom || '',
      prenom: s.prenom || '',
      email: s.email || '',
      matricule: s.matricule || '',
      est_responsable: Boolean(s.est_responsable),
      filiere_id: s.filiere?.id?.toString() || s.filiere_id?.toString() || '',
      annee_id: s.annee?.id?.toString() || s.annee_id?.toString() || '',
    });
    setFormError('');
    setShowModal(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    if (!form.nom || !form.prenom || !form.email) {
      setFormError('Veuillez remplir les champs obligatoires (nom, prénom, email)');
      return;
    }
    if (!form.matricule) {
      setFormError('Le matricule est obligatoire.');
      return;
    }
    if (!form.filiere_id) {
      setFormError('Veuillez sélectionner une filière');
      return;
    }
    // À la création, l'année est imposée (année active). On bloque seulement
    // s'il n'existe aucune année active à laquelle rattacher l'étudiant.
    if (!editing && !activeYear) {
      setFormError("Aucune année académique active. Activez une année avant d'inscrire des étudiants.");
      return;
    }
    if (editing && !form.annee_id) {
      setFormError("Veuillez sélectionner une année académique");
      return;
    }
    setSaving(true);
    setFormError('');
    try {
      const payload = {
        nom: form.nom,
        prenom: form.prenom,
        email: form.email,
        matricule: form.matricule,
        filiere_id: parseInt(form.filiere_id, 10),
      };
      // L'année n'est envoyée qu'en édition : à la création, le serveur impose
      // l'année active.
      if (editing && form.annee_id) {
        payload.annee_id = parseInt(form.annee_id, 10);
      }
      if (editing) {
        payload.est_responsable = Boolean(form.est_responsable);
      }

      if (editing) {
        await api.put(`/admin/students/${editing.id}`, payload);
        addToast?.('Étudiant modifié avec succès', 'success');
      } else {
        await api.post('/admin/students', payload);
        addToast?.('Étudiant créé avec succès', 'success');
      }
      setShowModal(false);
      rafraichir();
    } catch (err) {
      const data = err.response?.data;
      const msg = (data?.errors ? Object.values(data.errors).flat().join(', ') : null)
        || data?.message
        || "Erreur lors de l'enregistrement";
      setFormError(msg);
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleteId) return;
    setDeleting(true);
    try {
      await api.delete(`/admin/students/${deleteId}`);
      addToast?.('Étudiant supprimé', 'success');
      setDeleteId(null);
      rafraichir();
    } catch (err) {
      const msg = err.response?.data?.message || 'Erreur lors de la suppression';
      addToast?.(msg, 'error');
      setDeleteId(null);
    } finally {
      setDeleting(false);
    }
  };

  // ─── Promotion ───────────────────────────────────────────

  const openPromote = () => {
    setPromoteForm({ from_filiere_id: '', to_filiere_id: '', to_annee_id: activeYear?.id?.toString() || '' });
    setPromotePreview(null);
    setPromoteError('');
    setShowPromote(true);
  };

  // Récupère le nombre d'étudiants concernés sans rien modifier.
  const previewPromotion = useCallback(async (fromId) => {
    if (!fromId) { setPromotePreview(null); return; }
    try {
      const { data } = await api.post('/admin/students/promote', {
        from_filiere_id: parseInt(fromId, 10),
        dry_run: true,
      });
      setPromotePreview(data?.data?.etudiants_concernes ?? null);
    } catch {
      setPromotePreview(null);
    }
  }, []);

  const handlePromote = async () => {
    if (!promoteForm.from_filiere_id || !promoteForm.to_filiere_id) {
      setPromoteError('Sélectionnez la filière de départ et la filière de destination.');
      return;
    }
    if (promoteForm.from_filiere_id === promoteForm.to_filiere_id) {
      setPromoteError('Les deux filières doivent être différentes.');
      return;
    }
    setPromoting(true);
    setPromoteError('');
    try {
      const { data } = await api.post('/admin/students/promote', {
        from_filiere_id: parseInt(promoteForm.from_filiere_id, 10),
        to_filiere_id: parseInt(promoteForm.to_filiere_id, 10),
        to_annee_id: promoteForm.to_annee_id ? parseInt(promoteForm.to_annee_id, 10) : undefined,
      });
      addToast?.(data?.message || 'Promotion effectuée', 'success');
      setShowPromote(false);
      rafraichir();
    } catch (err) {
      const d = err.response?.data;
      setPromoteError((d?.errors ? Object.values(d.errors).flat().join(', ') : null) || d?.message || 'Erreur lors de la promotion.');
    } finally {
      setPromoting(false);
    }
  };

  // ─── Import CSV ──────────────────────────────────────────

  const handleImportDrop = (e) => {
    e.preventDefault();
    setImportDragOver(false);
    const f = e.dataTransfer.files[0];
    if (!f) return;
    const ext = '.' + f.name.split('.').pop().toLowerCase();
    if (ext === '.csv' || ext === '.xlsx') {
      setImportFile(f); setImportError(''); setImportResult(null);
    } else setImportError('Format non supporté. Utilisez CSV ou XLSX.');
  };

  const handleImportUpload = async () => {
    if (!importFile) return;
    setImportUploading(true);
    setImportError('');
    setImportResult(null);
    const formData = new FormData();
    formData.append('file', importFile);
    try {
      const response = await api.post('/admin/import/students', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      const apiData = response.data;
      if (apiData.success && apiData.data) {
        setImportResult({
          success: true,
          imported: apiData.data.success ?? 0,
          total: apiData.data.total ?? 0,
          errors: apiData.data.errors || [],
        });
      } else if (apiData.data && typeof apiData.data.success === 'number') {
        setImportResult({
          success: apiData.data.success > 0,
          imported: apiData.data.success ?? 0,
          total: apiData.data.total ?? 0,
          errors: apiData.data.errors || [],
        });
      } else {
        setImportResult({
          success: false, imported: 0, total: 0,
          errors: [apiData.message || JSON.stringify(apiData)],
        });
      }
      rafraichir();
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Erreur lors de l\'import';
      setImportError(message);
      setImportResult(null);
    } finally {
      setImportUploading(false);
    }
  };

  const resetImport = () => { setImportFile(null); setImportResult(null); setImportError(''); if (importFileRef.current) importFileRef.current.value = ''; };

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Gestion des Étudiants</h1>
          <p className="text-sm text-on-surface-variant">
            {pagination?.total ?? students.length} étudiant(s) inscrit(s)
          </p>
        </div>
        <div className="flex items-center gap-2">
          <button onClick={rafraichir} className="p-2.5 hover:bg-surface-container-high rounded-xl transition-colors" title="Actualiser">
            <FiRefreshCw className={`text-on-surface-variant ${loading ? 'animate-spin' : ''}`} />
          </button>
          <button onClick={openCreate} className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all shadow-sm">
            <FiPlus /> Nouvel étudiant
          </button>
          <button onClick={() => { setShowImportModal(true); resetImport(); }} className="flex items-center gap-2 bg-secondary/10 text-secondary px-5 py-2.5 rounded-xl font-semibold text-sm hover:bg-secondary/20 transition-all shadow-sm">
            <FiUpload /> Import en masse
          </button>
          <button onClick={openPromote} className="flex items-center gap-2 bg-tertiary/10 text-tertiary px-5 py-2.5 rounded-xl font-semibold text-sm hover:bg-tertiary/20 transition-all shadow-sm" title="Passer une promotion à l'année/niveau suivant">
            <FiTrendingUp /> Promouvoir
          </button>
        </div>
      </div>

      {/* Filtres */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 mb-6">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 flex-1 min-w-[160px]">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Année académique</label>
            <select value={filtres.annee} onChange={(e) => filtres.setAnnee(e.target.value)}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
              <option value="">Toutes les années</option>
              {annees.map(a => <option key={a.id} value={a.id}>{a.libelle || a.annee}{a.active ? ' (Active)' : ''}</option>)}
            </select>
          </div>
          <div className="space-y-1 flex-1 min-w-[160px]">
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
          <div className="space-y-1 min-w-[120px]">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Semestre</label>
            <select value={filtres.semestre} onChange={(e) => filtres.setSemestre(e.target.value)}
              disabled={filtres.semestres.length === 0} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
              <option value="">Tous</option>
              {filtres.semestres.map(s => <option key={s} value={s}>Semestre {s}</option>)}
            </select>
          </div>
          <div className="space-y-1 min-w-[140px]">
            <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Responsable</label>
            <select value={filtreResponsable} onChange={(e) => { setFiltreResponsable(e.target.value); setPageCourante(1); }}
              className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed">
              <option value="">Tous les étudiants</option>
              <option value="1">Responsables uniquement</option>
              <option value="0">Non-responsables</option>
            </select>
          </div>
        </div>

        {/* Une année sans aucune filière est un cas courant en début
            d'exercice. Le dire évite de laisser croire à une panne devant un
            tableau vide. */}
        {filtres.anneeVide && (
          <p className="mt-3 text-xs text-on-surface-variant">
            Cette année académique ne contient encore aucune filière. Rattachez-lui
            des filières, ou choisissez une autre année.
          </p>
        )}
      </div>

      <SearchInput value={search} onChange={(v) => { setSearch(v); setPageCourante(1); }} placeholder="Rechercher par nom, prénom, matricule..." className="mb-6 max-w-md" />

      {error && (
        <div className="mb-6 flex items-start gap-3 p-4 bg-error-container/30 rounded-xl text-on-error-container text-sm border border-error/10">
          <FiAlertTriangle className="mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <p className="font-semibold">Erreur de chargement</p>
            <p className="text-xs mt-1 opacity-80">{error}</p>
          </div>
          <button onClick={rafraichir} className="px-3 py-1 bg-error/10 hover:bg-error/20 rounded-lg text-xs font-semibold transition-colors">
            Réessayer
          </button>
        </div>
      )}

      {deleteId && (
        <div className="mb-6 flex items-center gap-3 p-4 bg-error/10 rounded-xl text-error border border-error/10">
          <FiAlertTriangle />
          <p className="flex-1 text-sm">Supprimer cet étudiant ? Cette action est irréversible.</p>
          <button onClick={confirmDelete} disabled={deleting} className="flex items-center gap-2 px-4 py-1.5 bg-error text-white rounded-lg text-sm font-semibold disabled:opacity-50">
            {deleting && <FiLoader className="animate-spin" />} Confirmer</button>
          <button onClick={() => setDeleteId(null)} className="px-4 py-1.5 text-sm font-semibold hover:bg-surface-container-high rounded-lg transition-colors">Annuler</button>
        </div>
      )}

      <div className="bg-surface-container-lowest rounded-xxl shadow-sm border border-outline-variant/10 overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-outline-variant/10 text-on-surface-variant text-xs uppercase tracking-wider">
              <th className="text-left p-4 font-semibold">Matricule</th>
              <th className="text-left p-4 font-semibold">Nom</th>
              <th className="text-left p-4 font-semibold">Prénom</th>
              <th className="text-left p-4 font-semibold hidden md:table-cell">Email</th>
              <th className="text-left p-4 font-semibold hidden lg:table-cell">Filière</th>
              <th className="text-left p-4 font-semibold hidden lg:table-cell">Année</th>
              <th className="text-right p-4 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <tr>
                <td colSpan={7} className="p-8 text-center">
                  <FiLoader className="animate-spin mx-auto text-primary" />
                </td>
              </tr>
            ) : students.length === 0 ? (
              <tr>
                <td colSpan={7} className="p-8 text-center text-on-surface-variant">Aucun étudiant trouvé</td>
              </tr>
            ) : Array.isArray(students) && students.map((s) => (
              <tr key={s.id} className="border-b border-outline-variant/5 hover:bg-surface-container-low/50 transition-colors">
                <td className="p-4 font-mono text-xs font-semibold">{s.matricule}</td>
                <td className="p-4 font-medium">
                  <span className="flex items-center gap-2">
                    {s.nom}
                    {/* Paire de jetons opaques : les modificateurs d'opacité
                        (bg-primary/10) ne produisent aucun CSS sur les couleurs
                        déclarées en var(), le badge serait invisible. */}
                    {s.est_responsable && (
                      <span
                        title="Responsable de la promotion — accède au QR Code du cours en séance"
                        className="shrink-0 rounded-full bg-secondary-container px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-on-secondary-container">
                        Responsable
                      </span>
                    )}
                  </span>
                </td>
                <td className="p-4">{s.prenom}</td>
                <td className="p-4 text-on-surface-variant hidden md:table-cell">{s.email}</td>
                <td className="p-4 hidden lg:table-cell">{s.filiere?.code || s.filiere || '-'}</td>
                <td className="p-4 hidden lg:table-cell">
                  <span className="px-2 py-0.5 bg-primary/10 text-primary rounded text-xs font-semibold">{s.annee?.annee || s.annee || '-'}</span>
                </td>
                <td className="p-4 text-right">
                  <div className="flex items-center justify-end gap-2">
                    <button onClick={() => openEdit(s)} className="p-2 hover:bg-surface-container-high rounded-lg transition-colors">
                      <FiEdit2 className="text-on-surface-variant" />
                    </button>
                    <button onClick={() => setDeleteId(s.id)} className="p-2 hover:bg-error/10 rounded-lg transition-colors">
                      <FiTrash2 className="text-error" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="px-4 pb-4">
          <Pagination pagination={pagination || { current_page: page, last_page: 1, from: 1, to: students.length, total: students.length }} onPageChange={setPageCourante} />
        </div>
      </div>

      <Modal isOpen={showModal} onClose={() => setShowModal(false)} title={editing ? "Modifier l'étudiant" : 'Nouvel étudiant'} size="lg">
        <form onSubmit={handleSave} className="space-y-4">
          {formError && (
            <div className="flex items-center gap-2 p-3 bg-error/10 rounded-xl text-error text-sm">
              <FiAlertTriangle /> {formError}
            </div>
          )}
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Nom *</label>
              <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-colors"
                value={form.nom} onChange={(e) => setForm({ ...form, nom: e.target.value })} />
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Prénom *</label>
              <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-colors"
                value={form.prenom} onChange={(e) => setForm({ ...form, prenom: e.target.value })} />
            </div>
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Email *</label>
            <input type="email" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-colors"
              value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Matricule *</label>
            <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 font-mono border-b-2 border-transparent focus:border-primary transition-colors"
              value={form.matricule} onChange={(e) => setForm({ ...form, matricule: e.target.value })} placeholder="22-XXXX-XXXX" required />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Filière *</label>
              <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-colors"
                value={form.filiere_id} onChange={(e) => setForm({ ...form, filiere_id: e.target.value })}>
                <option value="">Sélectionner une filière</option>
                {filieresToutes.map((f) => (
                  <option key={f.id} value={f.id}>
                    {f.code} — {f.intitule}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Année académique</label>
              {editing ? (
                // En édition, l'année reste modifiable (le changement d'année
                // relève d'une promotion — voir évolution à venir).
                <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-colors"
                  value={form.annee_id} onChange={(e) => setForm({ ...form, annee_id: e.target.value })}>
                  <option value="">Sélectionner une année</option>
                  {annees.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.libelle || a.annee} {a.active || a.is_active ? '(Active)' : ''}
                    </option>
                  ))}
                </select>
              ) : (
                // À la création, l'année active est imposée : affichage seul.
                <div className="w-full px-3 py-2.5 bg-surface-container rounded-lg text-sm text-on-surface-variant border-b-2 border-transparent">
                  {activeYear ? `${activeYear.libelle || activeYear.annee} (active)` : 'Aucune année active'}
                </div>
              )}
            </div>
          </div>
          {editing && (
            <label className="flex items-start gap-3 rounded-xl bg-surface-container-high px-3 py-3 cursor-pointer">
              <input type="checkbox" checked={form.est_responsable}
                onChange={(e) => setForm({ ...form, est_responsable: e.target.checked })}
                className="mt-0.5 h-4 w-4 accent-primary" />
              <span>
                <span className="block text-sm font-semibold text-on-surface">Responsable de la promotion</span>
                <span className="block text-xs text-on-surface-variant">
                  Donne accès, dans l'application mobile, à l'onglet affichant le QR Code du cours en
                  séance. Le responsable peut le présenter et le partager, jamais le générer.
                </span>
              </span>
            </label>
          )}
          <div className="flex justify-end gap-3 pt-4">
            <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">
              Annuler
            </button>
            <button type="submit" disabled={saving} className="flex items-center justify-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50">
              {saving && <FiLoader className="animate-spin" />}{saving ? 'Enregistrement...' : editing ? 'Modifier' : 'Créer'}
            </button>
          </div>
        </form>
      </Modal>

      {/* ─── Modal Promotion ───────────────────────────── */}
      <Modal isOpen={showPromote} onClose={() => setShowPromote(false)} title="Promouvoir une promotion" size="lg">
        <div className="space-y-4">
          <p className="text-sm text-on-surface-variant">
            Fait passer tous les étudiants d'une filière vers une autre (ex. L1 → L2), et
            réinscrit chacun aux cours de sa nouvelle filière. L'identifiant de connexion des
            étudiants reste inchangé.
          </p>

          {promoteError && (
            <div className="flex items-start gap-2 p-3 bg-error/10 text-error rounded-lg text-sm">
              <FiAlertTriangle className="mt-0.5 shrink-0" /> <span>{promoteError}</span>
            </div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Filière de départ *</label>
              <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
                value={promoteForm.from_filiere_id}
                onChange={(e) => { const v = e.target.value; setPromoteForm({ ...promoteForm, from_filiere_id: v }); previewPromotion(v); }}>
                <option value="">Sélectionner...</option>
                {filieresToutes.map((f) => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
              </select>
              {promotePreview !== null && (
                <p className="text-xs text-on-surface-variant">{promotePreview} étudiant(s) concerné(s)</p>
              )}
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Filière de destination *</label>
              <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
                value={promoteForm.to_filiere_id}
                onChange={(e) => setPromoteForm({ ...promoteForm, to_filiere_id: e.target.value })}>
                <option value="">Sélectionner...</option>
                {filieresToutes.filter((f) => f.id?.toString() !== promoteForm.from_filiere_id).map((f) => (
                  <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Année de destination</label>
            <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
              value={promoteForm.to_annee_id}
              onChange={(e) => setPromoteForm({ ...promoteForm, to_annee_id: e.target.value })}>
              <option value="">Conserver l'année actuelle</option>
              {annees.map((a) => (
                <option key={a.id} value={a.id}>{a.libelle || a.annee} {a.active || a.is_active ? '(active)' : ''}</option>
              ))}
            </select>
          </div>

          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={() => setShowPromote(false)} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">
              Annuler
            </button>
            <button type="button" onClick={handlePromote} disabled={promoting}
              className="flex items-center justify-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50">
              {promoting && <FiLoader className="animate-spin" />}{promoting ? 'Promotion...' : 'Promouvoir'}
            </button>
          </div>
        </div>
      </Modal>

      {/* ─── Modal Import CSV ───────────────────────────── */}
      {showImportModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          onClick={() => { if (!importUploading) { setShowImportModal(false); resetImport(); } }}>
          <div className="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto"
            onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-lg font-bold text-primary">Import en masse</h2>
              <button onClick={() => { setShowImportModal(false); resetImport(); }} disabled={importUploading}
                className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <span className="text-2xl leading-none text-outline">&times;</span>
              </button>
            </div>

            {/* Drop zone */}
            <div onDragOver={(e) => { e.preventDefault(); setImportDragOver(true); }} onDragLeave={() => setImportDragOver(false)} onDrop={handleImportDrop}
              className={`border-2 border-dashed rounded-xl p-10 text-center transition-all cursor-pointer ${importDragOver ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-primary/40'} ${importFile ? 'bg-surface-container-low' : ''}`}
              onClick={() => importFileRef.current?.click()}>
              <input ref={importFileRef} type="file" accept=".csv,.xlsx" className="hidden" onChange={(e) => {
                const f = e.target.files[0]; if (f) { setImportFile(f); setImportError(''); setImportResult(null); }
              }} />
              {!importFile ? (
                <>
                  <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                    <FiUpload className="text-2xl text-primary" />
                  </div>
                  <h3 className="text-sm font-semibold text-on-surface mb-1">Importez un fichier CSV</h3>
                  <p className="text-xs text-on-surface-variant mb-4">ou <span className="text-primary font-semibold cursor-pointer hover:underline">parcourez</span></p>
                  <p className="text-[10px] text-on-surface-variant/60">CSV ou XLSX — 5 Mo max</p>
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

            {/* Résultat */}
            {importResult && (
              <div className={`mt-4 p-4 rounded-xl flex items-start gap-3 ${importResult.success ? 'bg-secondary-container/30 border border-secondary/10' : 'bg-error-container/30 border border-error/10'}`}>
                {importResult.success ? <FiCheck className="text-secondary text-lg mt-0.5 flex-shrink-0" /> : <FiAlertTriangle className="text-error text-lg mt-0.5 flex-shrink-0" />}
                <div className="text-sm flex-1">
                  <p className="font-semibold">{importResult.success ? 'Import terminé avec succès' : 'Erreurs lors de l\'import'}</p>
                  <p className="text-on-surface-variant text-xs mt-1">
                    {importResult.imported}/{importResult.total} étudiants importés
                    {importResult.errors?.length > 0 && ` (${importResult.errors.length} erreur${importResult.errors.length > 1 ? 's' : ''})`}
                  </p>
                  {importResult.errors?.length > 0 && (
                    <div className="mt-3 space-y-1 max-h-32 overflow-y-auto">
                      {importResult.errors.map((e, i) => (
                        <p key={i} className="text-xs text-on-error-container/70 bg-error-container/20 p-1.5 rounded">
                          {typeof e === 'string' ? e : `${e.row || 'Ligne ' + (i+1)} : ${Array.isArray(e.errors) ? e.errors.join(', ') : e.errors || 'Erreur'}`}
                        </p>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}

            {importFile && !importResult && !importUploading && (
              <button onClick={handleImportUpload}
                className="mt-6 w-full flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all">
                <FiUpload /> Importer les étudiants
              </button>
            )}

            {importUploading && (
              <div className="mt-6 bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10 text-center">
                <FiLoader className="animate-spin mx-auto text-primary text-2xl mb-3" />
                <p className="font-semibold text-primary text-sm">Analyse du fichier en cours...</p>
                <p className="text-xs text-on-surface-variant mt-1">Veuillez patienter</p>
              </div>
            )}

            {/* Format info */}
            <div className="mt-6 bg-surface-container-high rounded-xl p-4">
              <h4 className="text-xs font-bold text-primary mb-2">Format attendu</h4>
              <p className="text-[11px] text-on-surface-variant">Colonnes : <span className="font-mono font-medium text-primary">nom, prenom, email, matricule, filiere_code, annee_libelle</span></p>
              <p className="text-[11px] text-on-surface-variant mt-1">CSV avec séparateur virgule, encodage UTF-8.</p>
            </div>

            {importResult && (
              <button onClick={() => { setShowImportModal(false); resetImport(); }}
                className="mt-4 w-full py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all">
                Fermer
              </button>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default StudentManagementPage;
