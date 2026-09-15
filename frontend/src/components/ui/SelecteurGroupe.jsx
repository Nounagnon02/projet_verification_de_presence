import { useEffect, useMemo, useState } from 'react';
import api from '../../api/axios';

// Seuls les TD et les TP se font par groupe : un CM ou une évaluation réunit
// toute la promotion.
const TYPES_A_GROUPES = ['td', 'tp'];

/**
 * Groupe de TD ou de TP visé par une séance, parmi ceux des filières qui
 * suivent le cours. Sans groupe, la séance attend toute la promotion.
 *
 * Absent pour un CM ou une évaluation ; un groupe qui ne vaut plus pour le
 * cours ou le type choisi est effacé, pour ne jamais envoyer un groupe caché.
 */
export default function SelecteurGroupe({ id, ecId, type, value, onChange, className = '', labelClassName = '', wrapperClassName = '' }) {
  const actif = Boolean(ecId) && TYPES_A_GROUPES.includes(type);
  const cle = actif ? `${ecId}|${type}` : '';
  const [groupes, setGroupes] = useState({ cle: '', liste: [] });

  useEffect(() => {
    if (!cle) return undefined;
    let annule = false;
    const [ec_id, typeSeance] = cle.split('|');
    api.get('/admin/groupes', { params: { ec_id, type: typeSeance } })
      .then(({ data }) => { if (!annule) setGroupes({ cle, liste: data?.data ?? [] }); })
      .catch(() => { if (!annule) setGroupes({ cle, liste: [] }); });
    return () => { annule = true; };
  }, [cle]);

  const charge = groupes.cle === cle;
  const liste = useMemo(() => (charge ? groupes.liste : []), [charge, groupes.liste]);

  useEffect(() => {
    if (!value) return;
    if (!actif || (charge && !liste.some((g) => String(g.id) === String(value)))) onChange('');
  }, [actif, charge, liste, value, onChange]);

  if (!actif) return null;

  return (
    <div className={wrapperClassName}>
      <label htmlFor={id} className={labelClassName}>Groupe</label>
      <select id={id} value={value ?? ''} onChange={(e) => onChange(e.target.value)} className={className}>
        <option value="">Toute la promotion</option>
        {liste.map((g) => (
          <option key={g.id} value={g.id}>
            {g.libelle}{g.filiere?.code ? ` — ${g.filiere.code}` : ''} ({g.etudiants_count} étudiant{g.etudiants_count > 1 ? 's' : ''})
          </option>
        ))}
      </select>
      {charge && liste.length === 0 && (
        <p className="text-xs text-on-surface-variant pt-1">
          Aucun groupe de {type.toUpperCase()} pour ce cours : la séance réunit toute la promotion.
          Les groupes se créent dans Étudiants.
        </p>
      )}
    </div>
  );
}
