import { useState } from 'react';
import { FiPlus, FiEdit2, FiTrash2, FiCheck } from 'react-icons/fi';
import Badge from '../../components/ui/Badge';
import Modal from '../../components/ui/Modal';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';

export default function AcademicYearsPage() {
  const { data: years, loading, refetch } = useApi('/admin/annees-academiques');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ libelle: '', date_debut: '', date_fin: '' });
  const [saving, setSaving] = useState(false);

  const openCreate = () => {
    setEditing(null);
    setForm({ annee: '', date_debut: '', date_fin: '' });
    setShowModal(true);
  };

  const openEdit = (y) => {
    setEditing(y);
    setForm({ libelle: y.libelle, date_debut: y.date_debut || '', date_fin: y.date_fin || '' });
    setShowModal(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (editing) {
        await api.put(`/admin/annees-academiques/${editing.id}`, form);
      } else {
        await api.post('/admin/annees-academiques', form);
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
    if (!window.confirm("Supprimer cette année académique ?")) return;
    try {
      await api.delete(`/admin/annees-academiques/${id}`);
      refetch();
    } catch (err) {
      alert(err.response?.data?.message || 'Erreur lors de la suppression');
    }
  };

  const setActive = async (id) => {
    try {
      await api.patch(`/admin/annees-academiques/${id}/activate`);
      refetch();
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de l'activation");
    }
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Années Académiques</h1>
          <p className="text-sm text-on-surface-variant">Gérez les années académiques</p>
        </div>
        <button onClick={openCreate} className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
          <FiPlus /> Nouvelle année
        </button>
      </div>

      {loading ? (
        <div className="text-center py-12 text-on-surface-variant">Chargement...</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {(years || []).map((year) => (
            <div key={year.id} className={`bg-surface-container-lowest rounded-xxl p-5 shadow-sm border transition-all ${year.active ? 'border-primary' : 'border-transparent'}`}>
              <div className="flex items-start justify-between mb-3">
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="font-bold text-primary">{year.annee}</h3>
                    {year.active && <Badge variant="success">Active</Badge>}
                  </div>
                  <p className="text-xs text-on-surface-variant mt-1">
                    {year.date_debut} → {year.date_fin}
                  </p>
                </div>
                <div className="flex gap-1">
                  {!year.active && (
                    <button onClick={() => setActive(year.id)} className="p-2 hover:bg-surface-container-high rounded-lg transition-colors" title="Définir comme active">
                      <FiCheck className="text-on-surface-variant" size={16} />
                    </button>
                  )}
                  <button onClick={() => openEdit(year)} className="p-2 hover:bg-surface-container-high rounded-lg transition-colors">
                    <FiEdit2 className="text-on-surface-variant" size={16} />
                  </button>
                  <button onClick={() => handleDelete(year.id)} className="p-2 hover:bg-error/10 rounded-lg transition-colors">
                    <FiTrash2 className="text-error" size={16} />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <Modal isOpen={showModal} onClose={() => setShowModal(false)} title={editing ? "Modifier l'année" : 'Nouvelle année académique'}>
        <form onSubmit={handleSave} className="space-y-4">
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Libellé</label>
            <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all" value={form.libelle} onChange={(e) => setForm({ ...form, libelle: e.target.value })} placeholder="Ex: 2026-2027" required />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Date début</label>
              <input type="date" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all" value={form.date_debut} onChange={(e) => setForm({ ...form, date_debut: e.target.value })} />
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Date fin</label>
              <input type="date" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all" value={form.date_fin} onChange={(e) => setForm({ ...form, date_fin: e.target.value })} />
            </div>
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
