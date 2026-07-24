import { useState } from 'react';
import { FiPlus, FiEdit2, FiTrash2, FiBook, FiFilter, FiLoader } from 'react-icons/fi';
import Modal from '../../components/ui/Modal';
import Badge from '../../components/ui/Badge';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';

const NIVEAUX = ['L1', 'L2', 'L3', 'M1', 'M2'];

const NIVEAU_COLORS = {
  L1: 'bg-blue-100 text-blue-700',
  L2: 'bg-emerald-100 text-emerald-700',
  L3: 'bg-amber-100 text-amber-700',
  M1: 'bg-purple-100 text-purple-700',
  M2: 'bg-rose-100 text-rose-700',
};

export default function FilieresPage() {
  const { data: filieres, loading, refetch } = useApi('/admin/filieres');
  const [filtreNiveau, setFiltreNiveau] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ code: '', intitule: '', niveau: '' });
  const [saving, setSaving] = useState(false);

  const filtered = filtreNiveau
    ? (filieres || []).filter((f) => f.niveau === filtreNiveau)
    : (filieres || []);

  const openCreate = () => {
    setEditing(null);
    setForm({ code: '', intitule: '', niveau: '' });
    setShowModal(true);
  };

  const openEdit = (f) => {
    setEditing(f);
    setForm({ code: f.code, intitule: f.intitule, niveau: f.niveau || '' });
    setShowModal(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (editing) {
        await api.put(`/admin/filieres/${editing.id}`, form);
      } else {
        await api.post('/admin/filieres', form);
      }
      setShowModal(false);
      refetch();
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de l'enregistrement");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Supprimer cette filière ?')) return;
    try {
      await api.delete(`/admin/filieres/${id}`);
      refetch();
    } catch (err) {
      alert(err.response?.data?.message || 'Erreur lors de la suppression');
    }
  };

  return (
    <div>
      {/* En-tête */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Filières</h1>
          <p className="text-sm text-on-surface-variant">Gérez les filières et programmes</p>
        </div>
        <button onClick={openCreate} className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
          <FiPlus /> Nouvelle filière
        </button>
      </div>

      {/* Filtres par niveau */}
      <div className="flex items-center gap-2 mb-6 flex-wrap">
        <FiFilter size={14} className="text-on-surface-variant" />
        <button
          onClick={() => setFiltreNiveau('')}
          className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
            filtreNiveau === ''
              ? 'bg-primary text-white shadow-sm'
              : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container'
          }`}
        >
          Toutes
        </button>
        {NIVEAUX.map((n) => (
          <button
            key={n}
            onClick={() => setFiltreNiveau(n)}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
              filtreNiveau === n
                ? 'bg-primary text-white shadow-sm'
                : `${NIVEAU_COLORS[n]} hover:opacity-80`
            }`}
          >
            {n}
          </button>
        ))}
        {filtreNiveau && (
          <span className="text-xs text-on-surface-variant ml-2">
            {filtered.length} filière{filtered.length > 1 ? 's' : ''}
          </span>
        )}
      </div>

      {/* Liste */}
      {loading ? (
        <div className="text-center py-12 text-on-surface-variant">Chargement...</div>
      ) : filtered.length === 0 ? (
        <div className="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-xxl">
          Aucune filière trouvée pour ce niveau.
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filtered.map((f) => (
            <div key={f.id} className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm border border-outline-variant/5 hover:shadow-md hover:border-outline-variant/20 transition-all">
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-primary/5 rounded-xl">
                    <FiBook className="text-primary" size={18} />
                  </div>
                  <div>
                    <div className="flex items-center gap-2 mb-1">
                      <Badge variant="info">{f.code}</Badge>
                      {f.niveau && (
                        <span className={`px-2 py-0.5 rounded-md text-[10px] font-bold ${NIVEAU_COLORS[f.niveau] || 'bg-surface-container-high text-outline'}`}>
                          {f.niveau}
                        </span>
                      )}
                    </div>
                    <h3 className="font-bold text-primary text-sm">{f.intitule}</h3>
                  </div>
                </div>
                <div className="flex gap-1">
                  <button onClick={() => openEdit(f)} className="p-1.5 hover:bg-surface-container-high rounded-lg transition-colors">
                    <FiEdit2 className="text-on-surface-variant" size={14} />
                  </button>
                  <button onClick={() => handleDelete(f.id)} className="p-1.5 hover:bg-error/10 rounded-lg transition-colors">
                    <FiTrash2 className="text-error" size={14} />
                  </button>
                </div>
              </div>
              <div className="flex gap-4 text-xs text-on-surface-variant pt-3 border-t border-outline-variant/5">
                <span><strong className="text-primary">{f.etudiants_count ?? 0}</strong> étudiant{(f.etudiants_count ?? 0) > 1 ? 's' : ''}</span>
                <span><strong className="text-primary">{f.ues_count ?? 0}</strong> UE{(f.ues_count ?? 0) > 1 ? 's' : ''}</span>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal */}
      <Modal isOpen={showModal} onClose={() => setShowModal(false)} title={editing ? "Modifier la filière" : 'Nouvelle filière'}>
        <form onSubmit={handleSave} className="space-y-4">
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Code</label>
            <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all font-mono" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} placeholder="GL" required />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Nom complet</label>
            <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all" value={form.intitule} onChange={(e) => setForm({ ...form, intitule: e.target.value })} placeholder="Génie Logiciel" required />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Niveau</label>
            <select
              className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
              value={form.niveau}
              onChange={(e) => setForm({ ...form, niveau: e.target.value })}
              required
            >
              <option value="">Sélectionnez un niveau</option>
              {NIVEAUX.map((n) => (
                <option key={n} value={n}>{n}</option>
              ))}
            </select>
          </div>
          <div className="flex justify-end gap-3 pt-4">
            <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">Annuler</button>
            <button type="submit" disabled={saving} className="flex items-center justify-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50 transition-all">{saving && <FiLoader className="animate-spin" />}{saving ? 'Enregistrement...' : editing ? 'Modifier' : 'Créer'}</button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
