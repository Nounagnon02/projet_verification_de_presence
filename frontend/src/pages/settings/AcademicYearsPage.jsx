import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FiCalendar, FiArrowRight, FiLoader, FiCopy, FiAlertTriangle, FiInfo, FiCheckCircle } from 'react-icons/fi';
import Modal from '../../components/ui/Modal';
import { listerAnnees } from '../../api/resources/reference';
import { activerAnnee } from '../../api/resources/anneesAcademiques';
import { useToastCtx } from '../../context/ToastContext';
import PreparationAnnee from './PreparationAnnee';
import AlerteCalendrier from '../../components/ui/AlerteCalendrier';
import {
  LIBELLES_STATUT, anneesApres, contenuAnnee, formatDateLongue, joursAvant, pluriel,
} from '../../utils/annees';

// Jetons pleins : les couleurs du thème sont des variables hexadécimales, et
// Tailwind ne produit rien pour « bg-secondary/10 » ou « bg-primary/10 ».
const CLASSES_STATUT = {
  terminee: 'bg-surface-container-high text-on-surface-variant',
  en_cours: 'bg-secondary-container text-on-secondary-container',
  a_venir: 'border border-primary text-primary',
};

/** Un libellé d'année ne se coupe pas à son trait d'union. */
const Libelle = ({ children }) => <span className="whitespace-nowrap">{children}</span>;

/**
 * Ce qui doit être signalé en tête de page : l'année active s'achève et la
 * suivante n'existe pas, ou elle est finie et la suivante attend.
 */
function alerteAnnee(active, suivante) {
  if (!active) {
    return { niveau: 'erreur', texte: "Aucune année n'est active pour votre établissement. Choisissez-en une ci-dessous : les inscriptions et la génération des séances en dépendent." };
  }

  const jours = joursAvant(active.date_fin);

  if (!suivante && jours !== null && jours <= 30) {
    const quand = jours < 0
      ? `est terminée depuis le ${formatDateLongue(active.date_fin)}`
      : jours === 0 ? "se termine aujourd'hui" : `se termine le ${formatDateLongue(active.date_fin)}, dans ${pluriel(jours, 'jour')}`;

    return {
      niveau: 'attention',
      texte: `${active.libelle} ${quand}, et l'année suivante n'a pas encore été créée. Seul le super administrateur de l'université peut la créer : demandez-lui de l'ouvrir.`,
    };
  }

  if (suivante && active.statut === 'terminee') {
    return {
      niveau: 'info',
      texte: `${active.libelle} est terminée depuis le ${formatDateLongue(active.date_fin)}. Passez sur ${suivante.libelle} quand votre établissement a bouclé l'année.`,
    };
  }

  return null;
}

