import { useState } from 'react';
import { MdAdd, MdEdit, MdDelete, MdEventAvailable, MdWarningAmber } from 'react-icons/md';
import Modal from '../../components/ui/Modal';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';
import { useToastCtx } from '../../context/ToastContext';
import {
  LIBELLES_STATUT, contenuAnnee, formatDateLongue, joursAvant, pluriel, proposerAnneeSuivante,
} from '../../utils/annees';

const CLASSES_STATUT = {
  terminee: 'bg-slate-100 text-slate-500',
  en_cours: 'bg-emerald-50 text-emerald-700',
  a_venir: 'bg-blue-50 text-blue-700',
};

const champ = 'w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20 focus:border-[#011549] transition-all';
const libelleChamp = 'text-xs font-semibold uppercase tracking-wider text-slate-500';

/** Pourquoi le serveur refusera la suppression ; null si rien ne s'y oppose. */
function obstacleSuppression(annee) {
  if (annee.active) return "C'est l'année en cours de l'université.";

  const choisie = (annee.etablissements || []).filter((e) => !e.suit_universite).map((e) => e.code);
  if (choisie.length) return `Année active de : ${choisie.join(', ')}.`;

  const contenu = contenuAnnee(annee);
  if (contenu) return `Elle contient ${contenu} : sa suppression les effacerait.`;

  return null;
}

/**
 * Années académiques de l'université. Le super administrateur les crée et
 * désigne l'année en cours ; chaque établissement choisit ensuite la sienne.
 */
