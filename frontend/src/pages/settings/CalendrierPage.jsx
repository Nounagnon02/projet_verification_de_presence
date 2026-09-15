import { useEffect, useState } from 'react';
import { FiAlertTriangle, FiLoader, FiTrash2 } from 'react-icons/fi';
import api from '../../api/axios';
import { invalidateApiCache } from '../../api/cache';
import useApi from '../../hooks/useApi';
import { useToastCtx } from '../../context/ToastContext';
import { datesLisibles, questionRetrait } from '../../utils/calendrier';

const PARITES = [
  { value: 'impair', label: 'Semestres impairs', detail: 'S1, S3, S5…' },
  { value: 'pair', label: 'Semestres pairs', detail: 'S2, S4, S6…' },
];

// Les jours fériés sont déclarés par l'université, pas par l'établissement.
const TYPES_FERMETURE = [
  { value: 'vacances', label: 'Vacances' },
  { value: 'examens', label: 'Examens' },
  { value: 'autre', label: 'Autre fermeture' },
];

const FERMETURE_VIDE = { type: 'vacances', libelle: '', date_debut: '', date_fin: '' };
const CHAMP = 'w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed';
const LIBELLE = 'text-xs font-semibold text-on-surface-variant';
const CARTE = 'bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10';
const BOUTON = 'flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50';

const messageErreur = (err, defaut) => {
  const d = err.response?.data;
  return (d?.errors ? Object.values(d.errors).flat().join(' ') : null) || d?.message || defaut;
};

/**
 * Calendrier de l'établissement : quand ont lieu les cours de chaque semestre,
 * et les jours sans cours. La génération des séances depuis l'emploi du temps
 * s'y tient ; sans période déclarée, elle ne crée rien.
 */
