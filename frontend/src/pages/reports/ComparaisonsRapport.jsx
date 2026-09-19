import { useQuery } from '@tanstack/react-query';
import { FiLoader } from 'react-icons/fi';
import BarreTaux from '../../components/charts/BarreTaux';
import { couleurTaux, libelleTaux } from '../../utils/taux';
import { rapportFiliereStats, rapportComparaisonSemestres, rapportAnneeStats } from '../../api/resources/rapports';

const CARTE = 'bg-surface-container-lowest rounded-2xl border border-outline-variant/10 p-5';

const Chargement = () => (
  <div className="flex justify-center py-8"><FiLoader className="animate-spin text-primary w-6 h-6" aria-label="Chargement" /></div>
);

const Vide = ({ children }) => (
  <p className="py-6 px-4 text-center text-sm text-on-surface-variant border border-dashed border-outline-variant/40 rounded-xl">{children}</p>
);

/** Une ligne « libellé — taux — présents / attendus », avec sa barre. */
const LigneTaux = ({ rang, libelle, detail, taux, presents, attendus, surlignee = false, puce }) => {
  const avecSeances = attendus > 0;

  return (
    <li className={`grid grid-cols-[1.75rem_minmax(0,1fr)_auto] gap-x-3 gap-y-1.5 items-baseline rounded-lg ${surlignee ? 'bg-primary/5 ring-1 ring-primary/20 -mx-2 px-2 py-1.5' : ''}`}>
      <span className="font-mono text-xs text-on-surface-variant">{rang}</span>
      <span className="min-w-0 text-sm">
        <span className="font-semibold text-on-surface">{libelle}</span>
        {puce && <span className="ml-2 text-[10px] font-semibold uppercase tracking-wider text-primary">{puce}</span>}
        {detail && <span className="ml-2 text-xs text-on-surface-variant">{detail}</span>}
      </span>
      <span className="text-sm text-right tabular-nums whitespace-nowrap">
        {avecSeances ? (
          <>
            <b style={{ color: couleurTaux(taux) }}>{libelleTaux(taux)}</b>
            <span className="ml-1.5 text-xs text-on-surface-variant">{presents} / {attendus}</span>
          </>
        ) : (
          <span className="text-xs text-on-surface-variant">aucune séance</span>
        )}
      </span>
      <BarreTaux taux={avecSeances ? taux : null} className="col-start-2 col-span-2" />
    </li>
  );
};

/**
 * Onglet Comparaisons de la page Rapports.
 *
 * Il suit l'année et la filière des filtres du haut : chaque bloc avait ses
 * propres listes et son bouton « Charger », qui pouvaient contredire les
 * filtres affichés juste au-dessus. Les trois blocs calculent le taux comme le
 * reste de la page : présences valides ÷ présences attendues, séances terminées.
 */
