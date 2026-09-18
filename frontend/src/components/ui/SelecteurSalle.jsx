/**
 * Choix de la salle d'une séance, parmi les salles configurées seulement.
 *
 * Un nom de salle saisi à la main ne permet ni le contrôle GPS/Wi-Fi au scan ni
 * la détection de double réservation : les deux reposent sur la salle
 * configurée. La saisie libre a donc disparu du formulaire.
 *
 * Chaque salle indique ce qu'elle vérifie réellement : une salle déclarée sans
 * coordonnées ni réseau ne protège que par le QR code, et le formulaire le dit
 * plutôt que de laisser croire le contraire.
 *
 * @param {Array}  salles     salles configurées ({ id, nom, verifie_gps, verifie_wifi })
 * @param {string} nomActuel  nom saisi d'une séance jamais rattachée (import ancien) :
 *                            affiché pour information, jamais effacé en silence
 * @param {string} id         à relier à un <label htmlFor> du parent : c'est alors lui qui nomme le champ
 */

function controles(salle) {
  if (!salle) return [];
  return [salle.verifie_gps && 'GPS', salle.verifie_wifi && 'Wi-Fi'].filter(Boolean);
}

export default function SelecteurSalle({
  salles = [],
  value,
  onChange,
  nomActuel = '',
  className = '',
  id,
  'aria-label': ariaLabel,
}) {
  // Sans id, personne ne peut relier un <label> au champ : on lui donne un nom.
  // Avec un id, un aria-label prendrait le pas sur le libellé visible du parent.
  const nomAccessible = ariaLabel || (id ? undefined : 'Salle');
  const choisie = salles.find((s) => String(s.id) === String(value ?? ''));
  const protegees = salles.filter((s) => controles(s).length);
  const qrSeul = salles.filter((s) => !controles(s).length);

  let indication;
  if (choisie) {
    const c = controles(choisie);
    indication = c.length
      ? `Au scan : contrôle ${c.join(' et ')} en plus du QR code.`
      : "Cette salle n'a ni GPS ni Wi-Fi configuré : le scan ne vérifie que le QR code. À compléter dans Paramètres > Salles.";
  } else if (nomActuel) {
    indication = `Salle actuelle : « ${nomActuel} », non configurée. Choisissez une salle pour activer les contrôles au scan.`;
  } else {
    indication = 'Sans salle, le scan ne vérifie que le QR code.';
  }

  return (
    <div>
      <select
        id={id}
        aria-label={nomAccessible}
        value={choisie ? String(choisie.id) : ''}
        onChange={(e) => onChange(e.target.value)}
        className={className}
      >
        <option value="">Aucune — QR seul</option>
        {protegees.length > 0 && (
          <optgroup label="Avec contrôle au scan">
            {protegees.map((s) => (
              <option key={s.id} value={s.id}>{s.nom} · {controles(s).join(' + ')}</option>
            ))}
          </optgroup>
        )}
        {qrSeul.length > 0 && (
          <optgroup label="QR seul — GPS et Wi-Fi à configurer">
            {qrSeul.map((s) => <option key={s.id} value={s.id}>{s.nom}</option>)}
          </optgroup>
        )}
      </select>
      <p className="text-xs pt-1 text-on-surface-variant">{indication}</p>
    </div>
  );
}
