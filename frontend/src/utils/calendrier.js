import { formatDateLongue } from './annees';

/** « le 10 janvier 2026 », ou « du 6 octobre 2025 au 21 février 2026 ». */
export const datesLisibles = (debut, fin) => (!fin || fin === debut
  ? `le ${formatDateLongue(debut)}`
  : `du ${formatDateLongue(debut)} au ${formatDateLongue(fin)}`);

/** Question posée avant de déclarer une fermeture qui retire des séances déjà planifiées. */
export const questionRetrait = (n, libelle) => {
  const s = n > 1 ? 's' : '';
  return `${n} séance${s} à venir, jamais ouverte${s} et sans présence, ser${n > 1 ? 'ont' : 'a'} retirée${s}. Déclarer « ${libelle} » ?`;
};