export default function CalendrierPage() {
  const { data: annees } = useApi('/admin/annees-academiques');
  const { addToast } = useToastCtx() ?? {};
  const [anneeId, setAnneeId] = useState('');
  const [rechargement, setRechargement] = useState(0);
  const [etat, setEtat] = useState({ cle: '', donnees: null, erreur: '' });
  const [saisie, setSaisie] = useState({ cle: '', valeurs: {} });
  const [fermeture, setFermeture] = useState(FERMETURE_VIDE);
  const [enCours, setEnCours] = useState('');

  const cle = `${anneeId}|${rechargement}`;

  useEffect(() => {
    let annule = false;
    api.get('/admin/calendrier', { params: anneeId ? { annee_id: anneeId } : {} })
      .then(({ data }) => { if (!annule) setEtat({ cle, donnees: data?.data ?? null, erreur: '' }); })
      .catch((err) => { if (!annule) setEtat({ cle, donnees: null, erreur: messageErreur(err, "Le calendrier n'a pas pu être chargé.") }); });
    return () => { annule = true; };
  }, [cle, anneeId]);

  const { donnees } = etat;
  const annee = donnees?.annee ?? null;
  const close = Boolean(annee?.close);
  const periodes = Object.fromEntries((donnees?.periodes ?? []).map((p) => [p.parite, p]));
  const fermetures = donnees?.fermetures ?? [];
  const occupe = Boolean(enCours);

  // Saisie en cours d'une période ; elle repart des valeurs enregistrées à chaque rechargement.
  const valeurs = saisie.cle === cle ? saisie.valeurs : {};
  const valeur = (parite, champ) => valeurs[`${parite}.${champ}`] ?? periodes[parite]?.[champ] ?? '';
  const saisir = (parite, champ, v) => setSaisie({ cle, valeurs: { ...valeurs, [`${parite}.${champ}`]: v } });

  // Le tableau de bord et les Années académiques lisent aussi le calendrier.
  const rafraichir = () => {
    invalidateApiCache();
    setRechargement((n) => n + 1);
  };

  const agir = async (action, requete, echec) => {
    setEnCours(action);
    try {
      const { data } = await requete();
      addToast?.(data?.message || 'Enregistré.', 'success');
      rafraichir();
      return true;
    } catch (err) {
      addToast?.(messageErreur(err, echec), 'error');
      return false;
    } finally {
      setEnCours('');
    }
  };

  const enregistrerPeriode = (e, parite) => {
    e.preventDefault();
    agir(`periode-${parite}`, () => api.put('/admin/calendrier/periodes', {
      annee_id: annee.id, parite, date_debut: valeur(parite, 'date_debut'), date_fin: valeur(parite, 'date_fin'),
    }), "La période n'a pas été enregistrée.");
  };

  const retirerPeriode = (parite, label) => {
    const periode = periodes[parite];
    if (!periode || !window.confirm(`Retirer la période des ${label.toLowerCase()} ? Leurs séances ne seront plus générées depuis l'emploi du temps.`)) return;
    agir(`periode-${parite}`, () => api.delete(`/admin/calendrier/periodes/${periode.id}`), "La période n'a pas été retirée.");
  };

  // Le serveur annonce d'abord ce que la fermeture retirerait : on ne retire
  // pas des séances planifiées sans le dire.
  const declarerFermeture = async (e) => {
    e.preventDefault();
    const corps = { ...fermeture, annee_id: annee.id, date_fin: fermeture.date_fin || fermeture.date_debut };
    setEnCours('fermeture');
    try {
      const { data } = await api.post('/admin/calendrier/fermetures', { ...corps, apercu: true });
      const n = Number(data?.data?.seances_a_retirer ?? 0);
      if (n > 0 && !window.confirm(questionRetrait(n, corps.libelle))) return;
    } catch (err) {
      addToast?.(messageErreur(err, "La fermeture n'a pas été déclarée."), 'error');
      return;
    } finally {
      setEnCours('');
    }

    if (await agir('fermeture', () => api.post('/admin/calendrier/fermetures', corps), "La fermeture n'a pas été déclarée.")) {
      setFermeture(FERMETURE_VIDE);
    }
  };

  const retirerFermeture = (f) => {
    if (!window.confirm(`Retirer « ${f.libelle} » ? La génération planifiée recréera les séances de ces jours.`)) return;
    agir(`fermeture-${f.id}`, () => api.delete(`/admin/calendrier/fermetures/${f.id}`), "La fermeture n'a pas été retirée.");
  };

  return (
    <div className="space-y-8">
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Calendrier</h1>
          <p className="text-sm text-on-surface-variant max-w-2xl mt-1">
            Les séances ne sont générées depuis l'emploi du temps que pendant la période de leur semestre,
            hors vacances, examens et jours fériés. Les jours fériés sont déclarés par l'université.
          </p>
        </div>
        <div className="space-y-1 w-full md:w-56">
          <label htmlFor="calendrier-annee" className={LIBELLE}>Année académique</label>
          <select id="calendrier-annee" value={anneeId || (annee ? String(annee.id) : '')} onChange={(e) => setAnneeId(e.target.value)} className={CHAMP}>
            {(Array.isArray(annees) ? annees : []).map((a) => (
              <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (active)' : ''}{a.close ? ' (close)' : ''}</option>
            ))}
          </select>
        </div>
      </div>

      {etat.erreur && (
        <p role="alert" className="flex items-start gap-2 p-3 bg-error/10 text-error rounded-lg text-sm">
          <FiAlertTriangle className="mt-0.5 shrink-0" aria-hidden="true" /> {etat.erreur}
        </p>
      )}

      {!donnees && !etat.erreur && (
        <div className="py-12 text-center text-on-surface-variant"><FiLoader className="animate-spin mx-auto" aria-label="Chargement" /></div>
      )}

      {donnees && !annee && (
        <p className="py-12 text-center text-on-surface-variant">
          Aucune année active pour votre établissement : choisissez-en une dans Années académiques.
        </p>
      )}

      {annee && (
        <>
          {close && (
            <p className="text-sm text-on-surface-variant bg-surface-container-high rounded-lg px-4 py-3">
              {annee.libelle} est close pour votre établissement : son calendrier se consulte, il ne se modifie plus.
            </p>
          )}

          <section aria-labelledby="titre-semestres" className="space-y-3">
            <h2 id="titre-semestres" className="text-lg font-bold text-on-surface">Semestres</h2>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              {PARITES.map(({ value: parite, label, detail }) => {
                const periode = periodes[parite];
                return (
                  <form key={parite} onSubmit={(e) => enregistrerPeriode(e, parite)} className={`${CARTE} space-y-3`}>
                    <div className="flex items-baseline justify-between gap-2">
                      <h3 className="font-semibold text-on-surface">
                        {label} <span className="text-xs font-normal text-on-surface-variant">{detail}</span>
                      </h3>
                      {periode && !close && (
                        <button type="button" onClick={() => retirerPeriode(parite, label)} disabled={occupe}
                          aria-label={`Retirer la période des ${label.toLowerCase()}`}
                          className="text-xs font-semibold text-error hover:underline disabled:opacity-50">
                          Retirer
                        </button>
                      )}
                    </div>
                    {periode ? (
                      <p className="text-xs text-on-surface-variant">Cours {datesLisibles(periode.date_debut, periode.date_fin)}.</p>
                    ) : (
                      <p className="flex items-start gap-2 text-xs text-on-surface bg-warning-container rounded-lg px-3 py-2">
                        <FiAlertTriangle className="mt-0.5 shrink-0" aria-hidden="true" />
                        Aucune période : les séances de ces semestres ne sont pas générées.
                      </p>
                    )}
                    <div className="grid grid-cols-2 gap-3">
                      <div className="space-y-1">
                        <label htmlFor={`periode-${parite}-debut`} className={LIBELLE}>Début des cours</label>
                        <input id={`periode-${parite}-debut`} type="date" required min={annee.date_debut} max={annee.date_fin}
                          value={valeur(parite, 'date_debut')} onChange={(e) => saisir(parite, 'date_debut', e.target.value)}
                          disabled={close} className={CHAMP} />
                      </div>
                      <div className="space-y-1">
                        <label htmlFor={`periode-${parite}-fin`} className={LIBELLE}>Fin des cours</label>
                        <input id={`periode-${parite}-fin`} type="date" required min={annee.date_debut} max={annee.date_fin}
                          value={valeur(parite, 'date_fin')} onChange={(e) => saisir(parite, 'date_fin', e.target.value)}
                          disabled={close} className={CHAMP} />
                      </div>
                    </div>
                    <div className="flex justify-end">
                      <button type="submit" disabled={close || occupe} className={BOUTON}>
                        {enCours === `periode-${parite}` && <FiLoader className="animate-spin" aria-hidden="true" />} Enregistrer
                      </button>
                    </div>
                  </form>
                );
              })}
            </div>
          </section>

          <section aria-labelledby="titre-fermetures" className="space-y-3">
            <div>
              <h2 id="titre-fermetures" className="text-lg font-bold text-on-surface">Fermetures</h2>
              <p className="text-sm text-on-surface-variant max-w-2xl mt-1">
                Aucune séance n'est générée ces jours-là. Déclarer une fermeture retire les séances à venir
                qu'elle couvre, si elles n'ont jamais été ouvertes et n'ont aucune présence.
              </p>
            </div>

            <div className="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/10 overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-outline-variant/10 text-on-surface-variant text-xs uppercase tracking-wider">
                    <th className="text-left p-3 font-semibold">Type</th>
                    <th className="text-left p-3 font-semibold">Libellé</th>
                    <th className="text-left p-3 font-semibold">Dates</th>
                    <th className="text-left p-3 font-semibold">Déclarée par</th>
                    <th className="p-3"><span className="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody>
                  {fermetures.length === 0 ? (
                    <tr><td colSpan={5} className="p-6 text-center text-on-surface-variant">Aucune fermeture déclarée pour {annee.libelle}.</td></tr>
                  ) : fermetures.map((f) => (
                    <tr key={f.id} className="border-b border-outline-variant/5 last:border-0">
                      <td className="p-3"><span className="px-2 py-0.5 rounded text-xs font-semibold bg-surface-container-high whitespace-nowrap">{f.type_libelle}</span></td>
                      <td className="p-3 font-medium">{f.libelle}</td>
                      <td className="p-3 text-on-surface-variant whitespace-nowrap">{datesLisibles(f.date_debut, f.date_fin)}</td>
                      <td className="p-3 text-on-surface-variant">{f.portee === 'universite' ? "L'université" : 'Votre établissement'}</td>
                      <td className="p-3 text-right">
                        {f.portee === 'faculte' && !close && (
                          <button type="button" onClick={() => retirerFermeture(f)} disabled={occupe}
                            aria-label={`Retirer « ${f.libelle} »`}
                            className="p-2 hover:bg-error/10 rounded-lg transition-colors disabled:opacity-40">
                            <FiTrash2 className="text-error" />
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <form onSubmit={declarerFermeture} className={`${CARTE} space-y-3`}>
              <h3 className="font-semibold text-on-surface">Déclarer une fermeture</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div className="space-y-1">
                  <label htmlFor="fermeture-type" className={LIBELLE}>Type</label>
                  <select id="fermeture-type" value={fermeture.type} onChange={(e) => setFermeture((f) => ({ ...f, type: e.target.value }))} disabled={close} className={CHAMP}>
                    {TYPES_FERMETURE.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                  </select>
                </div>
                <div className="space-y-1">
                  <label htmlFor="fermeture-libelle" className={LIBELLE}>Libellé</label>
                  <input id="fermeture-libelle" required maxLength={120} placeholder="Vacances de Pâques" value={fermeture.libelle}
                    onChange={(e) => setFermeture((f) => ({ ...f, libelle: e.target.value }))} disabled={close} className={CHAMP} />
                </div>
                <div className="space-y-1">
                  <label htmlFor="fermeture-debut" className={LIBELLE}>Du</label>
                  <input id="fermeture-debut" type="date" required min={annee.date_debut} max={annee.date_fin} value={fermeture.date_debut}
                    onChange={(e) => setFermeture((f) => ({ ...f, date_debut: e.target.value }))} disabled={close} className={CHAMP} />
                </div>
                <div className="space-y-1">
                  <label htmlFor="fermeture-fin" className={LIBELLE}>Au</label>
                  <input id="fermeture-fin" type="date" min={fermeture.date_debut || annee.date_debut} max={annee.date_fin} value={fermeture.date_fin}
                    onChange={(e) => setFermeture((f) => ({ ...f, date_fin: e.target.value }))} disabled={close} className={CHAMP} />
                </div>
              </div>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-xs text-on-surface-variant">Sans date de fin, un seul jour.</p>
                <button type="submit" disabled={close || occupe} className={BOUTON}>
                  {enCours === 'fermeture' && <FiLoader className="animate-spin" aria-hidden="true" />} Déclarer
                </button>
              </div>
            </form>
          </section>
        </>
      )}
    </div>
  );
}
