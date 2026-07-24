import { useState } from 'react';
import { FiPlus, FiEdit2, FiTrash2, FiCheck, FiCalendar, FiArrowRight, FiStar, FiLoader, FiCopy } from 'react-icons/fi';
import Badge from '../../components/ui/Badge';
import Modal from '../../components/ui/Modal';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';
import { useToastCtx } from '../../context/ToastContext';

function formatDate(dateStr) {
  if (!dateStr) return '—';
  try {
    return new Date(dateStr).toLocaleDateString('fr-FR', {
      day: 'numeric', month: 'long', year: 'numeric',
    });
  } catch {
    return dateStr;
  }
}

export default function AcademicYearsPage() {
  const { data: years, loading, refetch } = useApi('/admin/annees-academiques');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState({ libelle: '', date_debut: '', date_fin: '' });
  const [saving, setSaving] = useState(false);
  const [reconduireLoading, setReconduireLoading] = useState(null);
  const { addToast } = useToastCtx();
  const activeYear = (years || []).find(y => y.active);

  const openCreate = () => {
    setEditing(null);
    setForm({ libelle: '', date_debut: '', date_fin: '' });
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

  const handleReconduire = async (targetId, activeId) => {
    setReconduireLoading(targetId);
    try {
      const { data } = await api.post('/admin/filieres/reconduire', {
        source_annee_id: activeId,
        target_annee_id: targetId,
      });
      addToast?.(data?.message || 'Filières reconduites avec succès', 'success');
      refetch();
    } catch (err) {
      addToast?.(err.response?.data?.message || 'Erreur lors de la reconduction', 'error');
    } finally {
      setReconduireLoading(null);
    }
  };

  return (
    <div>
      {/* En-tête */}
      <div className="flex items-center justify-between mb-6">
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
      ) : !years || years.length === 0 ? (
        <div className="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-xxl">
          Aucune année académique. Créez-en une !
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {(years || []).map((year) => (
            <div
              key={year.id}
              className={`bg-surface-container-lowest rounded-xxl p-5 shadow-sm border-2 transition-all ${
                year.active
                  ? 'border-primary/30 bg-primary/[0.02]'
                  : 'border-transparent hover:border-outline-variant/20'
              }`}
            >
              <div className="flex items-start justify-between mb-4">
                <div className="flex items-center gap-3">
                  <div className={`p-2.5 rounded-xl ${
                    year.active ? 'bg-primary/10' : 'bg-surface-container-high'
                  }`}>
                    <FiCalendar className={`${year.active ? 'text-primary' : 'text-outline'}`} size={20} />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <h3 className="font-bold text-primary text-base">{year.annee || year.libelle}</h3>
                      {year.active && (
                        <span className="flex items-center gap-1 px-2 py-0.5 bg-secondary/10 text-secondary rounded-full text-[10px] font-bold">
                          <FiStar size={10} /> Active
                        </span>
                      )}
                    </div>
                    <p className="text-[11px] text-on-surface-variant mt-0.5">Année académique</p>
                  </div>
                </div>

                <div className="flex gap-1">
                  {!year.active && (
                    <button
                      onClick={() => setActive(year.id)}
                      className="p-2 hover:bg-secondary/10 rounded-lg transition-colors"
                      title="Définir comme active"
                    >
                      <FiStar className="text-on-surface-variant hover:text-secondary" size={15} />
                    </button>
                  )}
                  <button
                    onClick={() => openEdit(year)}
                    className="p-2 hover:bg-surface-container-high rounded-lg transition-colors"
                    title="Modifier"
                  >
                    <FiEdit2 className="text-on-surface-variant" size={15} />
                  </button>
                  <button
                    onClick={() => handleDelete(year.id)}
                    className="p-2 hover:bg-error/10 rounded-lg transition-colors"
                    title="Supprimer"
                  >
                    <FiTrash2 className="text-error" size={15} />
                  </button>
                </div>
              </div>

              {/* Période */}
              <div className="bg-surface-container-high rounded-xl p-3.5">
                <div className="flex items-center gap-3 text-sm">
                  <div className="flex-1">
                    <p className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider mb-0.5">Début</p>
                    <p className="font-semibold text-on-surface">{formatDate(year.date_debut)}</p>
                  </div>
                  <div className="text-outline flex-shrink-0">
                    <FiArrowRight size={16} />
                  </div>
                  <div className="flex-1 text-right">
                    <p className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider mb-0.5">Fin</p>
                    <p className="font-semibold text-on-surface">{formatDate(year.date_fin)}</p>
                  </div>
                </div>
                {!year.active && activeYear && (
                  <button
                    onClick={() => handleReconduire(year.id, activeYear.id)}
                    disabled={reconduireLoading === year.id}
                    className="mt-3 w-full flex items-center justify-center gap-2 px-3 py-2 bg-primary/10 text-primary rounded-lg text-xs font-semibold hover:bg-primary/20 transition-all disabled:opacity-50"
                  >
                    {reconduireLoading === year.id ? <FiLoader className="animate-spin" size={12} /> : <FiCopy size={12} />}
                    {reconduireLoading === year.id ? 'Reconduction...' : 'Reconduire les filières'}
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal */}
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
            <button type="submit" disabled={saving} className="flex items-center justify-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50 transition-all">{saving && <FiLoader className="animate-spin" />}{saving ? 'Enregistrement...' : editing ? 'Modifier' : 'Créer'}</button>
          </div>
        </form>
      </Modal>
    </div>
  );
}
