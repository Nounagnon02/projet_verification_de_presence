import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiCheck, FiChevronLeft, FiChevronRight, FiBook, FiInfo } from 'react-icons/fi';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';

const steps = [
  { id: 1, label: 'Informations', icon: <FiInfo /> },
  { id: 2, label: 'UEs', icon: <FiBook /> },
  { id: 3, label: 'Récapitulatif', icon: <FiCheck /> },
];

export default function CreateFilierePage() {
  const navigate = useNavigate();
  const [step, setStep] = useState(1);
  const [form, setForm] = useState({ code: '', name: '', description: '', responsable: '', niveau: 'L3' });
  const [selectedUeIds, setSelectedUeIds] = useState([]);
  const [saving, setSaving] = useState(false);

  const { data: availableUes } = useApi('/admin/ues');

  const addUe = (id) => setSelectedUeIds(prev => prev.includes(id) ? prev : [...prev, id]);
  const removeUe = (id) => setSelectedUeIds(prev => prev.filter(u => u !== id));

  const selectedUes = (availableUes || []).filter(u => selectedUeIds.includes(u.id));
  const totalCredits = selectedUes.reduce((sum, u) => sum + (u.volume_horaire || 0), 0);

  const canNext = () => {
    if (step === 1) return form.code && form.name;
    if (step === 2) return selectedUeIds.length > 0;
    return true;
  };

  const handleCreate = async () => {
    setSaving(true);
    try {
      await api.post('/admin/filieres', {
        code: form.code,
        intitule: form.name,
        niveau: form.niveau,
      });
      navigate('/settings/filieres');
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de la création");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-primary font-headline">Créer une filière</h1>
        <p className="text-sm text-on-surface-variant mt-1">Configurez une nouvelle filière avec ses unités d'enseignement</p>
      </div>

      {/* Step indicator */}
      <div className="flex items-center gap-2 mb-10">
        {steps.map((s, i) => (
          <div key={s.id} className="flex items-center gap-2 flex-1">
            <div className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all ${
              step === s.id ? 'bg-primary text-white shadow-sm' :
              step > s.id ? 'bg-secondary/10 text-secondary' :
              'bg-surface-container-high text-on-surface-variant'
            }`}>
              <span className={`${step > s.id ? 'bg-secondary text-white' : step === s.id ? 'bg-white/20' : 'bg-surface-container'} w-5 h-5 rounded-lg flex items-center justify-center text-[10px]`}>
                {step > s.id ? <FiCheck size={12} /> : s.id}
              </span>
              <span className="hidden sm:inline">{s.label}</span>
            </div>
            {i < steps.length - 1 && <div className={`flex-1 h-0.5 rounded ${step > s.id ? 'bg-secondary' : 'bg-outline-variant/20'}`} />}
          </div>
        ))}
      </div>

      {/* Step 1: Basic info */}
      {step === 1 && (
        <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10 space-y-5">
          <h2 className="font-bold text-primary font-headline">Informations générales</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Code <span className="text-error">*</span></label>
              <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all font-mono"
                value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} placeholder="GL" />
            </div>
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Nom complet <span className="text-error">*</span></label>
              <input className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
                value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Génie Logiciel" />
            </div>
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-semibold text-on-surface-variant">Niveau</label>
            <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
              value={form.niveau} onChange={(e) => setForm({ ...form, niveau: e.target.value })}>
              <option value="L1">Licence 1</option>
              <option value="L2">Licence 2</option>
              <option value="L3">Licence 3</option>
              <option value="M1">Master 1</option>
              <option value="M2">Master 2</option>
            </select>
          </div>
        </div>
      )}

      {/* Step 2: UE selection */}
      {step === 2 && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
            <h2 className="font-bold text-primary font-headline text-sm mb-4">UEs disponibles</h2>
            <div className="space-y-2">
              {(availableUes || []).length === 0 ? (
                <p className="text-xs text-on-surface-variant text-center py-4">Aucune UE disponible</p>
              ) : (availableUes || []).map((ue) => {
                const isSelected = selectedUeIds.includes(ue.id);
                return (
                  <div key={ue.id} className={`p-3 rounded-xl border transition-all ${isSelected ? 'border-primary bg-primary/[0.02]' : 'border-outline-variant/10 hover:border-primary/30'}`}>
                    <div className="flex items-start justify-between">
                      <div className="flex-1 min-w-0">
                        <h4 className="text-xs font-semibold text-primary">{ue.intitule}</h4>
                        <p className="text-[10px] font-mono text-on-surface-variant mt-0.5">{ue.code} · {ue.volume_horaire || 0}h</p>
                      </div>
                      <button onClick={() => isSelected ? removeUe(ue.id) : addUe(ue.id)}
                        className={`shrink-0 px-3 py-1 rounded-lg text-[10px] font-semibold transition-all ${
                          isSelected ? 'bg-error/10 text-error' : 'bg-primary/10 text-primary hover:bg-primary/20'
                        }`}>
                        {isSelected ? 'Retirer' : 'Ajouter'}
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-bold text-primary font-headline text-sm">UEs sélectionnées</h2>
              <span className="text-xs text-on-surface-variant">{selectedUes.length} UE{selectedUes.length !== 1 ? 's' : ''}</span>
            </div>
            {selectedUes.length === 0 ? (
              <div className="text-center py-8 text-on-surface-variant">
                <FiBook className="mx-auto mb-2 opacity-40" size={24} />
                <p className="text-xs">Aucune UE sélectionnée</p>
              </div>
            ) : (
              <div className="space-y-2">
                {selectedUes.map((ue) => (
                  <div key={ue.id} className="flex items-center justify-between p-2.5 bg-surface-container-high rounded-lg">
                    <div className="min-w-0">
                      <p className="text-xs font-medium text-on-surface">{ue.intitule}</p>
                      <p className="text-[10px] text-on-surface-variant">{ue.code}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-[10px] text-on-surface-variant">{ue.volume_horaire || 0}h</span>
                      <button onClick={() => removeUe(ue.id)} className="text-error/70 hover:text-error text-xs">&times;</button>
                    </div>
                  </div>
                ))}
                <div className="flex justify-between pt-2 text-xs font-semibold text-primary border-t border-outline-variant/10">
                  <span>Total heures</span>
                  <span>{totalCredits}h</span>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Step 3: Review */}
      {step === 3 && (
        <div className="space-y-6">
          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
            <h2 className="font-bold text-primary font-headline text-sm mb-4">Récapitulatif</h2>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-on-surface-variant text-xs">Code</span><p className="font-semibold text-primary font-mono">{form.code}</p></div>
              <div><span className="text-on-surface-variant text-xs">Nom</span><p className="font-semibold text-primary">{form.name}</p></div>
              <div><span className="text-on-surface-variant text-xs">Niveau</span><p className="font-semibold">{form.niveau}</p></div>
            </div>
          </div>

          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
            <div className="flex items-center justify-between mb-4">
              <h2 className="font-bold text-primary font-headline text-sm">UEs sélectionnées ({selectedUes.length})</h2>
              <span className="text-xs text-on-surface-variant">{totalCredits}h total</span>
            </div>
            <div className="space-y-2">
              {selectedUes.map((ue) => (
                <div key={ue.id} className="flex items-center justify-between p-2.5 bg-surface-container-high rounded-lg">
                  <span className="text-xs font-medium">{ue.intitule}</span>
                  <span className="text-[10px] text-on-surface-variant">{ue.code} · {ue.volume_horaire || 0}h</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Navigation buttons */}
      <div className="flex items-center justify-between mt-8">
        <button disabled={step === 1}
          onClick={() => setStep(step - 1)}
          className={`flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all ${step === 1 ? 'text-on-surface-variant/30 cursor-not-allowed' : 'bg-surface-container-high text-on-surface hover:bg-surface-container'}`}>
          <FiChevronLeft /> Précédent
        </button>
        {step < 3 ? (
          <button disabled={!canNext()}
            onClick={() => setStep(step + 1)}
            className={`flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold transition-all ${canNext() ? 'bg-primary text-white hover:opacity-90' : 'bg-primary/30 text-white/50 cursor-not-allowed'}`}>
            Suivant <FiChevronRight />
          </button>
        ) : (
          <button onClick={handleCreate} disabled={saving}
            className="flex items-center gap-2 px-6 py-2.5 bg-secondary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50 transition-all">
            <FiCheck /> {saving ? 'Création...' : 'Confirmer la création'}
          </button>
        )}
      </div>
    </div>
  );
}
