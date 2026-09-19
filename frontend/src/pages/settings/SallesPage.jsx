import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { FiPlus, FiEdit2, FiTrash2, FiMapPin, FiWifi, FiAlertTriangle, FiSearch, FiLoader, FiCrosshair, FiCalendar } from 'react-icons/fi';
import Modal from '../../components/ui/Modal';
import { listerSalles, creerSalle, modifierSalle, supprimerSalle, utilisateurConnecte } from '../../api/resources/salles';
import { useToastCtx } from '../../context/ToastContext';

// La plage IP a quitté le formulaire : enregistrée et comparée au scan, elle
// n'y refusait jamais rien. Le champ promettait un contrôle qui n'existait pas.
const EMPTY_SALLE = {
  nom: '', code: '', etablissement_id: '',
  latitude: '', longitude: '', rayon_geofence_m: 50,
  ssid_attendu: '', bssid_attendu: '',
  hors_reseau: false, actif: true,
};

const renseigne = (v) => v !== null && v !== undefined && v !== '';

/**
 * Ce que la salle vérifie au scan, en plus du QR code. Le serveur le fournit
 * (verifie_gps, verifie_wifi) ; la règle locale n'est qu'un repli, identique à
 * Salle::verifieGps() et Salle::verifieWifi().
 */
const verifieGps = (s) => s.verifie_gps ?? (renseigne(s.latitude) && renseigne(s.longitude));
const verifieWifi = (s) => s.verifie_wifi ?? (!s.hors_reseau && Boolean(s.ssid_attendu || s.bssid_attendu));
const protegee = (s) => verifieGps(s) || verifieWifi(s);

const FILTRES = [
  { id: 'toutes', libelle: 'Toutes', garde: () => true },
  { id: 'a-configurer', libelle: 'À configurer', garde: (s) => s.actif && !protegee(s) },
  { id: 'protegees', libelle: 'Protégées', garde: (s) => s.actif && protegee(s) },
  { id: 'desactivees', libelle: 'Désactivées', garde: (s) => !s.actif },
];

const VIDES = {
  toutes: 'Aucune salle ne correspond à la recherche.',
  'a-configurer': 'Aucune salle à configurer : chaque salle active contrôle la position ou le réseau.',
  protegees: 'Aucune salle ne contrôle encore la position ou le réseau.',
  desactivees: 'Aucune salle désactivée.',
};

