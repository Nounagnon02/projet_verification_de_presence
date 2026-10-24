import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { MdAdd, MdEdit, MdDelete, MdEventAvailable, MdWarningAmber } from 'react-icons/md';
import Modal from '../../components/ui/Modal';
import {
  listerAnneesUniversitaires,
  creerAnneeUniversitaire,
  modifierAnneeUniversitaire,
  definirAnneeEnCours,
  supprimerAnneeUniversitaire,
} from '../../api/resources/anneesUniversitaires';
import { useToastCtx } from '../../context/ToastContext';
import {
  LIBELLES_STATUT,
  contenuAnnee,
  formatDateLongue,
  joursAvant,
  pluriel,
  proposerAnneeSuivante,
} from '../../utils/annees';

const CLASSES_STATUT = {
  terminee: 'bg-surface-container-high text-on-surface-variant',
  en_cours: 'bg-statut-succes-fond text-statut-succes',
  a_venir: 'bg-statut-info-fond text-statut-info',
};

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
  const queryClient = useQueryClient();
  const anneesQuery = useQuery({
    queryKey: ['annees-universitaires'],
    queryFn: ({ signal }) => listerAnneesUniversitaires(signal),
  });
  const { addToast } = useToastCtx();
  const annees = anneesQuery.data?.data || [];
  const loading = anneesQuery.isLoading;
  const refetch = () => queryClient.invalidateQueries({ queryKey: ['annees-universitaires'] });
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

  const ouvrirCreation = () => {
    setErreurs({});
    setFormulaire(proposition);
  };
  const ouvrirEdition = (a) => {
    setErreurs({});
    setFormulaire({ id: a.id, libelle: a.libelle, date_debut: a.date_debut, date_fin: a.date_fin });
  };

  const enregistrer = async (e) => {
    e.preventDefault();
    setEnvoi(true);
    setErreurs({});
    const { id, ...valeurs } = formulaire;

    try {
      const reponse = id ? await modifierAnneeUniversitaire(id, valeurs) : await creerAnneeUniversitaire(valeurs);
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
      const reponse = await requete();
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
  const autonomes = annees.flatMap((a) =>
    (a.etablissements || []).filter((e) => !e.suit_universite).map((e) => ({ ...e, annee: a.libelle })),
  );

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="titre-page">Années académiques</h1>
          <p className="sous-titre-page max-w-2xl">
            Communes à toutes les facultés et écoles. Chaque établissement choisit ensuite celle sur laquelle il
            travaille ; ceux qui n'ont rien choisi suivent l'année en cours de l'université.
          </p>
        </div>
        <button type="button" onClick={ouvrirCreation} className="btn-primaire self-start">
          <MdAdd size={16} aria-hidden="true" /> Nouvelle année
        </button>
      </div>

      {alerteFin && (
        <div
          role="alert"
          className="flex flex-wrap items-center gap-3 rounded-xl border border-statut-alerte/20 bg-statut-alerte-fond px-4 py-3 text-sm text-on-surface"
        >
          <MdWarningAmber size={20} className="shrink-0 text-statut-alerte" aria-hidden="true" />
          <p className="flex-1 min-w-[min(16rem,100%)]">
            {enCours.libelle}{' '}
            {jours < 0
              ? `est terminée depuis le ${formatDateLongue(enCours.date_fin)}`
              : `se termine le ${formatDateLongue(enCours.date_fin)}, dans ${pluriel(jours, 'jour')}`}
            . Aucune année ne lui succède : les établissements ne peuvent pas préparer la rentrée.
          </p>
          <button
            type="button"
            onClick={ouvrirCreation}
            className="btn bg-statut-alerte px-3 py-1.5 text-xs text-statut-alerte-fond hover:bg-statut-alerte/90"
          >
            Créer {proposition.libelle}
          </button>
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center py-16">
          <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin" />
        </div>
      ) : annees.length === 0 ? (
        <div className="carte px-6 py-12 text-center text-sm text-on-surface-variant">
          Aucune année académique. Créez la première : elle deviendra l'année en cours.
        </div>
      ) : (
        <div className="carte overflow-x-auto">
          <table className="tableau">
            <thead>
              <tr>
                <th scope="col">Année</th>
                <th scope="col">Période</th>
                <th scope="col" title="Établissements qui travaillent sur cette année">
                  Établissements
                </th>
                <th scope="col">Contenu</th>
                <th scope="col">
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {annees.map((a) => {
                const obstacle = obstacleSuppression(a);

                return (
                  <tr key={a.id} className={a.active ? 'bg-primary/[0.03]' : undefined}>
                    <td className="align-top">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-semibold text-primary tabular-nums">{a.libelle}</span>
                        <span
                          className={`px-2 py-0.5 rounded-full text-[11px] font-semibold ${CLASSES_STATUT[a.statut] || CLASSES_STATUT.terminee}`}
                        >
                          {LIBELLES_STATUT[a.statut] || a.statut}
                        </span>
                      </div>
                      {a.active && (
                        <p className="text-xs font-semibold text-primary mt-1">Année en cours de l'université</p>
                      )}
                    </td>
                    <td className="align-top text-on-surface-variant whitespace-nowrap">
                      {formatDateLongue(a.date_debut)} → {formatDateLongue(a.date_fin)}
                    </td>
                    <td className="align-top">
                      {(a.etablissements || []).length ? (
                        <ul className="flex flex-wrap gap-1.5" aria-label={`Établissements sur ${a.libelle}`}>
                          {a.etablissements.map((e) => (
                            <li
                              key={e.code}
                              title={
                                e.suit_universite
                                  ? `${e.nom} : suit l'année en cours de l'université`
                                  : `${e.nom} : l'a choisie`
                              }
                              className={`text-xs font-mono px-1.5 py-0.5 rounded ${e.suit_universite ? 'bg-surface-container-low text-on-surface-variant border border-dashed border-outline-variant' : 'bg-primary/5 text-primary'}`}
                            >
                              {e.code}
                            </li>
                          ))}
                        </ul>
                      ) : (
                        <span className="text-xs text-on-surface-variant">Aucun</span>
                      )}
                    </td>
                    <td className="align-top text-xs text-on-surface-variant min-w-[12rem] max-w-[20rem]">
                      {contenuAnnee(a) || 'Vide'}
                    </td>
                    <td className="align-top">
                      <div className="flex items-center justify-end gap-1">
                        {!a.active && (
                          <button
                            type="button"
                            onClick={() => setADesigner(a)}
                            className="btn-fantome px-2.5 py-1.5 text-xs text-primary hover:text-primary"
                          >
                            <MdEventAvailable size={15} aria-hidden="true" /> Définir en cours
                          </button>
                        )}
                        <button
                          type="button"
                          onClick={() => ouvrirEdition(a)}
                          className="p-2 rounded-lg text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface"
                          aria-label={`Modifier ${a.libelle}`}
                          title="Modifier"
                        >
                          <MdEdit size={16} aria-hidden="true" />
                        </button>
                        <button
                          type="button"
                          onClick={() => setASupprimer(a)}
                          disabled={!!obstacle}
                          className="p-2 rounded-lg text-statut-erreur hover:bg-error/10 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                          aria-label={`Supprimer ${a.libelle}`}
                          title={obstacle ? `Suppression impossible : ${obstacle}` : 'Supprimer'}
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
      <Modal
        isOpen={!!formulaire}
        onClose={() => !envoi && setFormulaire(null)}
        title={formulaire?.id ? `Modifier ${formulaire.libelle}` : 'Nouvelle année académique'}
      >
        {formulaire && (
          <form onSubmit={enregistrer} className="space-y-4" noValidate>
            <div>
              <label htmlFor="annee-libelle" className="libelle">
                Libellé
              </label>
              <input
                id="annee-libelle"
                className="champ"
                value={formulaire.libelle}
                onChange={(e) => setFormulaire({ ...formulaire, libelle: e.target.value })}
                placeholder="2026-2027"
                aria-invalid={!!erreurs.libelle}
                aria-describedby={erreurs.libelle ? 'annee-libelle-erreur' : undefined}
                required
              />
              {erreurs.libelle && (
                <p id="annee-libelle-erreur" className="mt-1 text-xs text-statut-erreur">
                  {erreurs.libelle}
                </p>
              )}
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label htmlFor="annee-debut" className="libelle">
                  Début
                </label>
                <input
                  id="annee-debut"
                  type="date"
                  className="champ"
                  value={formulaire.date_debut}
                  onChange={(e) => setFormulaire({ ...formulaire, date_debut: e.target.value })}
                  aria-invalid={!!erreurs.date_debut}
                  aria-describedby={erreurs.date_debut ? 'annee-debut-erreur' : undefined}
                  required
                />
                {erreurs.date_debut && (
                  <p id="annee-debut-erreur" className="mt-1 text-xs text-statut-erreur">
                    {erreurs.date_debut}
                  </p>
                )}
              </div>
              <div>
                <label htmlFor="annee-fin" className="libelle">
                  Fin
                </label>
                <input
                  id="annee-fin"
                  type="date"
                  className="champ"
                  value={formulaire.date_fin}
                  onChange={(e) => setFormulaire({ ...formulaire, date_fin: e.target.value })}
                  aria-invalid={!!erreurs.date_fin}
                  aria-describedby={erreurs.date_fin ? 'annee-fin-erreur' : undefined}
                  required
                />
                {erreurs.date_fin && (
                  <p id="annee-fin-erreur" className="mt-1 text-xs text-statut-erreur">
                    {erreurs.date_fin}
                  </p>
                )}
              </div>
            </div>
            <p className="text-xs text-on-surface-variant">
              Dates de référence de l'université. Chaque établissement passe sur une année quand il est prêt, pas à ces
              dates.
            </p>
            <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
              <button type="button" onClick={() => setFormulaire(null)} disabled={envoi} className="btn-fantome">
                Annuler
              </button>
              <button type="submit" disabled={envoi} className="btn-primaire">
                {envoi ? 'Enregistrement…' : formulaire.id ? 'Enregistrer' : `Créer ${formulaire.libelle || "l'année"}`}
              </button>
            </div>
          </form>
        )}
      </Modal>

      {/* Année en cours de l'université */}
      <Modal
        isOpen={!!aDesigner}
        onClose={() => !envoi && setADesigner(null)}
        title={aDesigner ? `Faire de ${aDesigner.libelle} l'année en cours ?` : ''}
      >
        {aDesigner && (
          <div className="space-y-4 text-sm text-on-surface">
            <ul className="list-disc pl-5 space-y-1.5">
              <li>
                {suiveurs.length ? (
                  <>
                    Passent sur {aDesigner.libelle}, parce qu'ils suivent l'année de l'université :{' '}
                    <strong>{suiveurs.map((e) => e.code).join(', ')}</strong>.
                  </>
                ) : (
                  <>Aucun établissement ne suit l'année de l'université : aucun ne change d'année.</>
                )}
              </li>
              {autonomes.length > 0 && (
                <li>
                  Gardent l'année qu'ils ont choisie : {autonomes.map((e) => `${e.code} (${e.annee})`).join(', ')}.
                </li>
              )}
              {suiveurs.length > 0 && (enCours?.seances_a_venir_count ?? 0) > 0 && (
                <li>
                  Chez eux, {pluriel(enCours.seances_a_venir_count, 'séance déjà planifiée', 'séances déjà planifiées')}{' '}
                  de {enCours.libelle} après aujourd'hui{' '}
                  {enCours.seances_a_venir_count > 1 ? 'seront retirées' : 'sera retirée'}, aucune n'a de présence.
                </li>
              )}
              {suiveurs.length > 0 && (aDesigner.emplois_du_temps_count ?? 0) === 0 && (
                <li className="text-statut-alerte font-medium">
                  {aDesigner.libelle} n'a encore aucun emploi du temps : aucune séance ne sera générée chez eux.
                </li>
              )}
            </ul>
            <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
              <button type="button" onClick={() => setADesigner(null)} disabled={envoi} className="btn-fantome">
                Annuler
              </button>
              <button
                type="button"
                disabled={envoi}
                onClick={() =>
                  agir(
                    () => definirAnneeEnCours(aDesigner.id),
                    () => setADesigner(null),
                  )
                }
                className="btn-primaire"
              >
                Définir {aDesigner.libelle} en cours
              </button>
            </div>
          </div>
        )}
      </Modal>

      {/* Suppression */}
      <Modal
        isOpen={!!aSupprimer}
        onClose={() => !envoi && setASupprimer(null)}
        title={aSupprimer ? `Supprimer ${aSupprimer.libelle} ?` : ''}
        size="sm"
      >
        {aSupprimer && (
          <div className="space-y-4 text-sm text-on-surface">
            <p>L'année est vide et aucun établissement ne travaille dessus. Sa suppression est définitive.</p>
            <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
              <button type="button" onClick={() => setASupprimer(null)} disabled={envoi} className="btn-fantome">
                Annuler
              </button>
              <button
                type="button"
                disabled={envoi}
                onClick={() =>
                  agir(
                    () => supprimerAnneeUniversitaire(aSupprimer.id),
                    () => setASupprimer(null),
                  )
                }
                className="btn-danger"
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
