/**
 * Taux de présence semaine par semaine, sur une échelle fixe de 0 à 100 %.
 *
 * Le graphique à barres générique s'étalonnait sur la plus haute valeur : un
 * 33 % y montait jusqu'en haut, et une semaine sans séance y dessinait une
 * petite barre, lue comme 0 %. Ici l'échelle est celle d'un taux, et une
 * semaine sans séance terminée reste un emplacement vide en pointillés.
 */

const LARGEUR = 600;
const HAUTEUR = 230;
const GAUCHE = 46;
const DROITE = 10;
const HAUT = 24;
const BAS = 40;
const GRADUATIONS = [0, 25, 50, 75, 100];

const jourMois = (valeur) => `${valeur.slice(8, 10)}/${valeur.slice(5, 7)}`;
const sansTaux = (semaine) => semaine.taux === null || semaine.taux === undefined;

export default function TauxHebdomadaireChart({ semaines }) {
  const nombre = semaines.length;
  const zone = HAUTEUR - HAUT - BAS;
  const y = (taux) => HAUT + zone * (1 - taux / 100);
  const pas = (LARGEUR - GAUCHE - DROITE) / nombre;
  const largeurBarre = Math.min(42, pas * 0.6);
  // Au-delà d'une douzaine de semaines, les étiquettes se chevaucheraient.
  const pasEtiquette = Math.ceil(nombre / 12);
  const detaille = nombre <= 12;

  const avecSeance = semaines.filter((s) => !sansTaux(s));
  const resume = avecSeance.length === 0
    ? 'Aucune séance terminée sur la période'
    : `Taux de présence par semaine : ${avecSeance.map((s) => `semaine du ${jourMois(s.semaine)}, ${s.taux} %`).join(' ; ')}.`
      + (nombre > avecSeance.length ? ` ${nombre - avecSeance.length} semaine(s) sans séance terminée.` : '');

  return (
    <svg viewBox={`0 0 ${LARGEUR} ${HAUTEUR}`} className="w-full h-auto" role="img" aria-label={resume}>
      {GRADUATIONS.map((taux) => (
        <g key={taux}>
          <line
            x1={GAUCHE} x2={LARGEUR - DROITE} y1={y(taux)} y2={y(taux)}
            className={taux === 0 ? 'stroke-outline' : 'stroke-outline-variant'}
            strokeOpacity={taux === 0 ? 0.7 : 0.35}
          />
          <text x={GAUCHE - 8} y={y(taux) + 4} textAnchor="end" className="fill-on-surface-variant text-[11px]">
            {taux} %
          </text>
        </g>
      ))}

      {semaines.map((semaine, i) => {
        const centre = GAUCHE + pas * i + pas / 2;
        const x = centre - largeurBarre / 2;
        const vide = sansTaux(semaine);

        return (
          <g key={semaine.semaine}>
            <title>
              {vide
                ? `Semaine du ${jourMois(semaine.semaine)} : aucune séance terminée`
                : `Semaine du ${jourMois(semaine.semaine)} : ${semaine.taux} % (${semaine.presents} présents sur ${semaine.attendus} attendus, ${semaine.seances} séance${semaine.seances > 1 ? 's' : ''})`}
            </title>

            {vide ? (
              <>
                <rect
                  x={x} y={y(0) - 6} width={largeurBarre} height={6} rx={2}
                  fill="none" className="stroke-outline" strokeOpacity={0.6} strokeDasharray="3 3"
                />
                {nombre <= 8 && (
                  <text x={centre} y={y(0) - 12} textAnchor="middle" className="fill-on-surface-variant text-[10px]">
                    sans séance
                  </text>
                )}
              </>
            ) : (
              <rect
                x={x} y={y(semaine.taux)} width={largeurBarre}
                height={Math.max(y(0) - y(semaine.taux), 2)} rx={3}
                className="fill-primary"
              />
            )}

            {detaille && !vide && (
              <text x={centre} y={y(semaine.taux) - 7} textAnchor="middle" className="fill-on-surface text-[12px] font-semibold">
                {semaine.taux} %
              </text>
            )}

            {i % pasEtiquette === 0 && (
              <text x={centre} y={HAUTEUR - BAS + 18} textAnchor="middle" className="fill-on-surface-variant text-[11px]">
                {jourMois(semaine.semaine)}
              </text>
            )}
          </g>
        );
      })}

      <text x={GAUCHE} y={HAUTEUR - 4} className="fill-on-surface-variant text-[10px]">
        Semaines du lundi au dimanche
      </text>
    </svg>
  );
}
