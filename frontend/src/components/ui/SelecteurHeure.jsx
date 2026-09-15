import DatePicker from 'react-datepicker';
import 'react-datepicker/dist/react-datepicker.css';
import './SelecteurHeure.css';
import { DEBUT_JOURNEE, FIN_JOURNEE, PAS_MINUTES, enHeure, enMinutes } from '../../utils/heures';

/**
 * Choix d'une heure dans un panneau déroulant, au pas de 15 minutes.
 *
 * Remplace <input type="time">, qui obligeait à saisir l'heure au clavier.
 * Les bornes servent à ne proposer que des heures valides : pour une fin,
 * uniquement après le début et dans la limite de durée autorisée. Les heures
 * exclues sont masquées, pas seulement grisées.
 *
 * La valeur échangée reste une chaîne « HH:MM » : react-datepicker manipule des
 * Date, la conversion est confinée ici pour que les formulaires n'en sachent rien.
 *
 * @param {string}  apres   borne basse EXCLUE (la fin doit suivre le début)
 * @param {string}  jusqua  borne haute INCLUSE
 */

/** « 08:15 » -> Date du jour à 08:15. Seule l'heure compte. */
function versDate(heure) {
  const minutes = enMinutes(heure);
  if (minutes === null) return null;
  const d = new Date();
  d.setHours(Math.floor(minutes / 60), minutes % 60, 0, 0);
  return d;
}

/** Date -> « 08:15 ». */
function versHeure(date) {
  return date ? enHeure(date.getHours() * 60 + date.getMinutes()) : '';
}

/**
 * Touches qui écriraient dans le champ. Il reste focalisable et validable
 * (required fonctionne, contrairement à un champ readOnly que le navigateur
 * exclut de la validation), mais on n'y tape plus : on choisit.
 */
function bloquerSaisie(e) {
  if (e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete') {
    e.preventDefault();
  }
}

export default function SelecteurHeure({
  value,
  onChange,
  apres = null,
  jusqua = null,
  placeholder = 'Choisir…',
  className = '',
  required = false,
  id,
}) {
  const bas = enMinutes(apres);
  const haut = enMinutes(jusqua);

  const permise = (date) => {
    const m = date.getHours() * 60 + date.getMinutes();
    return m >= DEBUT_JOURNEE
      && m <= FIN_JOURNEE
      && (bas === null || m > bas)
      && (haut === null || m <= haut);
  };

  return (
    <DatePicker
      selected={versDate(value)}
      onChange={(date) => onChange(versHeure(date))}
      showTimeSelect
      showTimeSelectOnly
      timeIntervals={PAS_MINUTES}
      timeCaption="Heure"
      dateFormat="HH:mm"
      timeFormat="HH:mm"
      filterTime={permise}
      placeholderText={placeholder}
      required={required}
      id={id}
      className={className}
      wrapperClassName="w-full"
      popperClassName="selecteur-heure__panneau"
      popperPlacement="bottom-start"
      showPopperArrow={false}
      // Rendu hors de la modale : son défilement rognait le panneau.
      portalId="selecteur-heure-portail"
      autoComplete="off"
      onKeyDown={bloquerSaisie}
      // inputMode « none » : sur téléphone, pas de clavier virtuel inutile.
      customInput={<input inputMode="none" onPaste={(e) => e.preventDefault()} />}
    />
  );
}