export default function ComparaisonsRapport({ anneeId, anneeLibelle, filiereId }) {
  const classementQuery = useQuery({
    queryKey: ['rapport-filiere-stats', anneeId],
    queryFn: ({ signal }) => rapportFiliereStats({ annee_id: anneeId }, signal),
    enabled: Boolean(anneeId),
  });
  const semestresQuery = useQuery({
    queryKey: ['rapport-comparaison-semestres', anneeId, filiereId],
    queryFn: ({ signal }) => rapportComparaisonSemestres({ annee_id: anneeId, filiere_id: filiereId }, signal),
    enabled: Boolean(anneeId && filiereId),
  });
  const anneesQuery = useQuery({
    queryKey: ['rapport-annee-stats'],
    queryFn: ({ signal }) => rapportAnneeStats(signal),
  });

  const classement = { chargement: classementQuery.isLoading, donnees: classementQuery.data?.data ?? null, erreur: classementQuery.isError };
  const semestres = { chargement: semestresQuery.isLoading, donnees: semestresQuery.data?.data ?? null, erreur: semestresQuery.isError };
  const annees = { chargement: anneesQuery.isLoading, donnees: anneesQuery.data?.data ?? null, erreur: anneesQuery.isError };

  // Les filières sans séance terminée ferment la marche, sans rang.
  const filieres = Array.isArray(classement.donnees)
    ? [...classement.donnees].sort((a, b) => (b.presences_attendues > 0) - (a.presences_attendues > 0) || b.taux - a.taux)
    : [];
  const lignesSemestres = Array.isArray(semestres.donnees?.semestres) ? semestres.donnees.semestres : [];
  const lignesAnnees = Array.isArray(annees.donnees) ? annees.donnees.filter((a) => a.total_evenements > 0) : [];

  return (
    <div className="space-y-4">
      <p className="text-xs text-on-surface-variant">
        Sur toute l'année choisie : la période, le semestre, l'UE et l'EC ne s'appliquent pas ici.
      </p>

      <div className="grid grid-cols-1 lg:grid-cols-[1.2fr_1fr] gap-4 items-start">
        <section aria-labelledby="titre-classement" className={CARTE}>
          <h2 id="titre-classement" className="text-sm font-bold font-headline text-primary">Classement des filières</h2>
          <p className="text-[11px] text-on-surface-variant mt-0.5 mb-4">
            {anneeLibelle ? `Année ${anneeLibelle}` : 'Choisissez une année'} · du meilleur taux au plus faible
          </p>

          {!anneeId ? (
            <Vide>Choisissez une année dans les filtres pour classer ses filières.</Vide>
          ) : classement.chargement ? (
            <Chargement />
          ) : classement.erreur ? (
            <Vide>Le classement n'a pas pu être chargé. Réessayez avec « Actualiser ».</Vide>
          ) : filieres.length === 0 ? (
            <Vide>Aucune filière pour cette année.</Vide>
          ) : (
            <ol className="space-y-3">
              {filieres.map((f, i) => (
                <LigneTaux
                  key={f.id}
                  rang={f.presences_attendues > 0 ? i + 1 : '–'}
                  libelle={f.code}
                  detail={f.intitule}
                  taux={f.taux}
                  presents={f.total_presences}
                  attendus={f.presences_attendues}
                  surlignee={String(f.id) === String(filiereId)}
                  puce={String(f.id) === String(filiereId) ? 'choisie' : null}
                />
              ))}
            </ol>
          )}
        </section>

        <div className="space-y-4">
          <section aria-labelledby="titre-semestres" className={CARTE}>
            <h2 id="titre-semestres" className="text-sm font-bold font-headline text-primary">
              {semestres.donnees?.filiere?.code ? `Semestres de ${semestres.donnees.filiere.code}` : 'Semestres de la filière'}
            </h2>
            <p className="text-[11px] text-on-surface-variant mt-0.5 mb-4">Compare les semestres d'une même filière.</p>

            {!anneeId || !filiereId ? (
              <Vide>Choisissez une filière dans les filtres du haut pour comparer ses semestres.</Vide>
            ) : semestres.chargement ? (
              <Chargement />
            ) : semestres.erreur ? (
              <Vide>La comparaison n'a pas pu être chargée.</Vide>
            ) : lignesSemestres.length === 0 ? (
              <Vide>Aucun semestre pour cette filière.</Vide>
            ) : (
              <ul className="space-y-3">
                {lignesSemestres.map((s) => (
                  <LigneTaux
                    key={s.semestre}
                    rang=""
                    libelle={s.label}
                    detail={`${s.total_evenements} séance${s.total_evenements > 1 ? 's' : ''}`}
                    taux={s.taux}
                    presents={s.total_presences}
                    attendus={s.presences_attendues}
                  />
                ))}
              </ul>
            )}
          </section>

          <section aria-labelledby="titre-annees" className={CARTE}>
            <h2 id="titre-annees" className="text-sm font-bold font-headline text-primary">Années académiques</h2>
            <p className="text-[11px] text-on-surface-variant mt-0.5 mb-4">Toutes les années de l'entité.</p>

            {annees.chargement ? (
              <Chargement />
            ) : annees.erreur ? (
              <Vide>Les années n'ont pas pu être chargées.</Vide>
            ) : lignesAnnees.length === 0 ? (
              <Vide>Aucune année ne contient encore de séance terminée.</Vide>
            ) : (
              <ul className="space-y-3">
                {lignesAnnees.map((a) => (
                  <LigneTaux
                    key={a.id}
                    rang=""
                    libelle={a.libelle}
                    detail={`${a.total_evenements} séance${a.total_evenements > 1 ? 's' : ''}`}
                    puce={a.active ? 'en cours' : null}
                    taux={a.taux}
                    presents={a.total_presences}
                    attendus={a.presences_attendues}
                  />
                ))}
              </ul>
            )}
          </section>
        </div>
      </div>
    </div>
  );
}
