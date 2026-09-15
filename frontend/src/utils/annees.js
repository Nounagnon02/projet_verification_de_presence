/**
 * Années académiques : dates, statut, année suivante.
 *
 * Le serveur envoie des dates AAAA-MM-JJ. `new Date('2025-10-01')` les lit à
 * minuit UTC : un navigateur à l'ouest de Greenwich affichait la veille. On
 * les lit ici comme des jours locaux.
 */

export function dateLocale(ymd) {
  if (!ymd) return null;
  const [a, m, j] = String(ymd).slice(0, 10).split('-').map(Number);
  if (!a || !m || !j) return null;
  return new Date(a, m - 1, j);
}

export function formatDateLongue(ymd) {
  const d = dateLocale(ymd);
  return d ? d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) : '—';
}

/** Jours d'aujourd'hui à cette date ; négatif si elle est passée. */
export function joursAvant(ymd, aujourdhui = new Date()) {
  const d = dateLocale(ymd);
  if (!d) return null;
  const jour = new Date(aujourdhui.getFullYear(), aujourdhui.getMonth(), aujourdhui.getDate());
  return Math.round((d - jour) / 86_400_000);
}

export const LIBELLES_STATUT = {
  terminee: 'Terminée',
  en_cours: 'En cours',
  a_venir: 'À venir',
};

/** Années qui commencent après `annee`, la plus proche d'abord. */
export function anneesApres(annees, annee) {
  if (!annee) return [];
  return (annees || [])
    .filter((a) => (a.date_debut || '') > (annee.date_debut || ''))
    .sort((a, b) => (a.date_debut || '').localeCompare(b.date_debut || ''));
}

/**
 * Ce qu'on propose pour créer l'année qui suit la plus récente : libellé et
 * dates décalés d'un an. 2025-2026 (1er oct. → 30 sept.) donne 2026-2027.
 */
export function proposerAnneeSuivante(annees) {
  const derniere = [...(annees || [])].sort((a, b) => (b.date_debut || '').localeCompare(a.date_debut || ''))[0];

  if (!derniere?.date_debut || !derniere?.date_fin) {
    const an = new Date().getFullYear();
    return { libelle: `${an}-${an + 1}`, date_debut: `${an}-10-01`, date_fin: `${an + 1}-09-30` };
  }

  const decaler = (ymd) => {
    const an = Number(ymd.slice(0, 4)) + 1;
    const moisJour = ymd.slice(5, 10) === '02-29' ? '02-28' : ymd.slice(5, 10);
    return `${an}-${moisJour}`;
  };
  const debut = Number(derniere.date_debut.slice(0, 4)) + 1;

  return { libelle: `${debut}-${debut + 1}`, date_debut: decaler(derniere.date_debut), date_fin: decaler(derniere.date_fin) };
}

export function pluriel(n, singulier, forme = `${singulier}s`) {
  return `${n} ${n > 1 ? forme : singulier}`;
}

/** « 83 étudiants · 80 UE · 4 créneaux d'emploi du temps · 132 séances ». */
export function contenuAnnee(annee) {
  const parties = [
    [annee.etudiants_count, 'étudiant'],
    [annee.ues_count, 'UE', 'UE'],
    [annee.emplois_du_temps_count, "créneau d'emploi du temps", "créneaux d'emploi du temps"],
    [annee.evenements_count, 'séance'],
  ].filter(([n]) => (n ?? 0) > 0);

  return parties.map(([n, s, p]) => pluriel(n, s, p)).join(' · ');
}
