import { useState } from 'react';
import { FiPlus, FiEdit2, FiTrash2, FiBook } from 'react-icons/fi';
import Modal from '../../components/ui/Modal';
import Badge from '../../components/ui/Badge';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';

export default function FilieresPage() {
  const { data: filieres, loading, refetch } = useApi('/admin/filieres');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ code: '', intitule: '', niveau: '' });
  const [saving, setSaving] = useState(false);

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
      alert(err.response?.data?.message || 'Erreur lors de l\'enregistrement');
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
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Filières</h1>
          <p className="text-sm text-on-surface-variant">Gérez les filières et programmes</p>
        </div>
        <button onClick={openCreate} className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
          <FiPlus /> Nouvelle filière
        </button>
      </div>

      {loading ? (
        <div className="text-center py-12 text-on-surface-variant">Chargement...</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {(filieres || []).map((f) => (
            <div key={f.id} className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm">
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-primary/5 rounded-xl">
                    <FiBook className="text-primary" size={18} />
                  </div>
                  <div>
                    <Badge variant="info">{f.code}</Badge>
                    <h3 className="font-bold text-primary text-sm mt-1">{f.intitule}</h3>
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
                <span><strong className="text-primary">{f.etudiants_count ?? 0}</strong> étudiants</span>
                <span><strong className="text-primary">{f.ues_count ?? 0}</strong> UE</span>
              </div>
            </div>
          ))}
        </div>
      )}

      <Modal isOpen={showModal} onClose={() => setShowModal(false)} title={editing ? 'Modifier la filière' : 'Nouvelle filière'}>
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
            <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all" value={form.niveau} onChange={(e) => setForm({ ...form, niveau: e.target.value })} placeholder="L3, M1, M2..." />
          </div>
          <div className="flex justify-end gap-3 pt-4">
            <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">Annuler</button>
            <button type="submit" disabled={saving} className="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50 transition-all">{saving ? 'Enregistrement...' : editing ? 'Modifier' : 'Créer'}</button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
