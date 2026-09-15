import { useCallback, useEffect, useMemo, useState } from 'react';
import { FiAlertTriangle, FiLoader, FiTrash2 } from 'react-icons/fi';
import api from '../../api/axios';
import Modal from '../ui/Modal';
import SelecteurHeure from '../ui/SelecteurHeure';
import SelecteurSalle from '../ui/SelecteurSalle';
import SelecteurGroupe from '../ui/SelecteurGroupe';
import { TYPES_SEANCE } from '../../utils/typesSeance';
import { finApresNouveauDebut } from '../../utils/heures';
import { JOURS } from '../../utils/emploiDuTemps';

const VIDE = {
  ec_id: '', jour_semaine: '1', heure_debut: '08:00', heure_fin: '10:00', type_cours: 'cm',
  groupe_id: '', salle_id: '', enseignant: '', valide_du: '', valide_au: '',
};

const CHAMP = 'w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20';
const LIBELLE = 'text-xs font-semibold text-on-surface-variant';

const messageErreur = (err, defaut) => {
  const d = err.response?.data;
  return (d?.errors ? Object.values(d.errors).flat().join(' ') : null) || d?.message || defaut;
};

const depuisCreneau = (c) => ({
  ec_id: String(c.ec_id),
  jour_semaine: String(c.jour_semaine),
  heure_debut: c.heure_debut,
  heure_fin: c.heure_fin,
  type_cours: c.type_cours || 'cm',
  groupe_id: c.groupe_id ? String(c.groupe_id) : '',
  salle_id: c.salle_id ? String(c.salle_id) : '',
  enseignant: c.enseignant || '',
  valide_du: c.valide_du || '',
  valide_au: c.valide_au || '',
});

/**
 * Ajout ou modification d'un créneau de l'emploi du temps. Le serveur applique
 * les règles des imports : un conflit de salle, de promotion ou d'enseignant
 * revient ici en clair.
 */
