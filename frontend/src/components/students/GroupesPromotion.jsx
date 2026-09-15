import { useEffect, useState } from 'react';
import { FiLoader, FiTrash2, FiAlertTriangle, FiCheck } from 'react-icons/fi';
import api from '../../api/axios';
import Modal from '../ui/Modal';

const TYPES = [{ value: 'td', label: 'TD' }, { value: 'tp', label: 'TP' }];
const CHAMP = 'w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50';
const LIBELLE = 'text-xs font-semibold text-on-surface-variant';

const messageErreur = (err, defaut) => {
  const d = err.response?.data;
  return (d?.errors ? Object.values(d.errors).flat().join(' ') : null) || d?.message || defaut;
};

/**
 * Groupes de TD et de TP d'une promotion (une filière, une année) : répartir
 * les étudiants, créer un groupe, supprimer un groupe qui n'a pas de séance.
 */
export default function GroupesPromotion({ isOpen, onClose, annees, filieres, filiereInitiale = '', anneeInitiale = '', onModifie }) {
  const [filiereId, setFiliereId] = useState('');
  const [anneeId, setAnneeId] = useState('');
  const [etat, setEtat] = useState({ cle: '', liste: [] });
  const [rechargement, setRechargement] = useState(0);
  const [typeRepartition, setTypeRepartition] = useState('td');
  const [nombre, setNombre] = useState('2');
  const [typeCreation, setTypeCreation] = useState('td');
  const [libelle, setLibelle] = useState('');
  const [enCours, setEnCours] = useState(false);
  const [retour, setRetour] = useState(null);

  // À chaque ouverture, la promotion filtrée dans la page.
  const [ouvert, setOuvert] = useState(false);
  if (isOpen !== ouvert) {
    setOuvert(isOpen);
    if (isOpen) {
      setFiliereId(String(filiereInitiale || ''));
      setAnneeId(String(anneeInitiale || ''));
      setRetour(null);
    }
  }

  const cle = isOpen && filiereId && anneeId ? `${filiereId}|${anneeId}|${rechargement}` : '';

  useEffect(() => {
    if (!cle) return undefined;
    let annule = false;
    const [filiere_id, annee_id] = cle.split('|');
    api.get('/admin/groupes', { params: { filiere_id, annee_id } })
      .then(({ data }) => { if (!annule) setEtat({ cle, liste: data?.data ?? [] }); })
      .catch(() => { if (!annule) setEtat({ cle, liste: [] }); });
    return () => { annule = true; };
  }, [cle]);

  const charge = etat.cle === cle;
  const groupes = charge ? etat.liste : [];
  const close = Boolean(annees.find((a) => String(a.id) === String(anneeId))?.close);
  const verrouille = close || enCours;

  const agir = async (requete, succes) => {
    setEnCours(true);
    setRetour(null);
    try {
      const { data } = await requete();
      setRetour({ ok: true, message: data?.message || succes });
      setRechargement((n) => n + 1);
      onModifie?.();
      return true;
    } catch (err) {
      setRetour({ ok: false, message: messageErreur(err, 'Opération impossible.') });
      return false;
    } finally {
      setEnCours(false);
    }
  };

  const promotion = { filiere_id: Number(filiereId), annee_id: Number(anneeId) };

  const repartir = (e) => {
    e.preventDefault();
    agir(() => api.post('/admin/groupes/repartir', { ...promotion, type: typeRepartition, nombre: Number(nombre) }), 'Répartition faite.');
  };

  const creer = async (e) => {
    e.preventDefault();
    if (!libelle.trim()) return;
    if (await agir(() => api.post('/admin/groupes', { ...promotion, type: typeCreation, libelle: libelle.trim() }), 'Groupe créé.')) {
      setLibelle('');
    }
  };

  const supprimer = (g) => {
    const type = g.type.toUpperCase();
    if (!window.confirm(`Supprimer le groupe de ${type} ${g.libelle} ? Ses ${g.etudiants_count} étudiant(s) n'auront plus de groupe de ${type}.`)) return;
    agir(() => api.delete(`/admin/groupes/${g.id}`), 'Groupe supprimé.');
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Groupes de TD et de TP" size="lg">
      <div className="space-y-5">
        <p className="text-sm text-on-surface-variant">
          Un groupe appartient à une promotion : une filière, une année. Une séance de TD ou de TP donnée à un
          groupe n'attend que ses membres, et chaque groupe reçoit tout le volume de TD ou de TP du cours.
        </p>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <label htmlFor="groupes-filiere" className={LIBELLE}>Filière</label>
            <select id="groupes-filiere" value={filiereId} onChange={(e) => { setFiliereId(e.target.value); setRetour(null); }} className={CHAMP}>
              <option value="">Sélectionner…</option>
              {filieres.map((f) => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
            </select>
          </div>
          <div className="space-y-1.5">
            <label htmlFor="groupes-annee" className={LIBELLE}>Année académique</label>
            <select id="groupes-annee" value={anneeId} onChange={(e) => { setAnneeId(e.target.value); setRetour(null); }} className={CHAMP}>
              <option value="">Sélectionner…</option>
              {annees.map((a) => <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (active)' : ''}{a.close ? ' (close)' : ''}</option>)}
            </select>
          </div>
        </div>

        {retour && (
          <p role={retour.ok ? 'status' : 'alert'} className={`flex items-start gap-2 p-3 rounded-lg text-sm ${retour.ok ? 'bg-secondary-container text-on-secondary-container' : 'bg-error/10 text-error'}`}>
            {retour.ok ? <FiCheck className="mt-0.5 shrink-0" aria-hidden="true" /> : <FiAlertTriangle className="mt-0.5 shrink-0" aria-hidden="true" />}
            <span>{retour.message}</span>
          </p>
        )}

        {close && (
          <p className="text-xs text-on-surface-variant">Cette année est close : ses groupes se consultent, ils ne se modifient plus.</p>
        )}

        {cle && (
          <>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {TYPES.map((t) => {
                const liste = groupes.filter((g) => g.type === t.value);
                return (
                  <section key={t.value} aria-label={`Groupes de ${t.label}`} className="rounded-xl bg-surface-container-low p-3">
                    <h3 className="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-2">Groupes de {t.label}</h3>
                    {!charge ? (
                      <FiLoader className="animate-spin text-primary" />
                    ) : liste.length === 0 ? (
                      <p className="text-xs text-on-surface-variant">Aucun groupe.</p>
                    ) : (
                      <ul className="space-y-1">
                        {liste.map((g) => (
                          <li key={g.id} className="flex items-center justify-between gap-2 text-sm">
                            <span>
                              <span className="font-semibold">{g.libelle}</span>{' '}
                              <span className="text-xs text-on-surface-variant tabular-nums">{g.etudiants_count} étudiant{g.etudiants_count > 1 ? 's' : ''}</span>
                            </span>
                            <button type="button" onClick={() => supprimer(g)} disabled={verrouille}
                              aria-label={`Supprimer le groupe de ${t.label} ${g.libelle}`}
                              className="p-1.5 hover:bg-error/10 rounded-lg transition-colors disabled:opacity-40">
                              <FiTrash2 className="text-error" />
                            </button>
                          </li>
                        ))}
                      </ul>
                    )}
                  </section>
                );
              })}
            </div>

            <form onSubmit={repartir} className="space-y-2 rounded-xl border border-outline-variant/20 p-3">
              <h3 className="text-sm font-semibold text-on-surface">Répartir la promotion</h3>
              <div className="flex flex-wrap items-end gap-3">
                <div className="space-y-1.5 w-28">
                  <label htmlFor="groupes-type" className={LIBELLE}>Type</label>
                  <select id="groupes-type" value={typeRepartition} onChange={(e) => setTypeRepartition(e.target.value)} className={CHAMP}>
                    {TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </div>
                <div className="space-y-1.5 w-28">
                  <label htmlFor="groupes-nombre" className={LIBELLE}>Nombre</label>
                  <input id="groupes-nombre" type="number" min="1" max="30" required value={nombre} onChange={(e) => setNombre(e.target.value)} className={CHAMP} />
                </div>
                <button type="submit" disabled={verrouille}
                  className="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50">
                  {enCours && <FiLoader className="animate-spin" />} Répartir
                </button>
              </div>
              <p className="text-xs text-on-surface-variant">
                Par ordre de matricule, en groupes G1, G2… de même taille à un étudiant près. Remplace la répartition
                de ce type pour la promotion.
              </p>
            </form>

            <form onSubmit={creer} className="space-y-2 rounded-xl border border-outline-variant/20 p-3">
              <h3 className="text-sm font-semibold text-on-surface">Créer un groupe</h3>
              <div className="flex flex-wrap items-end gap-3">
                <div className="space-y-1.5 w-28">
                  <label htmlFor="groupes-type-creation" className={LIBELLE}>Type</label>
                  <select id="groupes-type-creation" value={typeCreation} onChange={(e) => setTypeCreation(e.target.value)} className={CHAMP}>
                    {TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </div>
                <div className="space-y-1.5 flex-1 min-w-[8rem]">
                  <label htmlFor="groupes-libelle" className={LIBELLE}>Libellé</label>
                  <input id="groupes-libelle" maxLength={30} value={libelle} onChange={(e) => setLibelle(e.target.value)} placeholder="G3, TP-A…" className={CHAMP} />
                </div>
                <button type="submit" disabled={verrouille || !libelle.trim()}
                  className="px-4 py-2.5 bg-secondary/10 text-secondary rounded-xl text-sm font-semibold hover:bg-secondary/20 disabled:opacity-50">
                  Créer
                </button>
              </div>
              <p className="text-xs text-on-surface-variant">Pour changer un étudiant de groupe, modifiez-le dans la liste.</p>
            </form>
          </>
        )}
      </div>
    </Modal>
  );
}
