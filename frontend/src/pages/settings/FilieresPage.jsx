import { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { FiPlus, FiEdit2, FiTrash2, FiLoader, FiAlertTriangle } from 'react-icons/fi';
import Modal from '../../components/ui/Modal';
import api from '../../api/axios';
import useNiveaux from '../../hooks/useNiveaux';
import { useToastCtx } from '../../context/ToastContext';

const NOUVEAU = 'nouveau';
const CHAMP = 'w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all disabled:opacity-50';
const ETIQUETTE = 'block text-xs font-semibold text-on-surface-variant mb-1';

const liste = (reponse) => (Array.isArray(reponse?.data?.data) ? reponse.data.data : []);
const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

/** Ce qui empêche la suppression, toutes années confondues ; null si rien. */
const obstacles = (f) => {
  const raisons = [
    f.etudiants_total ? pluriel(f.etudiants_total, 'étudiant') : null,
    f.ues_total ? `${f.ues_total} UE` : null,
    f.evenements_total ? pluriel(f.evenements_total, 'séance') : null,
  ].filter(Boolean);

  return raisons.length ? raisons.join(', ') : null;
};

const ErreurChamp = ({ messages }) => (messages?.length
  ? <p className="text-xs text-error mt-1">{messages.join(' ')}</p>
  : null);

/**
 * Une filière dans la grille : ses effectifs de l'année, ses UE par semestre,
 * et les chemins vers ses UE et ses étudiants.
 */
function CaseFiliere({ filiere, annee, niveau, anneeId, onModifier, onSupprimer }) {
  const obstacle = obstacles(filiere);
  const parSemestre = Object.entries(annee?.ues_par_semestre ?? {});
  // Une UE dont le semestre n'appartient pas au niveau trahit une maquette
  // incohérente : on la montre au lieu de la noyer dans un total.
  const horsNiveau = niveau ? parSemestre.filter(([s]) => !niveau.semestres.includes(Number(s))) : [];
  const requete = `filiere=${filiere.id}${anneeId ? `&annee=${anneeId}` : ''}`;

  return (
    <div className="rounded-lg border border-outline-variant/20 bg-surface-container-low/40 p-3 space-y-1.5 mb-2 last:mb-0">
      <div className="flex items-start justify-between gap-2">
        <span className="font-mono text-xs font-bold text-primary break-all" title={filiere.intitule}>{filiere.code}</span>
        <div className="flex gap-0.5 shrink-0">
          <button type="button" onClick={onModifier} aria-label={`Modifier ${filiere.code}`} title="Modifier"
            className="p-1 rounded hover:bg-surface-container-high transition-colors">
            <FiEdit2 size={12} className="text-on-surface-variant" aria-hidden="true" />
          </button>
          <button type="button" onClick={onSupprimer} disabled={Boolean(obstacle)} aria-label={`Supprimer ${filiere.code}`}
            title={obstacle ? `Suppression impossible : ${obstacle}` : 'Supprimer'}
            className="p-1 rounded hover:bg-error/10 transition-colors disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent">
            <FiTrash2 size={12} className="text-error" aria-hidden="true" />
          </button>
        </div>
      </div>

      {annee ? (
        <>
          <p className="text-xs text-on-surface tabular-nums">{pluriel(annee.etudiants_count ?? 0, 'étudiant')} · {annee.ues_count ?? 0} UE</p>
          {parSemestre.length > 0 && (
            <p className="text-[11px] text-on-surface-variant tabular-nums">{parSemestre.map(([s, n]) => `S${s} : ${n}`).join(' · ')}</p>
          )}
          {horsNiveau.length > 0 && (
            <p className="text-[11px] text-error">UE hors niveau : {horsNiveau.map(([s]) => `S${s}`).join(', ')}</p>
          )}
        </>
      ) : (
        <p className="text-[11px] text-on-surface-variant italic">Aucune activité cette année</p>
      )}

      <div className="flex gap-3 text-[11px] font-semibold pt-0.5">
        <Link to={`/courses?${requete}`} className="text-primary hover:underline">UE →</Link>
        <Link to={`/students?${requete}`} className="text-primary hover:underline">Étudiants →</Link>
      </div>
    </div>
  );
}

/**
 * Filières : une grille programme × niveau.
 *
 * Une filière est un programme à un niveau (IM + L2 = IM-L2). Douze cartes
 * triées par intitulé mélangeaient programmes et niveaux, sans année, et
 * répétaient le niveau trois fois par carte ; rien ne menait aux UE ni aux
 * étudiants de la filière.
 */
export default function FilieresPage() {
  const { addToast } = useToastCtx() ?? {};
  const niveaux = useNiveaux();

  const [annees, setAnnees] = useState(null); // null : pas encore chargées
  const [anneeId, setAnneeId] = useState('');
  const [toutes, setToutes] = useState([]);
  const [delAnnee, setDelAnnee] = useState(() => new Map());
  const [programmes, setProgrammes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [erreurChargement, setErreurChargement] = useState(false);
  const [rechargement, setRechargement] = useState(0);

  const [formulaire, setFormulaire] = useState(null);
  const [erreurs, setErreurs] = useState({});
  const [enregistrement, setEnregistrement] = useState(false);
  const [aSupprimer, setASupprimer] = useState(null);
  const [suppression, setSuppression] = useState(false);
  // Programme en cours de renommage : { id, code, ancien, intitule, renommer_filieres }
  const [programmeEdite, setProgrammeEdite] = useState(null);
  const [renommage, setRenommage] = useState(false);

  // Années : l'écran s'ouvre sur l'année active.
  useEffect(() => {
    const controleur = new AbortController();

    api.get('/admin/annees-academiques', { signal: controleur.signal })
      .then((reponse) => {
        const lues = liste(reponse);
        setAnnees(lues);
        const active = lues.find((a) => a.active);
        if (active) setAnneeId(String(active.id));
      })
      .catch(() => { if (!controleur.signal.aborted) setAnnees([]); });

    return () => controleur.abort();
  }, []);

  // Toutes les filières (la grille), leurs effectifs de l'année choisie, et les
  // programmes. Une filière sans activité cette année reste dans sa case : la
  // proposer à nouveau à la création mènerait droit à un code en double.
  useEffect(() => {
    if (annees === null) return undefined;

    let annule = false;

    (async () => {
      setLoading(true);
      setErreurChargement(false);

      try {
        const [tout, annee, progs] = await Promise.all([
          api.get('/admin/filieres'),
          anneeId ? api.get('/admin/filieres', { params: { annee_id: anneeId } }) : Promise.resolve(null),
          api.get('/admin/programmes'),
        ]);
        if (annule) return;

        setToutes(liste(tout));
        setDelAnnee(new Map(liste(annee ?? tout).map((f) => [f.id, f])));
        setProgrammes(liste(progs));
      } catch {
        if (!annule) setErreurChargement(true);
      } finally {
        if (!annule) setLoading(false);
      }
    })();

    return () => { annule = true; };
  }, [annees, anneeId, rechargement]);

  // Une ligne par programme, dans l'ordre des codes ; les filières sans
  // programme, s'il en reste, ferment la grille.
  const lignes = useMemo(() => {
    const parProgramme = new Map(programmes.map((p) => [p.id, { programme: p, filieres: [] }]));
    const sansProgramme = { programme: null, filieres: [] };

    for (const f of toutes) (parProgramme.get(f.programme_id) ?? sansProgramme).filieres.push(f);

    const rangees = [...parProgramme.values()].sort((a, b) => String(a.programme.code).localeCompare(String(b.programme.code)));

    return sansProgramme.filieres.length ? [...rangees, sansProgramme] : rangees;
  }, [programmes, toutes]);

  const codesNiveaux = niveaux.map((n) => n.code);
  const niveauInconnu = niveaux.length ? toutes.filter((f) => !codesNiveaux.includes(f.niveau)) : [];
  const anneeChoisie = (annees ?? []).find((a) => String(a.id) === anneeId);
  const anneeActive = (annees ?? []).find((a) => a.active);
  const etudiantsDeLAnnee = [...delAnnee.values()].reduce((s, f) => s + (f.etudiants_count ?? 0), 0);

  // ── Formulaire ─────────────────────────────────────────────────────────

  const ouvrirCreation = (programme = null, niveau = '') => {
    setErreurs({});
    setFormulaire({
      mode: 'creation',
      valeurs: {
        programme: programme ? String(programme.id) : (programmes[0] ? String(programmes[0].id) : NOUVEAU),
        programme_code: '',
        programme_intitule: '',
        niveau: niveau || niveaux[0]?.code || '',
        code: '',
        intitule: '',
        codeTouche: false,
        intituleTouche: false,
      },
    });
  };

  const ouvrirEdition = (filiere) => {
    setErreurs({});
    setFormulaire({
      mode: 'edition',
      filiere,
      valeurs: {
        programme: filiere.programme_id ? String(filiere.programme_id) : '',
        niveau: filiere.niveau,
        code: filiere.code,
        intitule: filiere.intitule,
        codeTouche: true,
        intituleTouche: true,
      },
    });
  };

  const changer = (champ, valeur) => setFormulaire((f) => ({ ...f, valeurs: { ...f.valeurs, [champ]: valeur } }));

  // Code et intitulé proposés à partir du programme et du niveau, tant qu'on
  // ne les a pas modifiés : IM + L2 donne IM-L2, « Informatique et
  // Mathématiques (L2) ». Le niveau n'est plus retapé à la main.
  const v = formulaire?.valeurs;
  const programmeChoisi = v && v.programme !== NOUVEAU ? programmes.find((p) => String(p.id) === v.programme) : null;
  const baseCode = programmeChoisi?.code ?? v?.programme_code?.trim().toUpperCase() ?? '';
  const baseIntitule = programmeChoisi?.intitule ?? v?.programme_intitule?.trim() ?? '';
  const code = v ? (v.codeTouche ? v.code : (baseCode && v.niveau ? `${baseCode}-${v.niveau}` : '')) : '';
  const intitule = v ? (v.intituleTouche ? v.intitule : (baseIntitule && v.niveau ? `${baseIntitule} (${v.niveau})` : '')) : '';

  const niveauVerrouille = formulaire?.mode === 'edition' && (formulaire.filiere.ues_total ?? 0) > 0;
  const semestresVerrous = niveauVerrouille
    ? Object.keys(formulaire.filiere.ues_par_semestre ?? {}).map((s) => `S${s}`).join(', ')
    : '';

  const enregistrer = async (e) => {
    e.preventDefault();
    setEnregistrement(true);
    setErreurs({});

    try {
      if (formulaire.mode === 'creation') {
        const charge = { niveau: v.niveau, code, intitule };
        if (v.programme === NOUVEAU) {
          charge.programme_code = v.programme_code.trim().toUpperCase();
          charge.programme_intitule = v.programme_intitule.trim();
        } else {
          charge.programme_id = Number(v.programme);
        }
        await api.post('/admin/filieres', charge);
        addToast?.(`Filière ${code} créée.`, 'success');
      } else {
        const { filiere } = formulaire;
        const charge = { code, intitule, programme_id: v.programme ? Number(v.programme) : null };
        if (v.niveau !== filiere.niveau) charge.niveau = v.niveau;
        await api.put(`/admin/filieres/${filiere.id}`, charge);
        addToast?.(`Filière ${code} mise à jour.`, 'success');
      }
      setFormulaire(null);
      setRechargement((n) => n + 1);
    } catch (err) {
      const d = err.response?.data;
      setErreurs(d?.errors ?? { general: [d?.message || "L'enregistrement a échoué."] });
    } finally {
      setEnregistrement(false);
    }
  };

  const supprimer = async () => {
    setSuppression(true);
    try {
      await api.delete(`/admin/filieres/${aSupprimer.id}`);
      addToast?.(`Filière ${aSupprimer.code} supprimée.`, 'success');
      setASupprimer(null);
      setRechargement((n) => n + 1);
    } catch (err) {
      addToast?.(err.response?.data?.message || 'La suppression a échoué.', 'error');
    } finally {
      setSuppression(false);
    }
  };

  // ── Rendu ──────────────────────────────────────────────────────────────

  const renommerProgramme = async (e) => {
    e.preventDefault();
    setRenommage(true);
    try {
      const { data } = await api.put(`/admin/programmes/${programmeEdite.id}`, {
        intitule: programmeEdite.intitule.trim(),
        renommer_filieres: programmeEdite.renommer_filieres,
      });
      addToast?.(data?.message || 'Programme renommé.', 'success');
      setProgrammeEdite(null);
      setRechargement((n) => n + 1);
    } catch (err) {
      addToast?.(err.response?.data?.errors?.intitule?.[0] || err.response?.data?.message || 'Le renommage a échoué.', 'error');
    } finally {
      setRenommage(false);
    }
  };

  return (
    <div>
      {/* En-tête */}
      <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Filières</h1>
          <p className="text-sm text-on-surface-variant">
            Une filière est un programme à un niveau : IM + L2 = IM-L2.
            {!loading && anneeChoisie && ` ${pluriel(etudiantsDeLAnnee, 'étudiant')} inscrit${etudiantsDeLAnnee > 1 ? 's' : ''} en ${anneeChoisie.libelle}.`}
          </p>
        </div>
        <div className="flex items-end gap-3">
          <div>
            <label htmlFor="filieres-annee" className={ETIQUETTE}>Année</label>
            <select id="filieres-annee" value={anneeId} onChange={(e) => setAnneeId(e.target.value)} className={CHAMP}>
              <option value="">Toutes les années</option>
              {(annees ?? []).map((a) => <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (active)' : ''}</option>)}
            </select>
          </div>
          <button type="button" onClick={() => ouvrirCreation()}
            className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all whitespace-nowrap">
            <FiPlus aria-hidden="true" /> Nouvelle filière
          </button>
        </div>
      </div>

      {loading && toutes.length === 0 ? (
        <div className="text-center py-12 text-on-surface-variant">Chargement…</div>
      ) : erreurChargement ? (
        <div className="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-xl">
          Les filières n'ont pas pu être chargées. Rechargez la page.
        </div>
      ) : niveaux.length === 0 ? (
        <div className="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-xl">
          La liste des niveaux n'a pas pu être chargée. Rechargez la page.
        </div>
      ) : (
        <>
          <div className={`bg-surface-container-lowest rounded-xl border border-outline-variant/10 shadow-sm overflow-x-auto transition-opacity ${loading ? 'opacity-60' : ''}`} aria-busy={loading}>
            <table className="w-full text-sm border-collapse">
              <caption className="sr-only">Filières par programme et par niveau</caption>
              <thead>
                <tr className="text-left text-[10px] uppercase tracking-wider text-on-surface-variant border-b border-outline-variant/20">
                  <th scope="col" className="p-3 font-semibold w-52">Programme</th>
                  {niveaux.map((n) => (
                    <th key={n.code} scope="col" className="p-3 font-semibold min-w-[10.5rem]">
                      {n.code} <span className="normal-case tracking-normal font-normal">· {n.semestres.map((s) => `S${s}`).join('-')}</span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {lignes.length === 0 ? (
                  <tr>
                    <td colSpan={niveaux.length + 1} className="p-8 text-center text-on-surface-variant">
                      Aucune filière. Créez la première avec « Nouvelle filière ».
                    </td>
                  </tr>
                ) : lignes.map(({ programme, filieres }) => (
                  <tr key={programme?.id ?? 'sans-programme'} className="border-b border-outline-variant/10 last:border-0 align-top">
                    <th scope="row" className="p-3 text-left font-normal">
                      <span className="flex items-center gap-1.5">
                        <span className="font-mono text-xs font-bold text-primary">{programme?.code ?? 'Sans programme'}</span>
                        {programme && (
                          <button
                            type="button"
                            onClick={() => setProgrammeEdite({ id: programme.id, code: programme.code, ancien: programme.intitule, intitule: programme.intitule, renommer_filieres: true })}
                            aria-label={`Renommer le programme ${programme.code}`}
                            title="Renommer le programme"
                            className="p-1 rounded text-on-surface-variant hover:text-primary hover:bg-surface-container-high"
                          >
                            <FiEdit2 size={12} aria-hidden="true" />
                          </button>
                        )}
                      </span>
                      <span className="block text-xs text-on-surface-variant mt-0.5">{programme?.intitule ?? 'À rattacher à un programme'}</span>
                    </th>
                    {niveaux.map((n) => {
                      const ici = filieres.filter((f) => f.niveau === n.code);

                      return (
                        <td key={n.code} className="p-2">
                          {ici.length > 0 ? ici.map((f) => (
                            <CaseFiliere
                              key={f.id}
                              filiere={f}
                              annee={delAnnee.get(f.id)}
                              niveau={n}
                              anneeId={anneeId}
                              onModifier={() => ouvrirEdition(f)}
                              onSupprimer={() => setASupprimer(f)}
                            />
                          )) : programme && (
                            <button
                              type="button"
                              onClick={() => ouvrirCreation(programme, n.code)}
                              aria-label={`Ouvrir le niveau ${n.code} du programme ${programme.code}`}
                              className="w-full min-h-[5.5rem] rounded-lg border border-dashed border-outline-variant/40 text-xs text-on-surface-variant hover:border-primary hover:text-primary transition-colors"
                            >
                              + Ouvrir {n.code}
                            </button>
                          )}
                        </td>
                      );
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {niveauInconnu.length > 0 && (
            <p role="alert" className="mt-4 flex items-start gap-2 text-xs text-on-surface-variant">
              <FiAlertTriangle className="text-error shrink-0 mt-0.5" aria-hidden="true" />
              <span>
                Niveau non reconnu, donc absent de la grille : {niveauInconnu.map((f) => `${f.code} (« ${f.niveau} »)`).join(', ')}.
                Corrigez leur niveau pour qu'elles y apparaissent.
              </span>
            </p>
          )}
        </>
      )}

      {/* Création / modification */}
      <Modal isOpen={Boolean(formulaire)} onClose={() => setFormulaire(null)}
        title={formulaire?.mode === 'edition' ? `Modifier ${formulaire.filiere.code}` : 'Nouvelle filière'}>
        {formulaire && (
          <form onSubmit={enregistrer} className="space-y-4">
            <ErreurChamp messages={erreurs.general} />

            <div>
              <label htmlFor="filiere-programme" className={ETIQUETTE}>Programme</label>
              <select id="filiere-programme" value={v.programme} onChange={(e) => changer('programme', e.target.value)} className={CHAMP}>
                {formulaire.mode === 'edition' && !v.programme && <option value="">Aucun</option>}
                {programmes.map((p) => <option key={p.id} value={p.id}>{p.code} — {p.intitule}</option>)}
                {formulaire.mode === 'creation' && <option value={NOUVEAU}>Nouveau programme…</option>}
              </select>
              <ErreurChamp messages={erreurs.programme_id} />
            </div>

            {v.programme === NOUVEAU && (
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label htmlFor="programme-code" className={ETIQUETTE}>Code du programme</label>
                  <input id="programme-code" className={`${CHAMP} font-mono`} value={v.programme_code} maxLength={15}
                    onChange={(e) => changer('programme_code', e.target.value.toUpperCase())} placeholder="IM" required />
                  <ErreurChamp messages={erreurs.programme_code} />
                </div>
                <div className="sm:col-span-2">
                  <label htmlFor="programme-intitule" className={ETIQUETTE}>Intitulé du programme</label>
                  <input id="programme-intitule" className={CHAMP} value={v.programme_intitule}
                    onChange={(e) => changer('programme_intitule', e.target.value)} placeholder="Informatique et Mathématiques" required />
                  <ErreurChamp messages={erreurs.programme_intitule} />
                </div>
              </div>
            )}

            <div>
              <label htmlFor="filiere-niveau" className={ETIQUETTE}>Niveau</label>
              <select id="filiere-niveau" value={v.niveau} onChange={(e) => changer('niveau', e.target.value)} disabled={niveauVerrouille} className={CHAMP}>
                {niveaux.map((n) => (
                  <option key={n.code} value={n.code}>{n.code} — {n.libelle} ({n.semestres.map((s) => `S${s}`).join(', ')})</option>
                ))}
              </select>
              {niveauVerrouille && (
                <p className="text-xs text-on-surface-variant mt-1">
                  Ses UE sont en {semestresVerrous} : le niveau ne change plus. Pour faire passer ses étudiants au niveau
                  suivant, utilisez la promotion (Étudiants → Promouvoir).
                </p>
              )}
              <ErreurChamp messages={erreurs.niveau} />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label htmlFor="filiere-code" className={ETIQUETTE}>Code</label>
                <input id="filiere-code" className={`${CHAMP} font-mono`} value={code} maxLength={20}
                  onChange={(e) => setFormulaire((f) => ({ ...f, valeurs: { ...f.valeurs, code: e.target.value.toUpperCase(), codeTouche: true } }))} required />
                <p className="text-[11px] text-on-surface-variant mt-1">20 caractères au plus, unique dans votre établissement.</p>
                <ErreurChamp messages={erreurs.code} />
              </div>
              <div className="sm:col-span-2">
                <label htmlFor="filiere-intitule" className={ETIQUETTE}>Intitulé</label>
                <input id="filiere-intitule" className={CHAMP} value={intitule}
                  onChange={(e) => setFormulaire((f) => ({ ...f, valeurs: { ...f.valeurs, intitule: e.target.value, intituleTouche: true } }))} required />
                <ErreurChamp messages={erreurs.intitule} />
              </div>
            </div>

            {formulaire.mode === 'creation' && (
              <p className="text-xs text-on-surface-variant">
                {anneeActive
                  ? `Elle sera rattachée à l'année active (${anneeActive.libelle}).`
                  : "Aucune année active : elle ne sera rattachée à aucune année tant qu'elle n'aura ni étudiant ni UE."}
              </p>
            )}

            <div className="flex justify-end gap-3 pt-2">
              <button type="button" onClick={() => setFormulaire(null)} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">
                Annuler
              </button>
              <button type="submit" disabled={enregistrement}
                className="flex items-center justify-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50 transition-all">
                {enregistrement && <FiLoader className="animate-spin" aria-hidden="true" />}
                {enregistrement ? 'Enregistrement…' : formulaire.mode === 'edition' ? 'Enregistrer' : 'Créer'}
              </button>
            </div>
          </form>
        )}
      </Modal>

      {/* Suppression */}
      <Modal isOpen={Boolean(aSupprimer)} onClose={() => setASupprimer(null)} title="Supprimer la filière">
        {aSupprimer && (
          <div className="space-y-4">
            <p className="text-sm text-on-surface-variant">
              {aSupprimer.code} — {aSupprimer.intitule} n'a ni étudiant, ni UE, ni séance. Sa suppression est définitive.
            </p>
            <div className="flex justify-end gap-3">
              <button type="button" onClick={() => setASupprimer(null)} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">
                Annuler
              </button>
              <button type="button" onClick={supprimer} disabled={suppression}
                className="flex items-center gap-2 px-5 py-2.5 bg-error text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50 transition-all">
                {suppression && <FiLoader className="animate-spin" aria-hidden="true" />}Supprimer
              </button>
            </div>
          </div>
        )}
      </Modal>

      <Modal isOpen={Boolean(programmeEdite)} onClose={() => !renommage && setProgrammeEdite(null)} title={programmeEdite ? `Renommer le programme ${programmeEdite.code}` : ''}>
        {programmeEdite && (
          <form onSubmit={renommerProgramme} className="space-y-4 text-sm">
            <div className="space-y-1.5">
              <label htmlFor="programme-intitule" className="text-xs font-semibold text-on-surface-variant">Intitulé</label>
              <input
                id="programme-intitule"
                value={programmeEdite.intitule}
                onChange={(e) => setProgrammeEdite({ ...programmeEdite, intitule: e.target.value })}
                required
                maxLength={255}
                className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              />
              <p className="text-xs text-on-surface-variant">Le code {programmeEdite.code} ne change pas : il préfixe les codes des filières.</p>
            </div>
            <label htmlFor="programme-renommer" className="flex items-start gap-2">
              <input
                id="programme-renommer"
                type="checkbox"
                className="mt-0.5"
                checked={programmeEdite.renommer_filieres}
                onChange={(e) => setProgrammeEdite({ ...programmeEdite, renommer_filieres: e.target.checked })}
              />
              <span>
                Renommer aussi les filières au nom dérivé
                <span className="block text-xs text-on-surface-variant">
                  « {programmeEdite.ancien} (L1) » devient « {programmeEdite.intitule.trim() || '…'} (L1) ». Un intitulé personnalisé n'est pas touché.
                </span>
              </span>
            </label>
            <div className="flex justify-end gap-3 pt-2">
              <button type="button" onClick={() => setProgrammeEdite(null)} disabled={renommage} className="px-5 py-2.5 text-sm font-semibold text-on-surface-variant hover:bg-surface-container-high rounded-xl">
                Annuler
              </button>
              <button type="submit" disabled={renommage || !programmeEdite.intitule.trim()} className="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50">
                {renommage && <FiLoader className="animate-spin" aria-hidden="true" />} Renommer
              </button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  );
}