export default function AcademicYearsPage() {
  const anneesQuery = useQuery({ queryKey: ['annees-academiques'], queryFn: () => listerAnnees() });
  const [cible, setCible] = useState(null);
  const [activation, setActivation] = useState(false);
  const [aPreparer, setAPreparer] = useState(null);
  const { addToast } = useToastCtx();
  const queryClient = useQueryClient();

  const loading = anneesQuery.isLoading;
  const annees = anneesQuery.data?.data ?? [];
  const activeYear = annees.find((y) => y.active);
  const suivantes = anneesApres(annees, activeYear);
  const alerte = loading ? null : alerteAnnee(activeYear, suivantes[0]);

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['annees-academiques'] });

  const activer = async () => {
    setActivation(true);
    try {
      const data = await activerAnnee(cible.id);
      addToast?.(data?.message || `${cible.libelle} est désormais l'année active.`, 'success');
      setCible(null);
      rafraichir();
    } catch (err) {
      addToast?.(err.response?.data?.message || "Le changement d'année a échoué.", 'error');
    } finally {
      setActivation(false);
    }
  };

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold font-headline text-primary">Années académiques</h1>
        <p className="text-sm text-on-surface-variant max-w-2xl mt-1">
          Les années sont communes à toute l'université : le super administrateur les crée.
          Vous choisissez celle sur laquelle travaille votre établissement.
        </p>
      </div>

      {alerte && (
        <div
          role={alerte.niveau === 'info' ? 'status' : 'alert'}
          className={`flex items-start gap-3 rounded-xl px-4 py-3 mb-6 text-sm text-on-surface ${
            alerte.niveau === 'info' ? 'bg-surface-container-high' : 'bg-warning-container'
          }`}
        >
          {alerte.niveau === 'info'
            ? <FiInfo className="text-primary shrink-0 mt-0.5" aria-hidden="true" />
            : <FiAlertTriangle className="shrink-0 mt-0.5" aria-hidden="true" />}
          <p>{alerte.texte}</p>
        </div>
      )}

      <AlerteCalendrier className="mb-6" />

      {loading ? (
        <div className="text-center py-12 text-on-surface-variant">Chargement...</div>
      ) : annees.length === 0 ? (
        <div className="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-xxl">
          Aucune année académique n'a encore été créée. Le super administrateur de l'université les ouvre.
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {annees.map((year) => {
            const contenu = contenuAnnee(year);
            const apresActive = suivantes.some((s) => s.id === year.id);

            return (
              <article
                key={year.id}
                aria-label={year.libelle}
                className={`bg-surface-container-lowest rounded-xxl p-5 shadow-sm border-2 flex flex-col gap-4 ${
                  year.active ? 'border-primary' : 'border-transparent'
                }`}
              >
                <div className="flex items-start gap-3">
                  <div className="p-2.5 rounded-xl bg-surface-container-high">
                    <FiCalendar className={year.active ? 'text-primary' : 'text-outline'} size={20} aria-hidden="true" />
                  </div>
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-bold text-primary text-base">{year.libelle}</h2>
                      <span className={`px-2 py-0.5 rounded-full text-[11px] font-semibold ${CLASSES_STATUT[year.statut] || CLASSES_STATUT.terminee}`}>
                        {LIBELLES_STATUT[year.statut] || year.statut}
                      </span>
                    </div>
                    <div className="flex flex-wrap gap-2 mt-1.5">
                      {year.active && (
                        <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-primary">
                          <FiCheckCircle size={12} aria-hidden="true" /> Active pour votre établissement
                        </span>
                      )}
                      {!year.active && year.en_cours_universite && (
                        <span className="text-[11px] text-on-surface-variant">Année en cours de l'université</span>
                      )}
                    </div>
                  </div>
                </div>

                <div className="bg-surface-container-high rounded-xl p-3.5 flex items-center gap-3 text-sm">
                  <div className="flex-1">
                    <p className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider mb-0.5">Début</p>
                    <p className="font-semibold text-on-surface">{formatDateLongue(year.date_debut)}</p>
                  </div>
                  <FiArrowRight size={16} className="text-outline shrink-0" aria-hidden="true" />
                  <div className="flex-1 text-right">
                    <p className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider mb-0.5">Fin</p>
                    <p className="font-semibold text-on-surface">{formatDateLongue(year.date_fin)}</p>
                  </div>
                </div>

                <p className="text-xs text-on-surface-variant">
                  {contenu || 'Rien encore pour votre établissement'}
                </p>

                {!year.active && (
                  <div className="flex flex-wrap gap-2 mt-auto">
                    <button
                      type="button"
                      onClick={() => setCible(year)}
                      className="px-3 py-2 rounded-lg text-xs font-semibold border border-outline-variant text-primary hover:bg-surface-container-high transition-colors"
                    >
                      Travailler sur {year.libelle}
                    </button>
                    {/* On prépare une année à partir de l'année active : seulement
                        vers les années qui la suivent. */}
                    {apresActive && (
                      <button
                        type="button"
                        onClick={() => setAPreparer(year)}
                        className="flex items-center gap-2 px-3 py-2 bg-surface-container-high text-primary rounded-lg text-xs font-semibold hover:bg-surface-container-highest transition-all"
                      >
                        <FiCopy size={12} aria-hidden="true" /> Préparer {year.libelle}
                      </button>
                    )}
                  </div>
                )}
              </article>
            );
          })}
        </div>
      )}

      {aPreparer && activeYear && (
        <PreparationAnnee cible={aPreparer} source={activeYear} onClose={() => setAPreparer(null)} onPreparee={rafraichir} />
      )}

      <Modal isOpen={!!cible} onClose={() => !activation && setCible(null)} title={cible ? `Passer sur ${cible.libelle} ?` : ''}>
        {cible && (
          <ConsequencesBascule cible={cible} active={activeYear} />
        )}
        <div className="flex justify-end gap-3 pt-5">
          <button type="button" onClick={() => setCible(null)} disabled={activation} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">
            Annuler
          </button>
          <button type="button" onClick={activer} disabled={activation} className="flex items-center justify-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50 transition-all">
            {activation && <FiLoader className="animate-spin" />}
            {cible ? `Passer sur ${cible.libelle}` : ''}
          </button>
        </div>
      </Modal>
    </div>
  );
}

/** Ce que change le passage d'une année à l'autre, dit avant le clic. */
function ConsequencesBascule({ cible, active }) {
  const creneaux = cible.emplois_du_temps_count ?? 0;
  const retirees = active?.seances_a_venir_count ?? 0;

  return (
    <div className="space-y-4 text-sm text-on-surface">
      <p>
        {active ? <>Votre établissement travaille aujourd'hui sur <strong><Libelle>{active.libelle}</Libelle></strong>. </> : null}
        En passant sur <Libelle>{cible.libelle}</Libelle> :
      </p>
      <ul className="list-disc pl-5 space-y-1.5">
        <li>les nouvelles inscriptions iront dans {cible.libelle} ;</li>
        {creneaux > 0 ? (
          <li>les séances seront générées chaque nuit depuis son emploi du temps ({pluriel(creneaux, 'créneau', 'créneaux')}) ;</li>
        ) : (
          <li>
            <strong>aucune séance ne sera générée</strong> : {cible.libelle} n'a pas encore d'emploi du temps pour votre établissement ;
          </li>
        )}
        {active && retirees > 0 && (
          <li>
            {pluriel(retirees, 'séance déjà planifiée', 'séances déjà planifiées')} de {active.libelle} après aujourd'hui
            {retirees > 1 ? ' seront retirées' : ' sera retirée'} — aucune n'a de présence ;
          </li>
        )}
        <li>
          les écrans s'ouvriront sur {cible.libelle}
          {active ? <> ; les données de {active.libelle} restent consultables</> : null}.
        </li>
      </ul>
      {cible.statut === 'terminee' && (
        <p className="flex items-start gap-2 rounded-lg bg-warning-container px-3 py-2">
          <FiAlertTriangle className="shrink-0 mt-0.5" aria-hidden="true" />
          {cible.libelle} est terminée : y revenir ne sert qu'à corriger un changement d'année fait par erreur.
        </p>
      )}
      <p className="text-xs text-on-surface-variant">Les autres établissements ne sont pas concernés.</p>
    </div>
  );
}
