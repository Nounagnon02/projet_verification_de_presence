import { useState, useEffect, useCallback } from 'react';
import {
  FiPlus, FiEdit2, FiTrash2, FiSave, FiX, FiRefreshCw,
  FiCalendar, FiClock, FiMapPin, FiAlertTriangle,
  FiSearch, FiFilter, FiCheckCircle, FiXCircle, FiGrid, FiCopy, FiExternalLink, FiSmartphone
} from 'react-icons/fi';
import api from '../../api/axios';

const INITIAL_EVENT = {
  ec_id: '', filiere_id: '', annee_id: '',
  date: '', heure_debut: '', heure_fin: '', salle: '', salle_id: '', statut: 'planifie',
};

const STATUTS = [
  { value: 'planifie', label: 'Planifié', color: 'bg-primary/10 text-primary' },
  { value: 'en_cours', label: 'En cours', color: 'bg-secondary/10 text-secondary' },
  { value: 'termine', label: 'Terminé', color: 'bg-surface-container-high text-outline' },
  { value: 'annule', label: 'Annulé', color: 'bg-error/10 text-error' },
];

export default function EvenementManagementPage() {
  const [events, setEvents] = useState([]);
  const [ecs, setEcs] = useState([]);
  const [filieres, setFilieres] = useState([]);
  const [annees, setAnnees] = useState([]);
  const [salles, setSalles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [qrGenerating, setQrGenerating] = useState(null);

  // Filtres
  const [filters, setFilters] = useState({ date_debut: '', date_fin: '', filiere_id: '', statut: '' });
  // Filtres toujours visibles

  // Modal événement
  const [modal, setModal] = useState({ open: false, editing: false, data: INITIAL_EVENT, saving: false });

  // Modal QR Code
  const [qrModal, setQrModal] = useState({ open: false, event: null, qrUrl: '', token: '', expireAt: '' });

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setError('');
      const params = {};
      if (filters.date_debut) params.date_debut = filters.date_debut;
      if (params.date_debut && !filters.date_fin) params.date_fin = filters.date_debut;
      if (filters.filiere_id) params.filiere_id = filters.filiere_id;
      if (filters.statut) params.statut = filters.statut;

      const [eventsRes, ecsRes, filieresRes, anneesRes, sallesRes] = await Promise.all([
        api.get('/admin/evenements', { params }),
        api.get('/admin/ecs'),
        api.get('/admin/filieres'),
        api.get('/admin/annees-academiques'),
        api.get('/admin/salles/disponibles'),
      ]);
      setEvents(eventsRes.data?.data ?? eventsRes.data ?? []);
      setEcs(ecsRes.data?.data ?? ecsRes.data ?? []);
      setFilieres(filieresRes.data?.data ?? filieresRes.data ?? []);
      setAnnees(anneesRes.data?.data ?? anneesRes.data ?? []);
      setSalles(sallesRes.data?.data ?? sallesRes.data ?? []);
    } catch (err) {
      setError('Erreur lors du chargement des événements.');
      console.error('[Evenements]', err);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => { load(); }, [load]);

  const getStatutBadge = (statut) => {
    const s = STATUTS.find(s => s.value === statut);
    return s ? `${s.color} px-2.5 py-0.5 rounded-md text-[10px] font-bold` : 'px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-surface-container-high text-outline';
  };

  const getStatutLabel = (statut) => STATUTS.find(s => s.value === statut)?.label || statut;

  // ─── QR Code ─────────────────────────────────────────────

  const generateQrCode = async (eventId) => {
    setQrGenerating(eventId);
    setError('');
    setSuccess('');
    try {
      const { data: res } = await api.get(`/admin/qrcode/${eventId}/generate`);
      const d = res.data || res;
      const baseUrl = window.location.origin;
      const validationUrl = `${baseUrl}/attendance/validate?token=${d.token}`;
      setQrModal({
        open: true,
        event: events.find(e => e.id === eventId),
        qrUrl: validationUrl,
        token: d.token,
        expireAt: d.expire_at,
      });
      setSuccess('QR Code généré avec succès !');
      load();
    } catch (err) {
      setError('Erreur lors de la génération du QR Code.');
    } finally {
      setQrGenerating(null);
    }
  };

  const viewQrCode = (ev) => {
    if (!ev.qr_code?.token) return;
    const baseUrl = window.location.origin;
    const validationUrl = `${baseUrl}/attendance/validate?token=${ev.qr_code.token}`;
    setQrModal({
      open: true,
      event: ev,
      qrUrl: validationUrl,
      token: ev.qr_code.token,
      expireAt: ev.qr_code.expire_at,
    });
  };

  const copyToClipboard = (text) => {
    navigator.clipboard?.writeText(text).then(() => {
      setSuccess('Lien copié !');
    }).catch(() => {});
  };

  // ─── CRUD ──────────────────────────────────────────────

  const openCreate = () => setModal({
    open: true, editing: false,
    data: { ...INITIAL_EVENT, filiere_id: filieres[0]?.id || '', annee_id: annees.find(a => a.active)?.id || annees[0]?.id || '' },
    saving: false,
  });

  const openEdit = (ev) => setModal({
    open: true, editing: true,
    data: {
      id: ev.id,
      ec_id: ev.ec?.id || '', filiere_id: ev.filiere?.id || '', annee_id: ev.annee_id || '',
      date: ev.date, heure_debut: ev.heure_debut, heure_fin: ev.heure_fin,
      salle: ev.salle || '', salle_id: ev.salle_id || '', statut: ev.statut,
    },
    saving: false,
  });

  const handleSave = async (e) => {
    e.preventDefault();
    setModal(prev => ({ ...prev, saving: true }));
    setError('');
    setSuccess('');
    try {
      if (modal.editing) {
        await api.put(`/admin/evenements/${modal.data.id}`, modal.data);
        setSuccess('Événement mis à jour.');
      } else {
        await api.post('/admin/evenements', modal.data);
        setSuccess('Événement créé.');
      }
      setModal({ open: false, editing: false, data: INITIAL_EVENT, saving: false });
      load();
    } catch (err) {
      const msg = err.response?.data?.message
        || (err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : null)
        || 'Erreur lors de la sauvegarde.';
      setError(msg);
      setModal(prev => ({ ...prev, saving: false }));
    }
  };

  const handleDelete = async (ev) => {
    if (!window.confirm(`Supprimer l'événement du ${ev.date} (${ev.heure_debut}-${ev.heure_fin}) ?`)) return;
    try {
      await api.delete(`/admin/evenements/${ev.id}`);
      setSuccess('Événement supprimé.');
      load();
    } catch { setError('Erreur lors de la suppression.'); }
  };

  const getEcsForFiliere = () => {
    const filiereId = filters.filiere_id || modal.data.filiere_id;
    if (!filiereId) return ecs;
    return ecs.filter(ec => ec.ue?.filiere_id == filiereId || ec.ue?.filiere?.id == filiereId);
  };

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between flex-wrap gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Événements</h1>
          <p className="text-sm text-on-surface-variant">Gestion des séances de cours et codes QR</p>
        </div>
        <button onClick={openCreate}
          className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-br from-primary to-primary-container text-white rounded-xl font-bold text-sm shadow-lg hover:shadow-primary/20 active:scale-[0.99] transition-all">
          <FiPlus size={16} /> Nouvel événement
        </button>
      </div>

      {/* Alertes */}
      {error && (
        <div className="flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle size={16} className="flex-shrink-0" />
          <span className="flex-1">{error}</span>
          <button onClick={() => setError('')} className="text-on-error-container/60">&times;</button>
        </div>
      )}
      {success && (
        <div className="flex items-center gap-2 p-3 bg-secondary-container/30 rounded-xl text-on-secondary-container text-sm border border-secondary/10">
          <FiSave size={16} className="flex-shrink-0" />
          <span className="flex-1">{success}</span>
          <button onClick={() => setSuccess('')} className="text-on-secondary-container/60">&times;</button>
        </div>
      )}

      {/* Filtres */}
      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1 min-w-[160px] flex-1">
              <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Du</label>
              <input type="date" value={filters.date_debut}
                onChange={(e) => setFilters(prev => ({ ...prev, date_debut: e.target.value }))}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20" />
            </div>
            <div className="space-y-1 min-w-[160px] flex-1">
              <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Au</label>
              <input type="date" value={filters.date_fin}
                onChange={(e) => setFilters(prev => ({ ...prev, date_fin: e.target.value }))}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20" />
            </div>
            <div className="space-y-1 min-w-[180px] flex-1">
              <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
              <select value={filters.filiere_id}
                onChange={(e) => setFilters(prev => ({ ...prev, filiere_id: e.target.value }))}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="">Toutes</option>
                {filieres.map(f => <option key={f.id} value={f.id}>{f.code}</option>)}
              </select>
            </div>
            <div className="space-y-1 min-w-[180px] flex-1">
              <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Statut</label>
              <select value={filters.statut}
                onChange={(e) => setFilters(prev => ({ ...prev, statut: e.target.value }))}
                className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="">Tous</option>
                {STATUTS.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
              </select>
            </div>
            <div className="min-w-[120px]">
              <button onClick={load}
                className="w-full flex items-center justify-center gap-1.5 px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:opacity-90 transition-all">
                <FiRefreshCw size={14} /> Appliquer
              </button>
            </div>
          </div>
          {(filters.date_debut || filters.date_fin || filters.filiere_id || filters.statut) && (
            <div className="mt-3 text-right">
              <button onClick={() => setFilters({ date_debut: '', date_fin: '', filiere_id: '', statut: '' })}
                className="text-xs text-primary hover:underline">Réinitialiser les filtres</button>
            </div>
          )}
        </div>

      {/* Loading */}
      {loading ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center">
          <FiRefreshCw className="animate-spin mx-auto text-primary text-3xl mb-4" />
          <p className="text-on-surface-variant">Chargement des événements...</p>
        </div>
      ) : events.length === 0 ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center border border-dashed border-outline-variant/30">
          <div className="w-16 h-16 bg-surface-container-high rounded-full flex items-center justify-center mx-auto mb-6">
            <FiCalendar className="text-outline" size={28} />
          </div>
          <h3 className="text-lg font-semibold text-on-surface mb-2">
            {Object.values(filters).some(v => v) ? 'Aucun événement ne correspond aux filtres' : 'Aucun événement'}
          </h3>
          <p className="text-sm text-on-surface-variant">
            {Object.values(filters).some(v => v) ? 'Essayez d\'autres filtres.' : 'Créez votre premier événement.'}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {events.map((ev) => (
            <div key={ev.id} className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 flex items-start gap-4">
              {/* Date block */}
              <div className="text-center flex-shrink-0 w-14">
                <div className="text-2xl font-bold text-primary leading-none">
                  {new Date(ev.date + 'T00:00:00').getDate()}
                </div>
                <div className="text-[10px] text-on-surface-variant uppercase mt-0.5">
                  {new Date(ev.date + 'T00:00:00').toLocaleDateString('fr-FR', { month: 'short' })}
                </div>
              </div>

              {/* Content */}
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-bold text-sm text-on-surface truncate">{ev.ec?.intitule || '—'}</span>
                  <span className={getStatutBadge(ev.statut)}>{getStatutLabel(ev.statut)}</span>
                  {ev.has_qr_code && (
                    <span className="bg-secondary/10 text-secondary px-2 py-0.5 rounded-md text-[10px] font-bold flex items-center gap-1">
                      <FiGrid size={10} /> QR
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-4 mt-1 text-[11px] text-on-surface-variant flex-wrap">
                  <span className="flex items-center gap-1"><FiClock size={12} />{ev.heure_debut} - {ev.heure_fin}</span>
                  {ev.salle_ref ? (
                    <span className="flex items-center gap-1 text-secondary"><FiMapPin size={12} />{ev.salle_ref.nom} <span className="text-[9px] text-secondary/70">(GPS+WiFi)</span></span>
                  ) : ev.salle ? (
                    <span className="flex items-center gap-1"><FiMapPin size={12} />{ev.salle}</span>
                  ) : null}
                  <span className="flex items-center gap-1">{ev.ue?.code || ev.ec?.code}</span>
                  <span>{ev.filiere?.code}</span>
                </div>
                <p className="text-[10px] text-outline mt-1">
                  {ev.presences_count ?? 0} présence{(ev.presences_count ?? 0) > 1 ? 's' : ''}
                </p>

                {/* QR Code Actions */}
                <div className="flex items-center gap-2 mt-2 flex-wrap">
                  {ev.has_qr_code && ev.qr_code && !ev.qr_code.is_expired ? (
                    <button onClick={() => viewQrCode(ev)}
                      className="flex items-center gap-1 px-2.5 py-1 bg-secondary/10 text-secondary rounded-lg text-[10px] font-bold hover:bg-secondary/20 transition-all">
                      <FiGrid size={11} /> Voir QR Code
                    </button>
                  ) : (
                    <>
                      <button disabled
                        className="flex items-center gap-1 px-2.5 py-1 bg-surface-container-high text-outline/50 rounded-lg text-[10px] font-bold cursor-not-allowed">
                        <FiGrid size={11} /> Voir QR Code
                      </button>
                      <button onClick={() => generateQrCode(ev.id)} disabled={qrGenerating === ev.id}
                        className="flex items-center gap-1 px-2.5 py-1 bg-primary/10 text-primary rounded-lg text-[10px] font-bold hover:bg-primary/20 transition-all disabled:opacity-50">
                        {qrGenerating === ev.id ? <FiRefreshCw className="animate-spin" size={11} /> : <FiRefreshCw size={11} />}
                        Générer QR Code
                      </button>
                    </>
                  )}
                </div>
              </div>

              {/* Actions */}
              <div className="flex items-center gap-1 flex-shrink-0">
                <button onClick={() => openEdit(ev)}
                  className="p-2 text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Modifier">
                  <FiEdit2 size={14} />
                </button>
                <button onClick={() => handleDelete(ev)}
                  className="p-2 text-outline hover:text-error hover:bg-error/10 rounded-lg transition-all" title="Supprimer">
                  <FiTrash2 size={14} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* ─── Modal QR Code ─────────────────────────────── */}
      {qrModal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          onClick={() => setQrModal(prev => ({ ...prev, open: false }))}>
          <div className="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-sm shadow-xl"
            onClick={(e) => e.stopPropagation()}>
            {/* Close */}
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-bold text-primary">Code QR</h2>
              <button onClick={() => setQrModal(prev => ({ ...prev, open: false }))}
                className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX size={20} className="text-outline" />
              </button>
            </div>

            {/* Event info */}
            {qrModal.event && (
              <div className="bg-surface-container-high rounded-xl p-3 mb-4 text-center">
                <p className="font-bold text-sm text-primary">{qrModal.event.ec?.intitule || 'Cours'}</p>
                <p className="text-[11px] text-on-surface-variant mt-0.5">
                  {qrModal.event.date} · {qrModal.event.heure_debut} - {qrModal.event.heure_fin}
                </p>
                {qrModal.event.salle && (
                  <p className="text-[11px] text-on-surface-variant">{qrModal.event.salle}</p>
                )}
              </div>
            )}

            {/* QR Code Image */}
            <div className="flex justify-center mb-4">
              <img
                src={`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(qrModal.qrUrl)}`}
                alt="QR Code"
                className="w-56 h-56 rounded-xl bg-white p-2 shadow-sm"
              />
            </div>

            {/* Lien de validation */}
            <div className="bg-surface-container-high rounded-xl p-3 mb-4">
              <p className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant mb-1">Lien de validation</p>
              <div className="flex items-center gap-2">
                <code className="flex-1 text-[10px] text-primary font-mono truncate bg-surface-container-lowest rounded-lg px-2 py-1.5">
                  {qrModal.qrUrl}
                </code>
                <button onClick={() => copyToClipboard(qrModal.qrUrl)}
                  className="p-1.5 text-outline hover:text-primary hover:bg-primary/10 rounded-lg transition-all" title="Copier">
                  <FiCopy size={14} />
                </button>
              </div>
            </div>

            {/* Instructions */}
            <div className="flex items-start gap-2 text-[11px] text-on-surface-variant p-3 bg-surface-container-high rounded-xl">
              <FiSmartphone size={14} className="shrink-0 mt-0.5 text-secondary" />
              <p>Les étudiants scannent ce QR code avec leur téléphone pour valider leur présence. Le code expire dans 60 secondes.</p>
            </div>

            <div className="mt-4">
              <button onClick={() => setQrModal(prev => ({ ...prev, open: false }))}
                className="w-full py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all">
                Fermer
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ─── Modal événement ────────────────────────────── */}
      {modal.open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
          onClick={() => setModal(prev => ({ ...prev, open: false }))}>
          <div className="bg-surface-container-lowest rounded-2xl p-6 w-full max-w-lg shadow-xl"
            onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-lg font-bold text-primary">{modal.editing ? "Modifier l'événement" : 'Nouvel événement'}</h2>
              <button onClick={() => setModal(prev => ({ ...prev, open: false }))} className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX size={20} className="text-outline" />
              </button>
            </div>
            <form onSubmit={handleSave} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Filière *</label>
                  <select value={modal.data.filiere_id} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, filiere_id: e.target.value, ec_id: '' } }))}
                    required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Sélectionner...</option>
                    {filieres.map(f => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Année *</label>
                  <select value={modal.data.annee_id} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, annee_id: e.target.value } }))}
                    required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Sélectionner...</option>
                    {annees.map(a => <option key={a.id} value={a.id}>{a.libelle}</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1">EC (Cours) *</label>
                <select value={modal.data.ec_id} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, ec_id: e.target.value } }))}
                  required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                  <option value="">Sélectionner...</option>
                  {getEcsForFiliere().map(ec => (
                    <option key={ec.id} value={ec.id}>{ec.code} — {ec.intitule}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1">Date *</label>
                <input type="date" value={modal.data.date} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, date: e.target.value } }))}
                  required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Début *</label>
                  <input type="time" value={modal.data.heure_debut} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, heure_debut: e.target.value } }))}
                    required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Fin *</label>
                  <input type="time" value={modal.data.heure_fin} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, heure_fin: e.target.value } }))}
                    required className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Salle (nom)</label>
                  <input type="text" value={modal.data.salle} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, salle: e.target.value } }))}
                    maxLength={100} className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: Amphi 200" />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-on-surface mb-1">Salle configurée (GPS/WiFi)</label>
                  <select value={modal.data.salle_id} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, salle_id: e.target.value } }))}
                    className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="">Aucune (QR seul)</option>
                    {salles.map(s => <option key={s.id} value={s.id}>{s.nom} ({s.code})</option>)}
                  </select>
                </div>
              </div>
              <div>
                <label className="block text-xs font-semibold text-on-surface mb-1">Statut</label>
                <select value={modal.data.statut} onChange={(e) => setModal(prev => ({ ...prev, data: { ...prev.data, statut: e.target.value } }))}
                  className="w-full px-3 py-2 bg-surface-container-high border border-outline-variant/30 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                  {STATUTS.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                </select>
              </div>
              <div className="flex gap-3 pt-2">
                <button type="submit" disabled={modal.saving}
                  className="flex-1 flex items-center justify-center gap-2 py-2.5 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all disabled:opacity-50">
                  {modal.saving ? <FiRefreshCw className="animate-spin" size={16} /> : <FiSave size={16} />}
                  {modal.editing ? 'Mettre à jour' : "Créer l'événement"}
                </button>
                <button type="button" onClick={() => setModal(prev => ({ ...prev, open: false }))}
                  className="px-6 py-2.5 bg-surface-container-high text-on-surface-variant rounded-xl font-semibold text-sm hover:bg-surface-container-high/80 transition-all">
                  Annuler
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