export default function FormulaireCreneau({ ouvert, creneau = null, anneeId = '', filiereId = '', semestre = '', onFermer, onEnregistre }) {
  const [form, setForm] = useState(VIDE);
  const [ecs, setEcs] = useState([]);
  const [salles, setSalles] = useState([]);
  const [erreur, setErreur] = useState('');
  const [enCours, setEnCours] = useState(false);

  // À chaque ouverture : le créneau à modifier, ou un créneau vierge.
  const [ouvertAvant, setOuvertAvant] = useState(false);
  if (ouvert !== ouvertAvant) {
    setOuvertAvant(ouvert);
    if (ouvert) {
      setErreur('');
      setForm(creneau ? depuisCreneau(creneau) : VIDE);
    }
  }

  useEffect(() => {
    if (!ouvert) return undefined;
    let annule = false;
    Promise.all([api.get('/admin/ecs'), api.get('/admin/salles/disponibles')])
      .then(([reponseEcs, reponseSalles]) => {
        if (annule) return;
        const listeEcs = reponseEcs.data?.data ?? reponseEcs.data;
        const listeSalles = reponseSalles.data?.data ?? reponseSalles.data;
        setEcs(Array.isArray(listeEcs) ? listeEcs : []);
        setSalles(Array.isArray(listeSalles) ? listeSalles : []);
      })
      .catch(() => { /* listes laissées vides : le serveur tranche */ });
    return () => { annule = true; };
  }, [ouvert]);

  // Les cours de l'année, que suit la filière choisie (cours communs compris),
  // du semestre choisi ; celui du créneau modifié, toujours.
  const cours = useMemo(() => ecs
    .filter((ec) => String(ec.id) === String(creneau?.ec_id ?? '') || (
      (!anneeId || String(ec.ue?.annee_id) === String(anneeId))
      && (!filiereId || String(ec.ue?.filiere_id) === String(filiereId)
        || (ec.ue?.filieres || []).some((f) => String(f.id) === String(filiereId)))
      && (!semestre || String(ec.ue?.semestre) === String(semestre))
    ))
    .sort((a, b) => String(a.code).localeCompare(String(b.code), 'fr')), [ecs, creneau, anneeId, filiereId, semestre]);

  const champ = (cle) => (e) => setForm((f) => ({ ...f, [cle]: e.target.value }));
  const choisirGroupe = useCallback((groupe_id) => setForm((f) => ({ ...f, groupe_id })), []);

  const enregistrer = async (e) => {
    e.preventDefault();
    setEnCours(true);
    setErreur('');
    const corps = {
      ...form,
      ec_id: Number(form.ec_id),
      jour_semaine: Number(form.jour_semaine),
      groupe_id: form.groupe_id || null,
      salle_id: form.salle_id || null,
      enseignant: form.enseignant.trim() || null,
      valide_du: form.valide_du || null,
      valide_au: form.valide_au || null,
    };
    try {
      const { data } = creneau
        ? await api.put(`/admin/emploi-du-temps/${creneau.id}`, corps)
        : await api.post('/admin/emploi-du-temps', corps);
      onEnregistre?.(data?.message || 'Créneau enregistré.');
    } catch (err) {
      setErreur(messageErreur(err, "Le créneau n'a pas été enregistré."));
    } finally {
      setEnCours(false);
    }
  };

  const supprimer = async () => {
    if (!creneau || !window.confirm('Supprimer ce créneau ? Ses séances à venir, jamais ouvertes et sans présence, sont retirées avec lui.')) return;
    setEnCours(true);
    try {
      const { data } = await api.delete(`/admin/emploi-du-temps/${creneau.id}`);
      onEnregistre?.(data?.message || 'Créneau supprimé.');
    } catch (err) {
      setErreur(messageErreur(err, "Le créneau n'a pas été supprimé."));
    } finally {
      setEnCours(false);
    }
  };

  return (
    <Modal isOpen={ouvert} onClose={onFermer} title={creneau ? 'Modifier le créneau' : 'Ajouter un créneau'} size="lg">
      <form onSubmit={enregistrer} className="space-y-4">
        {erreur && (
          <p role="alert" className="flex items-start gap-2 p-3 bg-error/10 text-error rounded-lg text-sm">
            <FiAlertTriangle className="mt-0.5 shrink-0" aria-hidden="true" /> <span>{erreur}</span>
          </p>
        )}

        <div className="space-y-1.5">
          <label htmlFor="creneau-ec" className={LIBELLE}>Cours *</label>
          <select id="creneau-ec" required value={form.ec_id} onChange={champ('ec_id')} className={CHAMP}>
            <option value="">Sélectionner un cours</option>
            {cours.map((ec) => (
              <option key={ec.id} value={ec.id}>
                {ec.code} — {ec.intitule}
                {(ec.ue?.filieres?.length ?? 0) > 1 ? ` (commun à ${ec.ue.filieres.map((f) => f.code).join(', ')})` : ''}
              </option>
            ))}
          </select>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="space-y-1.5">
            <label htmlFor="creneau-jour" className={LIBELLE}>Jour *</label>
            <select id="creneau-jour" value={form.jour_semaine} onChange={champ('jour_semaine')} className={CHAMP}>
              {JOURS.slice(1).map((jour, i) => <option key={jour} value={i + 1}>{jour}</option>)}
            </select>
          </div>
          <div className="space-y-1.5">
            <label htmlFor="creneau-debut" className={LIBELLE}>Début *</label>
            <SelecteurHeure id="creneau-debut" required value={form.heure_debut} className={CHAMP}
              // La fin suit le début en gardant la durée.
              onChange={(v) => setForm((f) => ({
                ...f,
                heure_debut: v,
                heure_fin: finApresNouveauDebut({ ancienDebut: f.heure_debut, ancienneFin: f.heure_fin, nouveauDebut: v, limiteMinutes: null }),
              }))} />
          </div>
          <div className="space-y-1.5">
            <label htmlFor="creneau-fin" className={LIBELLE}>Fin *</label>
            <SelecteurHeure id="creneau-fin" required value={form.heure_fin} apres={form.heure_debut || null} className={CHAMP}
              onChange={(v) => setForm((f) => ({ ...f, heure_fin: v }))} />
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div className="space-y-1.5">
            <label htmlFor="creneau-type" className={LIBELLE}>Type de séance</label>
            <select id="creneau-type" value={form.type_cours} onChange={champ('type_cours')} className={CHAMP}>
              {TYPES_SEANCE.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
            </select>
          </div>
          <SelecteurGroupe id="creneau-groupe" ecId={form.ec_id} type={form.type_cours} value={form.groupe_id}
            onChange={choisirGroupe} wrapperClassName="space-y-1.5" labelClassName={LIBELLE} className={CHAMP} />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div className="space-y-1.5">
            <label htmlFor="creneau-salle" className={LIBELLE}>Salle</label>
            <SelecteurSalle id="creneau-salle" salles={salles} value={form.salle_id}
              onChange={(id) => setForm((f) => ({ ...f, salle_id: id }))} className={CHAMP} />
          </div>
          <div className="space-y-1.5">
            <label htmlFor="creneau-enseignant" className={LIBELLE}>Enseignant</label>
            <input id="creneau-enseignant" maxLength={255} placeholder="HOUNDJI / AGBO" value={form.enseignant}
              onChange={champ('enseignant')} className={CHAMP} />
          </div>
        </div>

        <fieldset className="space-y-2">
          <legend className={LIBELLE}>Validité</legend>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label htmlFor="creneau-du" className={LIBELLE}>Valable du</label>
              <input id="creneau-du" type="date" value={form.valide_du} onChange={champ('valide_du')} className={CHAMP} />
            </div>
            <div className="space-y-1.5">
              <label htmlFor="creneau-au" className={LIBELLE}>au</label>
              <input id="creneau-au" type="date" min={form.valide_du || undefined} value={form.valide_au} onChange={champ('valide_au')} className={CHAMP} />
            </div>
          </div>
          <p className="text-xs text-on-surface-variant">
            Vide : sans limite. Une nouvelle version de l'emploi du temps commence le lendemain de la fin de la précédente.
          </p>
        </fieldset>

        <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
          {creneau ? (
            <button type="button" onClick={supprimer} disabled={enCours}
              className="flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-error hover:bg-error/10 rounded-xl disabled:opacity-50">
              <FiTrash2 aria-hidden="true" /> Supprimer
            </button>
          ) : <span />}
          <div className="flex gap-3">
            <button type="button" onClick={onFermer} disabled={enCours}
              className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl">
              Annuler
            </button>
            <button type="submit" disabled={enCours}
              className="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50">
              {enCours && <FiLoader className="animate-spin" aria-hidden="true" />} Enregistrer
            </button>
          </div>
        </div>
      </form>
    </Modal>
  );
}
