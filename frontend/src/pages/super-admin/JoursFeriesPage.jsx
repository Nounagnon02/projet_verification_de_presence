import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FiAlertTriangle, FiLoader, FiTrash2 } from 'react-icons/fi';
import { listerAnneesUniversitaires } from '../../api/resources/anneesUniversitaires';
import { listerJoursFeries, declarerJourFerie, supprimerJourFerie } from '../../api/resources/joursFeries';
import { useToastCtx } from '../../context/ToastContext';
import { datesLisibles, questionRetrait } from '../../utils/calendrier';

const FERIE_VIDE = { libelle: '', date_debut: '', date_fin: '' };
const CHAMP = 'w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50';
const LIBELLE = 'text-xs font-semibold text-on-surface-variant';

const messageErreur = (err, defaut) => {
  const d = err.response?.data;
  return (d?.errors ? Object.values(d.errors).flat().join(' ') : null) || d?.message || defaut;
};

/**
 * Jours fériés de l'université : aucune séance n'est générée ces jours-là,
 * dans aucun établissement. Chaque établissement déclare lui-même ses
 * semestres, vacances et examens.
 */
export default function JoursFeriesPage() {
  const queryClient = useQueryClient();
  const anneesQuery = useQuery({
    queryKey: ['annees-universitaires'],
    queryFn: ({ signal }) => listerAnneesUniversitaires(signal),
  });
  const { addToast } = useToastCtx() ?? {};
  const [anneeId, setAnneeId] = useState('');
  const [ferie, setFerie] = useState(FERIE_VIDE);
  const [enCours, setEnCours] = useState('');

  const liste = Array.isArray(anneesQuery.data?.data) ? anneesQuery.data.data : [];
  const choisie = anneeId || String(liste.find((a) => a.active)?.id ?? '');
  const annee = liste.find((a) => String(a.id) === choisie) ?? null;

  const feriesQuery = useQuery({
    queryKey: ['jours-feries', choisie],
    queryFn: ({ signal }) => listerJoursFeries(choisie, signal),
    enabled: Boolean(choisie),
  });

  const charge = !feriesQuery.isLoading;
  const feries = feriesQuery.data?.data ?? [];
  const erreurFeries = feriesQuery.isError ? messageErreur(feriesQuery.error, "Les jours fériés n'ont pas pu être chargés.") : '';
  const occupe = Boolean(enCours);

  const rafraichirFeries = () => queryClient.invalidateQueries({ queryKey: ['jours-feries'] });

  const declarer = async (e) => {
    e.preventDefault();
    const corps = { ...ferie, annee_id: annee.id, date_fin: ferie.date_fin || ferie.date_debut };
    setEnCours('declarer');
    try {
      const apercu = await declarerJourFerie({ ...corps, apercu: true });
      const n = Number(apercu?.data?.seances_a_retirer ?? 0);
      if (n > 0 && !window.confirm(questionRetrait(n, corps.libelle))) return;

      const data = await declarerJourFerie(corps);
      addToast?.(data?.message || 'Jour férié déclaré.', 'success');
      setFerie(FERIE_VIDE);
      rafraichirFeries();
    } catch (err) {
      addToast?.(messageErreur(err, "Le jour férié n'a pas été déclaré."), 'error');
    } finally {
      setEnCours('');
    }
  };

  const retirer = async (f) => {
    if (!window.confirm(`Retirer « ${f.libelle} » ? La génération planifiée recréera les séances de ce jour.`)) return;
    setEnCours(`retirer-${f.id}`);
    try {
      const data = await supprimerJourFerie(f.id);
      addToast?.(data?.message || 'Jour férié retiré.', 'success');
      rafraichirFeries();
    } catch (err) {
      addToast?.(messageErreur(err, "Le jour férié n'a pas été retiré."), 'error');
    } finally {
      setEnCours('');
    }
  };

  return (
    <div className="space-y-6 max-w-4xl">
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Jours fériés</h1>
          <p className="text-sm text-on-surface-variant max-w-2xl mt-1">
            Aucune séance n'est générée ces jours-là, dans aucune faculté. Déclarer un jour férié retire les
            séances à venir qu'il couvre, si elles n'ont jamais été ouvertes et n'ont aucune présence.
          </p>
        </div>
        <div className="space-y-1 w-full md:w-56">
          <label htmlFor="feries-annee" className={LIBELLE}>Année académique</label>
          <select id="feries-annee" value={choisie} onChange={(e) => setAnneeId(e.target.value)} className={CHAMP}>
            {liste.map((a) => <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (en cours)' : ''}</option>)}
          </select>
        </div>
      </div>

      {erreurFeries && (
        <p role="alert" className="flex items-start gap-2 p-3 bg-error/10 text-error rounded-lg text-sm">
          <FiAlertTriangle className="mt-0.5 shrink-0" aria-hidden="true" /> {erreurFeries}
        </p>
      )}

      <div className="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/10 overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-outline-variant/10 text-on-surface-variant text-xs uppercase tracking-wider">
              <th className="text-left p-3 font-semibold">Libellé</th>
              <th className="text-left p-3 font-semibold">Dates</th>
              <th className="p-3"><span className="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            {!charge && choisie ? (
              <tr><td colSpan={3} className="p-6 text-center"><FiLoader className="animate-spin mx-auto text-primary" aria-label="Chargement" /></td></tr>
            ) : feries.length === 0 ? (
              <tr><td colSpan={3} className="p-6 text-center text-on-surface-variant">Aucun jour férié déclaré{annee ? ` pour ${annee.libelle}` : ''}.</td></tr>
            ) : feries.map((f) => (
              <tr key={f.id} className="border-b border-outline-variant/5 last:border-0">
                <td className="p-3 font-medium">{f.libelle}</td>
                <td className="p-3 text-on-surface-variant whitespace-nowrap">{datesLisibles(f.date_debut, f.date_fin)}</td>
                <td className="p-3 text-right">
                  <button type="button" onClick={() => retirer(f)} disabled={occupe} aria-label={`Retirer « ${f.libelle} »`}
                    className="p-2 hover:bg-error/10 rounded-lg transition-colors disabled:opacity-40">
                    <FiTrash2 className="text-error" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {annee && (
        <form onSubmit={declarer} className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 space-y-3">
          <h2 className="font-semibold text-on-surface">Déclarer un jour férié</h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div className="space-y-1">
              <label htmlFor="ferie-libelle" className={LIBELLE}>Libellé</label>
              <input id="ferie-libelle" required maxLength={120} placeholder="Fête du Vodoun" value={ferie.libelle}
                onChange={(e) => setFerie((f) => ({ ...f, libelle: e.target.value }))} className={CHAMP} />
            </div>
            <div className="space-y-1">
              <label htmlFor="ferie-debut" className={LIBELLE}>Le</label>
              <input id="ferie-debut" type="date" required min={annee.date_debut} max={annee.date_fin} value={ferie.date_debut}
                onChange={(e) => setFerie((f) => ({ ...f, date_debut: e.target.value }))} className={CHAMP} />
            </div>
            <div className="space-y-1">
              <label htmlFor="ferie-fin" className={LIBELLE}>Jusqu'au (facultatif)</label>
              <input id="ferie-fin" type="date" min={ferie.date_debut || annee.date_debut} max={annee.date_fin} value={ferie.date_fin}
                onChange={(e) => setFerie((f) => ({ ...f, date_fin: e.target.value }))} className={CHAMP} />
            </div>
          </div>
          <div className="flex justify-end">
            <button type="submit" disabled={occupe}
              className="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50">
              {enCours === 'declarer' && <FiLoader className="animate-spin" aria-hidden="true" />} Déclarer
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