export default function AnneesUniversitairesPage() {
  const { data, loading, refetch } = useApi('/super-admin/annees-academiques');
  const { addToast } = useToastCtx();
  const annees = data || [];
  const enCours = annees.find((a) => a.active);

  const [formulaire, setFormulaire] = useState(null); // { id?, libelle, date_debut, date_fin }
  const [erreurs, setErreurs] = useState({});
  const [envoi, setEnvoi] = useState(false);
  const [aDesigner, setADesigner] = useState(null);
  const [aSupprimer, setASupprimer] = useState(null);

  // L'année en cours s'achève et rien ne lui succède : les facultés ne
  // pourront pas préparer la rentrée.
  const jours = enCours ? joursAvant(enCours.date_fin) : null;
  const sansSuivante = enCours && !annees.some((a) => (a.date_debut || '') > (enCours.date_debut || ''));
  const proposition = proposerAnneeSuivante(annees);
  const alerteFin = !loading && sansSuivante && jours !== null && jours <= 60;

  const ouvrirCreation = () => { setErreurs({}); setFormulaire(proposition); };
  const ouvrirEdition = (a) => { setErreurs({}); setFormulaire({ id: a.id, libelle: a.libelle, date_debut: a.date_debut, date_fin: a.date_fin }); };

  const enregistrer = async (e) => {
    e.preventDefault();
    setEnvoi(true);
    setErreurs({});
    const { id, ...valeurs } = formulaire;

    try {
      const { data: reponse } = id
        ? await api.put(`/super-admin/annees-academiques/${id}`, valeurs)
        : await api.post('/super-admin/annees-academiques', valeurs);
      addToast?.(reponse?.message || 'Année enregistrée.', 'success');
      setFormulaire(null);
      refetch();
    } catch (err) {
      const errs = err.response?.data?.errors;
      if (errs) setErreurs(Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])));
      else addToast?.(err.response?.data?.message || "L'enregistrement a échoué.", 'error');
    } finally {
      setEnvoi(false);
    }
  };

  const agir = async (requete, fermer) => {
    setEnvoi(true);
    try {
      const { data: reponse } = await requete();
      addToast?.(reponse?.message || 'Fait.', 'success');
      fermer();
      refetch();
    } catch (err) {
      addToast?.(err.response?.data?.message || "L'opération a échoué.", 'error');
    } finally {
      setEnvoi(false);
    }
  };

  const suiveurs = (enCours?.etablissements || []).filter((e) => e.suit_universite);
  const autonomes = annees.flatMap((a) => (a.etablissements || []).filter((e) => !e.suit_universite).map((e) => ({ ...e, annee: a.libelle })));

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-[#011549] font-headline">Années académiques</h1>
          <p className="text-sm text-slate-500 mt-1 max-w-2xl">
            Communes à toutes les facultés et écoles. Chaque établissement choisit ensuite celle sur laquelle il
            travaille ; ceux qui n'ont rien choisi suivent l'année en cours de l'université.
          </p>
        </div>
        <button
          type="button"
          onClick={ouvrirCreation}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#011549] text-white text-sm font-semibold hover:bg-[#011549]/90 transition-colors self-start"
        >
          <MdAdd size={16} aria-hidden="true" /> Nouvelle année
        </button>
      </div>

      {alerteFin && (
        <div role="alert" className="flex flex-wrap items-center gap-3 rounded-xl bg-amber-50 text-amber-900 px-4 py-3 text-sm">
          <MdWarningAmber size={20} className="shrink-0 text-amber-600" aria-hidden="true" />
          <p className="flex-1 min-w-[16rem]">
            {enCours.libelle} {jours < 0 ? `est terminée depuis le ${formatDateLongue(enCours.date_fin)}` : `se termine le ${formatDateLongue(enCours.date_fin)}, dans ${pluriel(jours, 'jour')}`}.
            Aucune année ne lui succède : les établissements ne peuvent pas préparer la rentrée.
          </p>
          <button type="button" onClick={ouvrirCreation} className="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700">
            Créer {proposition.libelle}
          </button>
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center py-16">
          <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin" />
        </div>
      ) : annees.length === 0 ? (
        <div className="bg-white rounded-2xl border border-slate-100 px-6 py-12 text-center text-sm text-slate-500">
          Aucune année académique. Créez la première : elle deviendra l'année en cours.
        </div>
      ) : (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100">
                <th scope="col" className="px-5 py-3 font-semibold">Année</th>
                <th scope="col" className="px-5 py-3 font-semibold">Période</th>
                <th scope="col" className="px-5 py-3 font-semibold whitespace-nowrap" title="Établissements qui travaillent sur cette année">Établissements</th>
                <th scope="col" className="px-5 py-3 font-semibold">Contenu</th>
                <th scope="col" className="px-5 py-3 font-semibold"><span className="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-50">
              {annees.map((a) => {
                const obstacle = obstacleSuppression(a);

                return (
                  <tr key={a.id} className={a.active ? 'bg-[#011549]/[0.02]' : undefined}>
                    <td className="px-5 py-4 align-top">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-semibold text-[#011549] tabular-nums">{a.libelle}</span>
                        <span className={`px-2 py-0.5 rounded-full text-[11px] font-medium ${CLASSES_STATUT[a.statut] || CLASSES_STATUT.terminee}`}>
                          {LIBELLES_STATUT[a.statut] || a.statut}
                        </span>
                      </div>
                      {a.active && <p className="text-xs font-semibold text-[#011549] mt-1">Année en cours de l'université</p>}
                    </td>
                    <td className="px-5 py-4 align-top text-slate-600 whitespace-nowrap">
                      {formatDateLongue(a.date_debut)} → {formatDateLongue(a.date_fin)}
                    </td>
                    <td className="px-5 py-4 align-top">
                      {(a.etablissements || []).length ? (
                        <ul className="flex flex-wrap gap-1.5" aria-label={`Établissements sur ${a.libelle}`}>
                          {a.etablissements.map((e) => (
                            <li
                              key={e.code}
                              title={e.suit_universite ? `${e.nom} — suit l'année en cours de l'université` : `${e.nom} — l'a choisie`}
                              className={`text-xs font-mono px-1.5 py-0.5 rounded ${e.suit_universite ? 'bg-slate-50 text-slate-500 border border-dashed border-slate-300' : 'bg-[#011549]/5 text-[#011549]'}`}
                            >
                              {e.code}
                            </li>
                          ))}
                        </ul>
                      ) : <span className="text-xs text-slate-400">Aucun</span>}
                    </td>
                    <td className="px-5 py-4 align-top text-xs text-slate-500 min-w-[12rem] max-w-[20rem]">
                      {contenuAnnee(a) || 'Vide'}
                    </td>
                    <td className="px-5 py-4 align-top">
                      <div className="flex items-center justify-end gap-1">
                        {!a.active && (
                          <button
                            type="button"
                            onClick={() => setADesigner(a)}
                            className="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-[#011549] hover:bg-[#011549]/5 whitespace-nowrap"
                          >
                            <MdEventAvailable size={15} aria-hidden="true" /> Définir en cours
                          </button>
                        )}
                        <button
                          type="button"
                          onClick={() => ouvrirEdition(a)}
                          className="p-2 rounded-lg text-slate-500 hover:bg-slate-50"
                          aria-label={`Modifier ${a.libelle}`}
                          title="Modifier"
                        >
                          <MdEdit size={16} aria-hidden="true" />
                        </button>
                        <button
                          type="button"
                          onClick={() => setASupprimer(a)}
                          disabled={!!obstacle}
                          className="p-2 rounded-lg text-red-600 hover:bg-red-50 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                          aria-label={`Supprimer ${a.libelle}`}
                          title={obstacle ? `Suppression impossible — ${obstacle}` : 'Supprimer'}
                        >
                          <MdDelete size={16} aria-hidden="true" />
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Création / modification */}
      <Modal isOpen={!!formulaire} onClose={() => !envoi && setFormulaire(null)} title={formulaire?.id ? `Modifier ${formulaire.libelle}` : 'Nouvelle année académique'}>
        {formulaire && (
          <form onSubmit={enregistrer} className="space-y-4" noValidate>
            <div className="space-y-1.5">
              <label htmlFor="annee-libelle" className={libelleChamp}>Libellé</label>
              <input
                id="annee-libelle"
                className={champ}
                value={formulaire.libelle}
                onChange={(e) => setFormulaire({ ...formulaire, libelle: e.target.value })}
                placeholder="2026-2027"
                aria-invalid={!!erreurs.libelle}
                aria-describedby={erreurs.libelle ? 'annee-libelle-erreur' : undefined}
                required
              />
              {erreurs.libelle && <p id="annee-libelle-erreur" className="text-xs text-red-600">{erreurs.libelle}</p>}
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label htmlFor="annee-debut" className={libelleChamp}>Début</label>
                <input
                  id="annee-debut"
                  type="date"
                  className={champ}
                  value={formulaire.date_debut}
                  onChange={(e) => setFormulaire({ ...formulaire, date_debut: e.target.value })}
                  aria-invalid={!!erreurs.date_debut}
                  aria-describedby={erreurs.date_debut ? 'annee-debut-erreur' : undefined}
                  required
                />
                {erreurs.date_debut && <p id="annee-debut-erreur" className="text-xs text-red-600">{erreurs.date_debut}</p>}
              </div>
              <div className="space-y-1.5">
                <label htmlFor="annee-fin" className={libelleChamp}>Fin</label>
                <input
                  id="annee-fin"
                  type="date"
                  className={champ}
                  value={formulaire.date_fin}
                  onChange={(e) => setFormulaire({ ...formulaire, date_fin: e.target.value })}
                  aria-invalid={!!erreurs.date_fin}
                  aria-describedby={erreurs.date_fin ? 'annee-fin-erreur' : undefined}
                  required
                />
                {erreurs.date_fin && <p id="annee-fin-erreur" className="text-xs text-red-600">{erreurs.date_fin}</p>}
              </div>
            </div>
            <p className="text-xs text-slate-500">
              Dates de référence de l'université. Chaque établissement passe sur une année quand il est prêt, pas à ces dates.
            </p>
            <div className="flex justify-end gap-3 pt-2">
              <button type="button" onClick={() => setFormulaire(null)} disabled={envoi} className="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-50 rounded-xl">
                Annuler
              </button>
              <button type="submit" disabled={envoi} className="px-5 py-2.5 bg-[#011549] text-white rounded-xl text-sm font-semibold hover:bg-[#011549]/90 disabled:opacity-50">
                {envoi ? 'Enregistrement…' : formulaire.id ? 'Enregistrer' : `Créer ${formulaire.libelle || "l'année"}`}
              </button>
            </div>
          </form>
        )}
      </Modal>

      {/* Année en cours de l'université */}
      <Modal isOpen={!!aDesigner} onClose={() => !envoi && setADesigner(null)} title={aDesigner ? `Faire de ${aDesigner.libelle} l'année en cours ?` : ''}>
        {aDesigner && (
          <div className="space-y-4 text-sm text-slate-700">
            <ul className="list-disc pl-5 space-y-1.5">
              <li>
                {suiveurs.length
                  ? <>Passent sur {aDesigner.libelle}, parce qu'ils suivent l'année de l'université : <strong>{suiveurs.map((e) => e.code).join(', ')}</strong>.</>
                  : <>Aucun établissement ne suit l'année de l'université : aucun ne change d'année.</>}
              </li>
              {autonomes.length > 0 && (
                <li>Gardent l'année qu'ils ont choisie : {autonomes.map((e) => `${e.code} (${e.annee})`).join(', ')}.</li>
              )}
              {suiveurs.length > 0 && (enCours?.seances_a_venir_count ?? 0) > 0 && (
                <li>
                  Chez eux, {pluriel(enCours.seances_a_venir_count, 'séance déjà planifiée', 'séances déjà planifiées')} de {enCours.libelle} après
                  aujourd'hui {enCours.seances_a_venir_count > 1 ? 'seront retirées' : 'sera retirée'} — aucune n'a de présence.
                </li>
              )}
              {suiveurs.length > 0 && (aDesigner.emplois_du_temps_count ?? 0) === 0 && (
                <li className="text-amber-700 font-medium">
                  {aDesigner.libelle} n'a encore aucun emploi du temps : aucune séance ne sera générée chez eux.
                </li>
              )}
            </ul>
            <div className="flex justify-end gap-3 pt-2">
              <button type="button" onClick={() => setADesigner(null)} disabled={envoi} className="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-50 rounded-xl">
                Annuler
              </button>
              <button
                type="button"
                disabled={envoi}
                onClick={() => agir(() => api.patch(`/super-admin/annees-academiques/${aDesigner.id}/en-cours`), () => setADesigner(null))}
                className="px-5 py-2.5 bg-[#011549] text-white rounded-xl text-sm font-semibold hover:bg-[#011549]/90 disabled:opacity-50"
              >
                Définir {aDesigner.libelle} en cours
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Suppression */}
      <Modal isOpen={!!aSupprimer} onClose={() => !envoi && setASupprimer(null)} title={aSupprimer ? `Supprimer ${aSupprimer.libelle} ?` : ''} size="sm">
        {aSupprimer && (
          <div className="space-y-4 text-sm text-slate-700">
            <p>L'année est vide et aucun établissement ne travaille dessus. Sa suppression est définitive.</p>
            <div className="flex justify-end gap-3">
              <button type="button" onClick={() => setASupprimer(null)} disabled={envoi} className="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:bg-slate-50 rounded-xl">
                Annuler
              </button>
              <button
                type="button"
                disabled={envoi}
                onClick={() => agir(() => api.delete(`/super-admin/annees-academiques/${aSupprimer.id}`), () => setASupprimer(null))}
                className="px-5 py-2.5 bg-red-600 text-white rounded-xl text-sm font-semibold hover:bg-red-700 disabled:opacity-50"
              >
                Supprimer
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  );
}
