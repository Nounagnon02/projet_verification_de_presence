import { useState, useEffect, useCallback } from 'react';
import { FiPlus, FiEdit2, FiTrash2, FiAlertTriangle, FiLoader, FiRefreshCw } from 'react-icons/fi';
import api from '../../api/axios';
import SearchInput from '../../components/ui/SearchInput';
import Pagination from '../../components/ui/Pagination';
import Modal from '../../components/ui/Modal';
import { useToastCtx } from '../../context/ToastContext';

const StudentManagementPage = () => {
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ nom: '', prenom: '', email: '', matricule: '', filiere_id: '', annee_id: '' });
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const [deleteId, setDeleteId] = useState(null);
  const [filieres, setFilieres] = useState([]);
  const [annees, setAnnees] = useState([]);
  const { addToast } = useToastCtx();

  // Charger les filières et années académiques pour les selects
  useEffect(() => {
    api.get('/admin/filieres').then(({ data }) => {
      if (data.success) setFilieres(data.data || []);
      else if (Array.isArray(data)) setFilieres(data);
      else if (data.data) setFilieres(data.data);
    }).catch(() => {});

    api.get('/admin/annees-academiques').then(({ data }) => {
      if (data.success) setAnnees(data.data || []);
      else if (Array.isArray(data)) setAnnees(data);
      else if (data.data) setAnnees(data.data);
    }).catch(() => {});
  }, []);

  const fetchStudents = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = { page, per_page: 15 };
      if (search.trim()) params.search = search;
      const response = await api.get('/admin/students', { params });
      const result = response.data;

      if (result.success) {
        setStudents(result.data ?? []);
        setPagination(result.meta ?? null);
      } else {
        setError(result.message || 'Erreur lors du chargement');
        setStudents([]);
      }
    } catch (err) {
      const message = err.response?.data?.message || err.message || 'Erreur de connexion au serveur';
      setError(message);
      setStudents([]);
    } finally {
      setLoading(false);
    }
  }, [page, search]);

  useEffect(() => { fetchStudents(); }, [fetchStudents]);

  const openCreate = () => {
    setEditing(null);
    setForm({ nom: '', prenom: '', email: '', matricule: '', filiere_id: '', annee_id: '' });
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
    if (!form.filiere_id) {
      setFormError('Veuillez sélectionner une filière');
      return;
    }
    if (!form.annee_id) {
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
        matricule: form.matricule || undefined,
        filiere_id: parseInt(form.filiere_id, 10),
        annee_id: parseInt(form.annee_id, 10),
      };

      if (editing) {
        await api.put(`/admin/students/${editing.id}`, payload);
        addToast?.('Étudiant modifié avec succès', 'success');
      } else {
        await api.post('/admin/students', payload);
        addToast?.('Étudiant créé avec succès', 'success');
      }
      setShowModal(false);
      fetchStudents();
    } catch (err) {
      const msg = err.response?.data?.message
        || err.response?.data?.errors
          ? Object.values(err.response.data.errors).flat().join(', ')
          : null
        || "Erreur lors de l'enregistrement";
      setFormError(msg);
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleteId) return;
    try {
      await api.delete(`/admin/students/${deleteId}`);
      addToast?.('Étudiant supprimé', 'success');
      setDeleteId(null);
      fetchStudents();
    } catch (err) {
      const msg = err.response?.data?.message || 'Erreur lors de la suppression';
      addToast?.(msg, 'error');
      setDeleteId(null);
    }
  };

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
          <button onClick={fetchStudents} className="p-2.5 hover:bg-surface-container-high rounded-xl transition-colors" title="Actualiser">
            <FiRefreshCw className={`text-on-surface-variant ${loading ? 'animate-spin' : ''}`} />
          </button>
          <button onClick={openCreate} className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all shadow-sm">
            <FiPlus /> Nouvel étudiant
          </button>
        </div>
      </div>

      <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Rechercher par nom, prénom, matricule..." className="mb-6 max-w-md" />

      {error && (
        <div className="mb-6 flex items-start gap-3 p-4 bg-error-container/30 rounded-xl text-on-error-container text-sm border border-error/10">
          <FiAlertTriangle className="mt-0.5 flex-shrink-0" />
          <div className="flex-1">
            <p className="font-semibold">Erreur de chargement</p>
            <p className="text-xs mt-1 opacity-80">{error}</p>
          </div>
          <button onClick={fetchStudents} className="px-3 py-1 bg-error/10 hover:bg-error/20 rounded-lg text-xs font-semibold transition-colors">
            Réessayer
          </button>
        </div>
      )}

      {deleteId && (
        <div className="mb-6 flex items-center gap-3 p-4 bg-error/10 rounded-xl text-error border border-error/10">
          <FiAlertTriangle />
          <p className="flex-1 text-sm">Supprimer cet étudiant ? Cette action est irréversible.</p>
          <button onClick={confirmDelete} className="px-4 py-1.5 bg-error text-white rounded-lg text-sm font-semibold">Confirmer</button>
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
                <td className="p-4 font-medium">{s.nom}</td>
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
          <Pagination pagination={pagination || { current_page: page, last_page: 1, from: 1, to: students.length, total: students.length }} onPageChange={setPage} />
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
              <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-all"
                value={form.nom} onChange={(e) => setForm({ ...form, nom: e.target.value })} />
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Prénom *</label>
              <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-all"
                value={form.prenom} onChange={(e) => setForm({ ...form, prenom: e.target.value })} />
            </div>
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Email *</label>
            <input type="email" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-all"
              value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Matricule</label>
            <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 font-mono border-b-2 border-transparent focus:border-primary transition-all"
              value={form.matricule} onChange={(e) => setForm({ ...form, matricule: e.target.value })} placeholder="22-XXXX-XXXX" />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Filière *</label>
              <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-all"
                value={form.filiere_id} onChange={(e) => setForm({ ...form, filiere_id: e.target.value })}>
                <option value="">Sélectionner une filière</option>
                {filieres.map((f) => (
                  <option key={f.id} value={f.id}>
                    {f.code} — {f.intitule}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Année académique *</label>
              <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 border-b-2 border-transparent focus:border-primary transition-all"
                value={form.annee_id} onChange={(e) => setForm({ ...form, annee_id: e.target.value })}>
                <option value="">Sélectionner une année</option>
                {annees.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.libelle || a.annee} {a.active || a.is_active ? '(Active)' : ''}
                  </option>
                ))}
              </select>
            </div>
          </div>
          <div className="flex justify-end gap-3 pt-4">
            <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">
              Annuler
            </button>
            <button type="submit" disabled={saving} className="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50">
              {saving ? 'Enregistrement...' : editing ? 'Modifier' : 'Créer'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
};

export default StudentManagementPage;