/** Forme de recherche : sans casse ni accents. */
const cle = (texte) => String(texte ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

// Les salles actives et utilisées d'abord : c'est par elles qu'il faut
// commencer la configuration.
const parUsage = (a, b) => (Number(b.actif) - Number(a.actif))
  || ((b.seances_a_venir ?? 0) - (a.seances_a_venir ?? 0))
  || ((b.creneaux_count ?? 0) - (a.creneaux_count ?? 0))
  || String(a.nom).localeCompare(String(b.nom));

const usage = (s) => {
  const parts = [];
  if (s.seances_a_venir) parts.push(`${s.seances_a_venir} séance${s.seances_a_venir > 1 ? 's' : ''} à venir`);
  if (s.creneaux_count) parts.push(`${s.creneaux_count} créneau${s.creneaux_count > 1 ? 'x' : ''} à l'emploi du temps`);
  return parts.length ? parts.join(' · ') : 'Aucune séance à venir';
};

/** Ce que la salle vérifie, en une pastille : « QR seul » ne se confond plus avec une salle protégée. */
function Protection({ salle }) {
  const base = 'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap';

  if (!salle.actif) return <span className={`${base} bg-error/10 text-error`}>Désactivée</span>;

  const controles = [verifieGps(salle) && 'GPS', verifieWifi(salle) && 'Wi-Fi'].filter(Boolean);

  return controles.length
    ? <span className={`${base} bg-secondary/10 text-secondary`}>QR + {controles.join(' + ')}</span>
    : <span className={`${base} bg-warning-container text-on-surface`}>QR seul</span>;
}

const Compteur = ({ valeur, libelle, alerte = false }) => (
  <div className={`rounded-lg p-3 ${alerte && valeur > 0 ? 'bg-warning-container' : 'bg-surface-container-low'}`}>
    <p className="text-2xl font-bold font-headline tabular-nums text-on-surface">{valeur}</p>
    <p className="text-xs text-on-surface-variant">{libelle}</p>
  </div>
);

export default function SallesPage() {
  const { addToast } = useToastCtx() ?? {};
  const [search, setSearch] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState(null); // null = create, object = edit
  const [form, setForm] = useState(EMPTY_SALLE);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState('');
  const [showDelete, setShowDelete] = useState(null);
  const [localisation, setLocalisation] = useState({ etat: 'repos', message: '' });

  // Le filtre vit dans l'adresse : les imports renvoient directement sur
  // « À configurer » (/settings/salles?filtre=a-configurer).
  const [params, setParams] = useSearchParams();
  const filtre = FILTRES.some((f) => f.id === params.get('filtre')) ? params.get('filtre') : 'toutes';
  const choisirFiltre = (id) => setParams(id === 'toutes' ? {} : { filtre: id }, { replace: true });

  const queryClient = useQueryClient();

  // Toutes les salles de l'entité, puis recherche et filtres à l'écran : le
  // bandeau d'état doit compter toutes les salles, pas le résultat d'une
  // recherche.
  const sallesQuery = useQuery({ queryKey: ['salles'], queryFn: ({ signal }) => listerSalles(signal) });
  const salles = Array.isArray(sallesQuery.data?.data) ? sallesQuery.data.data : (Array.isArray(sallesQuery.data) ? sallesQuery.data : []);
  const loading = sallesQuery.isLoading;
  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['salles'] });

  // Rattachement de l'utilisateur à son entité, pour préremplir le formulaire.
  // L'etablissement de rattachement vient de /user, qui le charge avec la
  // relation. L'ancienne version interrogeait /admin/etablissements — une
  // route qui n'existe pas : l'appel partait en 404 avalé en silence.
  const utilisateurQuery = useQuery({ queryKey: ['utilisateur-connecte'], queryFn: () => utilisateurConnecte() });
  const userEntity = utilisateurQuery.data?.etablissement ?? null;

  const openCreate = () => {
    setEditing(null);
    setForm({ ...EMPTY_SALLE, etablissement_id: userEntity?.id || '' });
    setError('');
    setLocalisation({ etat: 'repos', message: '' });
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
      hors_reseau: salle.hors_reseau || false,
      actif: salle.actif ?? true,
    });
    setError('');
    setLocalisation({ etat: 'repos', message: '' });
    setShowModal(true);
  };

  /**
   * Relève la position de l'appareil. Les coordonnées d'une salle ne se
   * devinent pas : elles se relèvent sur place, et c'est le plus simple depuis
   * un téléphone, dans la salle même.
   */
  const utiliserMaPosition = () => {
    if (!navigator.geolocation) {
      setLocalisation({ etat: 'erreur', message: "Ce navigateur ne donne pas accès à la position : saisissez les coordonnées." });
      return;
    }

    const rayon = Number(form.rayon_geofence_m) || 50;
    setLocalisation({ etat: 'encours', message: 'Recherche de la position…' });

    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setForm((f) => ({ ...f, latitude: coords.latitude.toFixed(6), longitude: coords.longitude.toFixed(6) }));
        const precision = Math.round(coords.accuracy ?? 0);
        setLocalisation({
          etat: 'ok',
          message: precision > rayon
            ? `Position relevée à ±${precision} m, moins précise que le rayon de la salle (${rayon} m) : réessayez près d'une fenêtre, ou élargissez le rayon.`
            : `Position relevée à ±${precision} m. Vérifiez que vous êtes bien dans la salle.`,
        });
      },
      (err) => setLocalisation({
        etat: 'erreur',
        message: !window.isSecureContext
          ? "La position n'est disponible que sur une connexion sécurisée (https) : saisissez les coordonnées."
          : err?.code === 1
            ? "Accès à la position refusé : autorisez-le dans le navigateur, ou saisissez les coordonnées."
            : "Position introuvable : réessayez près d'une fenêtre, ou saisissez les coordonnées.",
      }),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        ...form,
        // Laissé vide à la création, le code est dérivé du nom par le serveur.
        code: form.code.trim() || undefined,
        etablissement_id: form.etablissement_id ? Number(form.etablissement_id) : undefined,
        latitude: form.latitude !== '' ? Number(form.latitude) : null,
        longitude: form.longitude !== '' ? Number(form.longitude) : null,
        rayon_geofence_m: form.rayon_geofence_m ? Number(form.rayon_geofence_m) : 50,
      };

      if (editing) {
        await modifierSalle(editing.id, payload);
        addToast?.('Salle mise à jour.', 'success');
      } else {
        await creerSalle(payload);
        addToast?.('Salle créée avec succès.', 'success');
      }
      setShowModal(false);
      rafraichir();
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
      await supprimerSalle(showDelete.id);
      addToast?.('Salle supprimée.', 'success');
      setShowDelete(null);
      rafraichir();
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

  const actives = salles.filter((s) => s.actif);
  const etat = {
    gps: actives.filter(verifieGps).length,
    wifi: actives.filter(verifieWifi).length,
    qrSeul: actives.filter((s) => !protegee(s)).length,
    desactivees: salles.length - actives.length,
  };

  const q = cle(search.trim());
  const garde = FILTRES.find((f) => f.id === filtre).garde;
  const visibles = salles
    .filter(garde)
    .filter((s) => !q || cle(s.nom).includes(q) || cle(s.code).includes(q))
    .sort(parUsage);

  const champ = 'w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all';

  return (
    <div className="space-y-6">
      {/* En-tête */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Salles</h1>
          <p className="text-sm text-on-surface-variant mt-1">
            Ce que chaque salle vérifie au scan, en plus du QR code : la position (GPS) et le réseau (Wi-Fi).
          </p>
        </div>
        <button
          onClick={openCreate}
          className="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all"
        >
          <FiPlus size={16} /> Ajouter une salle
        </button>
      </div>

      {salles.length === 0 ? (
        <div className="text-center py-16 text-on-surface-variant">
          <FiMapPin size={48} className="mx-auto mb-4 opacity-30" />
          <p className="text-lg font-semibold">Aucune salle configurée</p>
          <p className="text-sm mt-1">Ajoutez des salles pour activer la vérification de présence par géolocalisation et réseau Wi-Fi.</p>
        </div>
      ) : (
        <>
          {/* État d'ensemble : les seize salles « GPS non configuré » d'affilée ne
              disaient pas que la protection au scan était presque absente. */}
          <section aria-label="État des salles" className="bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/10">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
              <Compteur valeur={etat.gps} libelle="contrôlent la position (GPS)" />
              <Compteur valeur={etat.wifi} libelle="contrôlent le réseau (Wi-Fi)" />
              <Compteur valeur={etat.qrSeul} libelle="ne vérifient que le QR code" alerte />
              <Compteur valeur={etat.desactivees} libelle="désactivées" />
            </div>
            {etat.qrSeul > 0 && (
              <p className="flex items-start gap-2 text-xs text-on-surface-variant mt-4">
                <FiAlertTriangle size={14} className="text-error shrink-0 mt-0.5" aria-hidden="true" />
                <span>
                  Dans une salle « QR seul », le scan ne vérifie pas que l'étudiant est dans la salle : un QR code relayé
                  à distance peut suffire. Commencez par les salles qui ont des séances à venir, placées en tête de liste.
                </span>
              </p>
            )}
          </section>

          {/* Filtres et recherche */}
          <div className="flex flex-col lg:flex-row lg:items-center gap-3">
            <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Filtrer les salles">
              {FILTRES.map((f) => {
                const actif = filtre === f.id;
                return (
                  <button
                    key={f.id}
                    type="button"
                    aria-pressed={actif}
                    onClick={() => choisirFiltre(f.id)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                      actif ? 'bg-primary text-white shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:bg-surface-container'
                    }`}
                  >
                    {f.libelle} <span className="opacity-70 tabular-nums">({salles.filter(f.garde).length})</span>
                  </button>
                );
              })}
            </div>
            <div className="relative lg:ml-auto w-full lg:max-w-xs">
              <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant" size={16} aria-hidden="true" />
              <input
                type="search"
                aria-label="Rechercher une salle"
                placeholder="Rechercher une salle..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-10 pr-4 py-2.5 bg-surface-container-high rounded-xl text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
              />
            </div>
          </div>

          {/* Liste des salles */}
          {visibles.length === 0 ? (
            <p className="text-center py-12 text-sm text-on-surface-variant">{VIDES[filtre]}</p>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {visibles.map((salle) => (
                <article
                  key={salle.id}
                  className={`bg-surface-container-lowest rounded-xl p-5 shadow-sm border transition-all flex flex-col ${
                    salle.actif ? 'border-outline-variant/10 hover:border-primary/20' : 'border-error/20 opacity-70'
                  }`}
                >
                  <div className="flex items-start justify-between gap-2 mb-3">
                    <div className="min-w-0">
                      <h3 className="font-bold text-primary">{salle.nom}</h3>
                      <div className="flex flex-wrap items-center gap-2 mt-1">
                        <code className="text-xs text-on-surface-variant font-mono">{salle.code}</code>
                        <Protection salle={salle} />
                      </div>
                    </div>
                    <div className="flex items-center gap-1 shrink-0">
                      <button onClick={() => openEdit(salle)} className="p-1.5 hover:bg-surface-container-high rounded-lg transition-colors" title="Modifier" aria-label={`Modifier ${salle.nom}`}>
                        <FiEdit2 size={14} className="text-on-surface-variant" />
                      </button>
                      <button onClick={() => setShowDelete(salle)} className="p-1.5 hover:bg-error/10 rounded-lg transition-colors" title="Supprimer" aria-label={`Supprimer ${salle.nom}`}>
                        <FiTrash2 size={14} className="text-error" />
                      </button>
                    </div>
                  </div>

                  <div className="space-y-2 text-xs">
                    <div className="flex items-center gap-2">
                      <FiMapPin size={12} className={verifieGps(salle) ? 'text-secondary' : 'text-on-surface-variant/40'} aria-hidden="true" />
                      <span className="text-on-surface-variant">
                        {verifieGps(salle)
                          ? `${Number(salle.latitude).toFixed(5)}, ${Number(salle.longitude).toFixed(5)} · rayon ${salle.rayon_geofence_m} m`
                          : 'GPS non configuré'}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <FiWifi size={12} className={verifieWifi(salle) ? 'text-secondary' : 'text-on-surface-variant/40'} aria-hidden="true" />
                      <span className="text-on-surface-variant">
                        {salle.hors_reseau
                          ? 'Hors réseau : pas de contrôle Wi-Fi'
                          : (salle.ssid_attendu || salle.bssid_attendu || 'Wi-Fi non configuré')}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <FiCalendar size={12} className="text-on-surface-variant/60" aria-hidden="true" />
                      <span className="text-on-surface-variant">{usage(salle)}</span>
                    </div>
                  </div>

                  {salle.actif && !protegee(salle) && (
                    <button
                      type="button"
                      onClick={() => openEdit(salle)}
                      className="mt-4 self-start text-xs font-semibold text-primary hover:underline"
                    >
                      Configurer le GPS ou le Wi-Fi →
                    </button>
                  )}
                </article>
              ))}
            </div>
          )}
        </>
      )}

      {/* Modal Création / Édition */}
      <Modal isOpen={showModal} onClose={() => setShowModal(false)}
        title={editing ? 'Modifier la salle' : 'Nouvelle salle'} size="lg">
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
              <Field label="Nom de la salle *" htmlFor="salle-nom">
                <input id="salle-nom" type="text" required className={champ} value={form.nom} onChange={(e) => setForm({ ...form, nom: e.target.value })} />
              </Field>
            </div>
            <Field label={editing ? 'Code unique *' : 'Code unique'} htmlFor="salle-code">
              <input
                id="salle-code"
                type="text"
                required={Boolean(editing)}
                placeholder={editing ? '' : 'Dérivé du nom'}
                className={`${champ} font-mono`}
                value={form.code}
                onChange={(e) => setForm({ ...form, code: e.target.value })}
              />
            </Field>
          </div>
          {!editing && (
            <p className="text-[11px] text-on-surface-variant -mt-2">
              Laissez le code vide : il sera dérivé du nom (« Labo Info 1 » donne LABO-INFO-1).
            </p>
          )}

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
              <Field label="Latitude" htmlFor="salle-latitude">
                <input id="salle-latitude" type="number" step="any" placeholder="Ex: 6.3650" className={`${champ} font-mono`} value={form.latitude} onChange={(e) => setForm({ ...form, latitude: e.target.value })} />
              </Field>
              <Field label="Longitude" htmlFor="salle-longitude">
                <input id="salle-longitude" type="number" step="any" placeholder="Ex: 2.4180" className={`${champ} font-mono`} value={form.longitude} onChange={(e) => setForm({ ...form, longitude: e.target.value })} />
              </Field>
              <Field label="Rayon geofence (mètres)" htmlFor="salle-rayon">
                <input id="salle-rayon" type="number" min="5" max="500" placeholder="50" className={champ} value={form.rayon_geofence_m} onChange={(e) => setForm({ ...form, rayon_geofence_m: e.target.value })} />
              </Field>
            </div>
            <div className="flex flex-wrap items-center gap-3 mt-3">
              <button
                type="button"
                onClick={utiliserMaPosition}
                disabled={localisation.etat === 'encours'}
                className="flex items-center gap-2 px-3 py-2 bg-primary/10 text-primary rounded-lg text-xs font-semibold hover:bg-primary/20 transition-all disabled:opacity-50"
              >
                {localisation.etat === 'encours' ? <FiLoader className="animate-spin" size={14} /> : <FiCrosshair size={14} />}
                Utiliser ma position actuelle
              </button>
              {localisation.message && (
                <p role="status" className={`text-xs ${localisation.etat === 'erreur' ? 'text-error' : 'text-on-surface-variant'}`}>
                  {localisation.message}
                </p>
              )}
            </div>
            <p className="text-[11px] text-on-surface-variant mt-2">
              À faire depuis la salle, avec un téléphone : c'est la position de l'appareil qui est relevée.
            </p>
          </div>

          {/* Réseau Wi-Fi */}
          <div className="border-t border-outline-variant/20 pt-4">
            <h4 className="text-sm font-bold text-primary mb-3 flex items-center gap-2"><FiWifi size={16} /> Réseau Wi-Fi</h4>
            {/* Conséquence non évidente, à dire ici : c'est l'administrateur
                qui la déclenche en remplissant ces champs. */}
            <p className="text-xs text-on-surface-variant mb-3">
              Renseigner un réseau rend cette salle validable{' '}
              <span className="font-semibold">uniquement depuis l'application mobile</span> :
              un navigateur ne peut pas lire le nom du réseau. Laissez ces champs
              vides, ou cochez « hors réseau », pour autoriser aussi la page web.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Field label="SSID attendu" htmlFor="salle-ssid">
                <input id="salle-ssid" type="text" placeholder="Ex: IFRI-WiFi" className={champ} value={form.ssid_attendu} onChange={(e) => setForm({ ...form, ssid_attendu: e.target.value })} />
              </Field>
              <Field label="BSSID attendu (MAC)" htmlFor="salle-bssid">
                <input id="salle-bssid" type="text" placeholder="Ex: 00:11:22:33:44:55" className={`${champ} font-mono`} value={form.bssid_attendu} onChange={(e) => setForm({ ...form, bssid_attendu: e.target.value })} />
              </Field>
            </div>
            <label className="flex items-center gap-2 mt-3 cursor-pointer">
              <input type="checkbox" checked={form.hors_reseau} onChange={(e) => setForm({ ...form, hors_reseau: e.target.checked })} className="rounded border-outline-variant/30 text-primary focus:ring-primary/20" />
              <span className="text-xs text-on-surface-variant">Salle hors réseau : aucun contrôle Wi-Fi (GPS seul)</span>
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
      </Modal>

      {/* Modal confirmation suppression */}
      <Modal isOpen={Boolean(showDelete)} onClose={() => setShowDelete(null)} title="Supprimer la salle" size="md"
        aria-describedby="suppression-salle-description">
        <div className="flex items-center gap-3 mb-4">
          <div className="p-2 bg-error/10 rounded-xl"><FiAlertTriangle className="text-error" size={20} /></div>
          {/* La salle visée peut être nulle : `Modal` ne se rend pas fermée, mais
              ses enfants sont construits à chaque rendu de la page. */}
          <p className="text-xs text-on-surface-variant">{showDelete?.nom} ({showDelete?.code})</p>
        </div>
        {/* La règle est énoncée avant le clic, plutôt que découverte par une
            erreur : le serveur refuse si des événements à venir utilisent la
            salle. */}
        <p id="suppression-salle-description" className="text-sm text-on-surface-variant mb-6">
          Cette action est irréversible. Elle sera refusée si des événements à venir
          utilisent cette salle ; les événements passés sont conservés. Pour la retirer
          des listes sans la supprimer, décochez plutôt « Salle active ».
        </p>
        <div className="flex gap-3">
          <button onClick={() => setShowDelete(null)} className="flex-1 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl text-sm font-semibold hover:bg-surface-container transition-colors">Annuler</button>
          <button onClick={handleDelete} disabled={deleting} className="flex items-center justify-center gap-2 flex-1 px-4 py-2.5 bg-error text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50">
            {deleting && <FiLoader className="animate-spin" />}Supprimer</button>
        </div>
      </Modal>
    </div>
  );
}

const Field = ({ label, htmlFor, children }) => (
  <div className="space-y-1">
    <label htmlFor={htmlFor} className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">{label}</label>
    {children}
  </div>
);
