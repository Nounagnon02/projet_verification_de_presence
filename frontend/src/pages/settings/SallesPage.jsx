import { useState, useEffect, useCallback } from 'react';
import { FiPlus, FiEdit2, FiTrash2, FiMapPin, FiWifi, FiAlertTriangle, FiCheck, FiX, FiSearch, FiLoader } from 'react-icons/fi';
import api from '../../api/axios';
import { useToastCtx } from '../../context/ToastContext';

const EMPTY_SALLE = {
  nom: '', code: '', etablissement_id: '',
  latitude: '', longitude: '', rayon_geofence_m: 50,
  ssid_attendu: '', bssid_attendu: '', ip_range: '',
  hors_reseau: false, actif: true,
};

export default function SallesPage() {
  const { addToast } = useToastCtx();
  const [salles, setSalles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null); // null = create, object = edit
  const [form, setForm] = useState(EMPTY_SALLE);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState('');
  const [showDelete, setShowDelete] = useState(null);
  const [userEntity, setUserEntity] = useState(null);

  const fetchSalles = useCallback(async () => {
    try {
      const { data } = await api.get('/admin/salles', { params: { search: search || undefined } });
      setSalles(data.data || data || []);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, [search]);

  useEffect(() => { fetchSalles(); }, [fetchSalles]);

  useEffect(() => {
    Promise.all([
      api.get('/admin/etablissements'),
      api.get('/user'),
    ]).then(([etabRes, userRes]) => {
      const entities = etabRes.data?.data ?? etabRes.data ?? [];
      const user = userRes.data;
      if (user?.etablissement_id) {
        const entity = entities.find(e => e.id === user.etablissement_id);
        if (entity) setUserEntity(entity);
      }
    }).catch(() => {});
  }, []);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...EMPTY_SALLE, etablissement_id: userEntity?.id || '' });
    setError('');
    setShowModal(true);
  };

  const openEdit = (salle) => {
    setEditing(salle);
    setForm({
      nom: salle.nom || '',
      code: salle.code || '',
      etablissement_id: salle.etablissement_id || '',
      latitude: salle.latitude ?? '',
      longitude: salle.longitude ?? '',
      rayon_geofence_m: salle.rayon_geofence_m ?? 50,
      ssid_attendu: salle.ssid_attendu || '',
      bssid_attendu: salle.bssid_attendu || '',
      ip_range: salle.ip_range || '',
      hors_reseau: salle.hors_reseau || false,
      actif: salle.actif ?? true,
    });
    setError('');
    setShowModal(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        ...form,
        etablissement_id: form.etablissement_id ? Number(form.etablissement_id) : undefined,
        latitude: form.latitude !== '' ? Number(form.latitude) : null,
        longitude: form.longitude !== '' ? Number(form.longitude) : null,
        rayon_geofence_m: form.rayon_geofence_m ? Number(form.rayon_geofence_m) : 50,
      };

      if (editing) {
        await api.put(`/admin/salles/${editing.id}`, payload);
        addToast?.('Salle mise à jour.', 'success');
      } else {
        await api.post('/admin/salles', payload);
        addToast?.('Salle créée avec succès.', 'success');
      }
      setShowModal(false);
      fetchSalles();
    } catch (err) {
      const msg = err.response?.data?.message || 'Erreur lors de la sauvegarde.';
      setError(msg);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!showDelete) return;
    setDeleting(true);
    try {
      await api.delete(`/admin/salles/${showDelete.id}`);
      addToast?.('Salle supprimée.', 'success');
      setShowDelete(null);
      fetchSalles();
    } catch (err) {
      addToast?.(err.response?.data?.message || 'Erreur lors de la suppression.', 'error');
    } finally {
      setDeleting(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64 text-on-surface-variant">Chargement...</div>
    );
  }

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Salles</h1>
          <p className="text-sm text-on-surface-variant mt-1">
            Configurez les salles avec géolocalisation et réseau WiFi pour la vérification de présence.
          </p>
        </div>
        <button
          onClick={openCreate}
          className="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all"
        >
          <FiPlus size={16} /> Ajouter une salle
        </button>
      </div>

      {/* Barre de recherche */}
      <div className="relative max-w-md">
        <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant" size={16} />
        <input
          type="text"
          placeholder="Rechercher une salle..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full pl-10 pr-4 py-2.5 bg-surface-container-high rounded-xl text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
        />
      </div>

      {/* Liste des salles */}
      {salles.length === 0 ? (
        <div className="text-center py-16 text-on-surface-variant">
          <FiMapPin size={48} className="mx-auto mb-4 opacity-30" />
          <p className="text-lg font-semibold">Aucune salle configurée</p>
          <p className="text-sm mt-1">Ajoutez des salles pour activer la vérification de présence par géolocalisation et réseau WiFi.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {salles.map((salle) => (
            <div
              key={salle.id}
              className={`bg-surface-container-lowest rounded-xl p-5 shadow-sm border transition-all ${
                salle.actif ? 'border-outline-variant/10 hover:border-primary/20' : 'border-error/20 opacity-60'
              }`}
            >
              <div className="flex items-start justify-between mb-3">
                <div>
                  <h3 className="font-bold text-primary">{salle.nom}</h3>
                  <code className="text-xs text-on-surface-variant font-mono">{salle.code}</code>
                </div>
                <div className="flex items-center gap-1">
                  <button onClick={() => openEdit(salle)} className="p-1.5 hover:bg-surface-container-high rounded-lg transition-colors" title="Modifier">
                    <FiEdit2 size={14} className="text-on-surface-variant" />
                  </button>
                  <button onClick={() => setShowDelete(salle)} className="p-1.5 hover:bg-error/10 rounded-lg transition-colors" title="Supprimer">
                    <FiTrash2 size={14} className="text-error" />
                  </button>
                </div>
              </div>

              <div className="space-y-2 text-xs">
                {/* GPS */}
                <div className="flex items-center gap-2">
                  <FiMapPin size={12} className={salle.latitude ? 'text-secondary' : 'text-on-surface-variant/40'} />
                  <span className="text-on-surface-variant">
                    {salle.latitude ? `${salle.latitude}, ${salle.longitude} (∅${salle.rayon_geofence_m}m)` : 'GPS non configuré'}
                  </span>
                </div>
                {/* WiFi */}
                <div className="flex items-center gap-2">
                  <FiWifi size={12} className={salle.ssid_attendu ? 'text-secondary' : 'text-on-surface-variant/40'} />
                  <span className="text-on-surface-variant">
                    {salle.hors_reseau ? 'Hors réseau (mode dégradé)' : (salle.ssid_attendu || 'WiFi non configuré')}
                  </span>
                </div>
                {/* Statut */}
                <div className="flex items-center gap-2">
                  {salle.actif ? (
                    <span className="flex items-center gap-1 text-secondary"><FiCheck size={12} /> Active</span>
                  ) : (
                    <span className="flex items-center gap-1 text-error"><FiX size={12} /> Inactive</span>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal Création / Édition */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-start justify-center p-4 bg-black/40 backdrop-blur-sm overflow-y-auto" onClick={() => setShowModal(false)}>
          <div className="bg-surface-container-lowest rounded-2xl shadow-2xl max-w-2xl w-full p-6 my-8 relative" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-lg font-bold text-primary font-headline">
                {editing ? 'Modifier la salle' : 'Nouvelle salle'}
              </h3>
              <button onClick={() => setShowModal(false)} className="p-1 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX size={20} className="text-on-surface-variant" />
              </button>
            </div>

            {error && (
              <div className="flex items-center gap-2 p-3 bg-error/10 rounded-xl text-error text-sm mb-4">
                <FiAlertTriangle size={16} />
                <span>{error}</span>
              </div>
            )}

            <form onSubmit={handleSave} className="space-y-4">
              {/* Infos générales */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="sm:col-span-2">
                  <Field label="Nom de la salle *">
                    <input type="text" required className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all" value={form.nom} onChange={(e) => setForm({ ...form, nom: e.target.value })} />
                  </Field>
                </div>
                <Field label="Code unique *">
                  <input type="text" required className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all font-mono" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
                </Field>
              </div>

              <Field label="Entité">
                <div className="flex items-center gap-2 px-3 py-2.5 bg-surface-container-high rounded-lg text-sm text-on-surface-variant">
                  <FiMapPin size={14} className="text-secondary" />
                  <span className="font-medium text-on-surface">{userEntity?.nom || userEntity?.code || 'Entité non définie'}</span>
                </div>
              </Field>

              {/* Géolocalisation */}
              <div className="border-t border-outline-variant/20 pt-4">
                <h4 className="text-sm font-bold text-primary mb-3 flex items-center gap-2"><FiMapPin size={16} /> Géolocalisation GPS</h4>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <Field label="Latitude">
                    <input type="number" step="any" placeholder="Ex: 6.3650" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all font-mono" value={form.latitude} onChange={(e) => setForm({ ...form, latitude: e.target.value })} />
                  </Field>
                  <Field label="Longitude">
                    <input type="number" step="any" placeholder="Ex: 2.4180" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all font-mono" value={form.longitude} onChange={(e) => setForm({ ...form, longitude: e.target.value })} />
                  </Field>
                  <Field label="Rayon geofence (mètres)">
                    <input type="number" min="5" max="500" placeholder="50" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all" value={form.rayon_geofence_m} onChange={(e) => setForm({ ...form, rayon_geofence_m: e.target.value })} />
                  </Field>
                </div>
              </div>

              {/* Réseau WiFi */}
              <div className="border-t border-outline-variant/20 pt-4">
                <h4 className="text-sm font-bold text-primary mb-3 flex items-center gap-2"><FiWifi size={16} /> Réseau WiFi</h4>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <Field label="SSID attendu">
                    <input type="text" placeholder="Ex: IFRI-WiFi" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all" value={form.ssid_attendu} onChange={(e) => setForm({ ...form, ssid_attendu: e.target.value })} />
                  </Field>
                  <Field label="BSSID attendu (MAC)">
                    <input type="text" placeholder="Ex: 00:11:22:33:44:55" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all font-mono" value={form.bssid_attendu} onChange={(e) => setForm({ ...form, bssid_attendu: e.target.value })} />
                  </Field>
                  <Field label="Plage IP (CIDR)">
                    <input type="text" placeholder="Ex: 192.168.1.0/24" className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all font-mono" value={form.ip_range} onChange={(e) => setForm({ ...form, ip_range: e.target.value })} />
                  </Field>
                </div>
                <label className="flex items-center gap-2 mt-3 cursor-pointer">
                  <input type="checkbox" checked={form.hors_reseau} onChange={(e) => setForm({ ...form, hors_reseau: e.target.checked })} className="rounded border-outline-variant/30 text-primary focus:ring-primary/20" />
                  <span className="text-xs text-on-surface-variant">Salle hors réseau (pas de vérification WiFi — mode GPS seul)</span>
                </label>
              </div>

              {/* Statut */}
              <div className="border-t border-outline-variant/20 pt-4">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" checked={form.actif} onChange={(e) => setForm({ ...form, actif: e.target.checked })} className="rounded border-outline-variant/30 text-primary focus:ring-primary/20" />
                  <span className="text-sm text-on-surface-variant">Salle active</span>
                </label>
              </div>

              {/* Actions */}
              <div className="flex justify-end gap-3 pt-2">
                <button type="button" onClick={() => setShowModal(false)} className="px-5 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container transition-colors">
                  Annuler
                </button>
                <button type="submit" disabled={saving} className="flex items-center justify-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50">
                  {saving && <FiLoader className="animate-spin" />}{saving ? 'Enregistrement...' : (editing ? 'Mettre à jour' : 'Créer')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal confirmation suppression */}
      {showDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" onClick={() => setShowDelete(null)}>
          <div className="bg-surface-container-lowest rounded-2xl shadow-2xl max-w-md w-full p-6 relative" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center gap-3 mb-4">
              <div className="p-2 bg-error/10 rounded-xl"><FiAlertTriangle className="text-error" size={20} /></div>
              <div>
                <h3 className="text-lg font-bold text-primary">Supprimer la salle</h3>
                <p className="text-xs text-on-surface-variant">{showDelete.nom} ({showDelete.code})</p>
              </div>
            </div>
            <p className="text-sm text-on-surface-variant mb-6">Cette action est irréversible. Les événements utilisant cette salle ne seront pas supprimés.</p>
            <div className="flex gap-3">
              <button onClick={() => setShowDelete(null)} className="flex-1 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container transition-colors">Annuler</button>
              <button onClick={handleDelete} disabled={deleting} className="flex items-center justify-center gap-2 flex-1 px-4 py-2.5 bg-error text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50">
                {deleting && <FiLoader className="animate-spin" />}Supprimer</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

const Field = ({ label, children }) => (
  <div className="space-y-1">
    <label className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">{label}</label>
    {children}
  </div>
);
