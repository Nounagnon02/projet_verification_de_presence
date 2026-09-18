import { DEBUT_JOURNEE, FIN_JOURNEE, PAS_MINUTES, enHeure, enMinutes } from '../../utils/heures';

/**
 * Choix d'une heure parmi celles qui sont permises, au pas de 15 minutes.
 *
 * Un champ HTML de type « time » obligeait à saisir l'heure au clavier et
 * laissait passer n'importe quelle valeur. Les bornes servent ici à ne proposer
 * que des heures valides : pour une fin, uniquement après le début et dans la
 * limite de durée autorisée. Les heures exclues ne sont pas dans la liste, et
 * non seulement grisées.
 *
 * C'est un <select> natif : react-datepicker et date-fns pesaient 178 Ko
 * (46 Ko compressés) pour choisir une heure, sur des réseaux mobiles où la page
 * de connexion elle-même est déjà lourde. Le sélecteur natif est aussi celui du
 * téléphone, se lit au lecteur d'écran et se pilote au clavier sans rien coder.
 *
 * La valeur échangée reste une chaîne « HH:MM ».
 *
 * @param {string}  apres   borne basse EXCLUE (la fin doit suivre le début)
 * @param {string}  jusqua  borne haute INCLUSE
 * @param {string}  id      à relier à un <label htmlFor> du parent : c'est alors lui qui nomme le champ
 */
export default function SelecteurHeure({
  value,
  onChange,
  apres = null,
  jusqua = null,
  placeholder = 'Choisir…',
  className = '',
  required = false,
  id,
  'aria-label': ariaLabel,
}) {
  // Sans id, personne ne peut relier un <label> au champ : on lui donne un nom.
  // Avec un id, un aria-label prendrait le pas sur le libellé visible du parent.
  const nomAccessible = ariaLabel || (id ? undefined : 'Choisir une heure');
  const bas = enMinutes(apres);
  const haut = enMinutes(jusqua);
  const courante = enMinutes(value);

  const minutes = [];
  for (let m = DEBUT_JOURNEE; m <= FIN_JOURNEE; m += PAS_MINUTES) {
    if ((bas === null || m > bas) && (haut === null || m <= haut)) minutes.push(m);
  }

  // Une heure déjà enregistrée hors de la grille (06:26 issue d'un import)
  // reste affichée et sélectionnée : l'effacer en silence à l'ouverture de la
  // modale modifierait la séance sans que l'administrateur l'ait décidé.
  if (courante !== null && !minutes.includes(courante)) {
    minutes.push(courante);
    minutes.sort((a, b) => a - b);
  }

  return (
    <select
      id={id}
      aria-label={nomAccessible}
      required={required}
      className={className}
      value={courante === null ? '' : enHeure(courante)}
      onChange={(e) => onChange(e.target.value)}
    >
      <option value="">{placeholder}</option>
      {minutes.map((m) => (
        <option key={m} value={enHeure(m)}>{enHeure(m)}</option>
      ))}
    </select>
  );
}
