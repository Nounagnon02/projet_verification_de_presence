import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { FiLoader, FiCheckCircle, FiAlertTriangle } from 'react-icons/fi';
import Modal from '../../components/ui/Modal';
import { previsualiserPreparation, preparerAnnee } from '../../api/resources/anneesAcademiques';
import { pluriel } from '../../utils/annees';

const decompte = ({ ues, ecs, creneaux }) =>
  [pluriel(ues, 'UE', 'UE'), pluriel(ecs, 'EC', 'EC'), pluriel(creneaux, 'créneau', 'créneaux')].join(' · ');

/**
 * Préparer une année à partir de l'année active : filières, maquette UE/EC et
 * emploi du temps. Une année neuve était vide — ni inscription possible, ni
 * séance générée.
 */
export default function PreparationAnnee({ cible, source, onClose, onPreparee }) {
  const apercuQuery = useQuery({
    queryKey: ['preparation-annee', cible.id, source.id],
    queryFn: ({ signal }) => previsualiserPreparation(cible.id, source.id, signal),
  });
  const filieres = apercuQuery.data?.data?.filieres ?? null;
  const [erreurEnvoi, setErreurEnvoi] = useState('');
  const [choix, setChoix] = useState(() => new Set());
  const [avecEdt, setAvecEdt] = useState(true);
  const [envoi, setEnvoi] = useState(false);
  const [bilan, setBilan] = useState(null);

  // Cochées d'office, une fois l'aperçu chargé : ce qui a une maquette à
  // copier et n'en a pas déjà une. Ajusté pendant le rendu (pas un effet) :
  // la donnée est déjà là quand ce composant s'affiche.
  const [filieresVues, setFilieresVues] = useState(null);
  if (filieres && filieres !== filieresVues) {
    setFilieresVues(filieres);
    setChoix(new Set(filieres.filter((f) => !f.deja_preparee && f.source.ues > 0).map((f) => f.id)));
  }

  const erreurChargement = apercuQuery.isError
    ? (apercuQuery.error?.response?.data?.message || "L'aperçu n'a pas pu être chargé.")
    : '';
  const erreur = erreurEnvoi || erreurChargement;

  const basculer = (id) => setChoix((actuel) => {
    const suivant = new Set(actuel);
    if (suivant.has(id)) suivant.delete(id); else suivant.add(id);
    return suivant;
  });

  const preparer = async () => {
    setEnvoi(true);
    setErreurEnvoi('');
    try {
      const data = await preparerAnnee(cible.id, {
        source_annee_id: source.id,
        filiere_ids: [...choix],
        avec_edt: avecEdt,
      });
      setBilan({ message: data?.message, filieres: data?.data?.filieres ?? [] });
      onPreparee?.();
    } catch (err) {
      setErreurEnvoi(err.response?.data?.message || 'La préparation a échoué.');
    } finally {
      setEnvoi(false);
    }
  };

  return (
    <Modal isOpen onClose={() => !envoi && onClose()} title={`Préparer ${cible.libelle}`} size="lg">
      {bilan ? (
        <div className="space-y-4 text-sm text-on-surface">
          <p className="flex items-start gap-2 font-medium">
            <FiCheckCircle className="text-secondary shrink-0 mt-0.5" aria-hidden="true" /> {bilan.message}
          </p>
          <ul className="space-y-1.5">
            {bilan.filieres.map((f) => (
              <li key={f.code}>
                <strong>{f.code}</strong> : {f.maquette_gardee ? 'maquette déjà en place, gardée' : `${pluriel(f.ues, 'UE', 'UE')} et ${pluriel(f.ecs, 'EC', 'EC')} copiés`}
                {avecEdt && (f.edt_garde ? ' ; emploi du temps déjà en place, gardé' : ` ; ${pluriel(f.creneaux, 'créneau copié', 'créneaux copiés')}`)}
                {f.ignorees?.length > 0 && (
                  <ul className="mt-1 list-disc pl-5 text-xs text-on-surface-variant">
                    {f.ignorees.map((motif) => <li key={motif}>{motif}</li>)}
                  </ul>
                )}
              </li>
            ))}
          </ul>
          <div className="rounded-lg bg-surface-container-high px-3 py-2.5">
            <p>
              Et ensuite : quand {source.libelle} est terminée, passez sur {cible.libelle} depuis sa carte, puis promouvez
              vos étudiants — la promotion les inscrit aux EC de {cible.libelle}.
            </p>
            <Link to="/students" className="inline-block mt-1.5 font-semibold text-primary hover:underline">Aller à la promotion</Link>
          </div>
          <div className="flex justify-end">
            <button type="button" onClick={onClose} className="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90">
              Fermer
            </button>
          </div>
        </div>
      ) : (
        <div className="space-y-4 text-sm text-on-surface">
          {erreur && (
            <p role="alert" className="flex items-start gap-2 rounded-lg bg-error-container px-3 py-2 text-on-error-container">
              <FiAlertTriangle className="shrink-0 mt-0.5" aria-hidden="true" /> {erreur}
            </p>
          )}

          {!filieres && !erreur && <p className="text-on-surface-variant">Chargement…</p>}

          {filieres?.length === 0 && (
            <p className="text-on-surface-variant">Aucune filière de votre établissement en {source.libelle}.</p>
          )}

          {filieres?.length > 0 && (
            <>
              <fieldset className="space-y-2">
                <legend className="mb-2 text-on-surface-variant">
                  Copie depuis <strong className="text-on-surface">{source.libelle}</strong> les filières choisies, leur maquette
                  (UE et EC) et, si vous le voulez, leur emploi du temps. Les étudiants n'y sont pas copiés.
                </legend>
                {filieres.map((f) => (
                  <label key={f.id} htmlFor={`prep-f-${f.id}`} className="flex items-start gap-3 rounded-lg px-3 py-2 hover:bg-surface-container-high cursor-pointer">
                    <input
                      id={`prep-f-${f.id}`}
                      type="checkbox"
                      className="mt-1"
                      checked={choix.has(f.id)}
                      onChange={() => basculer(f.id)}
                    />
                    <span className="min-w-0">
                      <span className="font-semibold">{f.code}</span>
                      <span className="text-on-surface-variant"> — {f.intitule}</span>
                      <span className="block text-xs text-on-surface-variant">
                        {f.deja_preparee
                          ? `Déjà ${pluriel(f.cible.ues, 'UE', 'UE')} en ${cible.libelle} : maquette gardée${f.cible.creneaux ? '' : ", emploi du temps à compléter"}`
                          : `${decompte(f.source)} à copier`}
                      </span>
                    </span>
                  </label>
                ))}
              </fieldset>

              <label htmlFor="prep-edt" className="flex items-start gap-3 px-3">
                <input id="prep-edt" type="checkbox" className="mt-1" checked={avecEdt} onChange={(e) => setAvecEdt(e.target.checked)} />
                <span>
                  Copier aussi l'emploi du temps
                  <span className="block text-xs text-on-surface-variant">
                    Vérifiez-le ensuite : salles et horaires changent souvent d'une année à l'autre.
                  </span>
                </span>
              </label>
            </>
          )}

          <div className="flex justify-end gap-3 pt-2">
            <button type="button" onClick={onClose} disabled={envoi} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl">
              Annuler
            </button>
            <button
              type="button"
              onClick={preparer}
              disabled={envoi || choix.size === 0}
              className="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50"
            >
              {envoi && <FiLoader className="animate-spin" aria-hidden="true" />}
              Préparer {cible.libelle}
            </button>
          </div>
        </div>
      )}
    </Modal>
  );
}
