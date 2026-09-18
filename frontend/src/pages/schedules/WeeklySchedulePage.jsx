import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiAlertTriangle, FiLoader, FiUpload, FiPlus, FiFileText, FiX, FiCalendar } from 'react-icons/fi';
import api from '../../api/axios';
import useFiltresAcademiques from '../../hooks/useFiltresAcademiques';
import BandeauAnneeClose from '../../components/ui/BandeauAnneeClose';
import Modal from '../../components/ui/Modal';
import CsvTemplateDownload from '../../components/import/CsvTemplateDownload';
import FormulaireCreneau from '../../components/schedules/FormulaireCreneau';
import { useToastCtx } from '../../context/ToastContext';
import { JOURS, enMinutes, enHeure, disposer } from '../../utils/emploiDuTemps';

const HAUTEUR_HEURE = 56;

// Une couleur par type de séance, tirée du thème : lisible en clair comme en sombre.
const COULEURS = {
  cm: 'bg-primary-container text-on-primary-container',
  td: 'bg-secondary-container text-on-secondary-container',
  tp: 'bg-tertiary-container text-on-tertiary-container',
  evaluation: 'bg-warning-container text-on-surface',
};
const LIBELLES_TYPE = { cm: 'CM', td: 'TD', tp: 'TP', evaluation: 'Évaluation' };

const CHAMP_FILTRE = 'w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50 disabled:cursor-not-allowed';
const LIBELLE_FILTRE = 'text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider';

/** « INF1322 (groupe G1) 08:00–10:00 » */
const decrire = (o) => `${o.ec_code}${o.groupe ? ` (groupe ${o.groupe})` : ''} ${o.heure_debut}–${o.heure_fin}`;

/**
 * L'emploi du temps : la semaine type, créneau par créneau. Les séances en sont
 * générées chaque nuit, pendant la période de chaque semestre et hors
 * fermetures ; une séance ponctuelle se programme dans Séances.
 *
 * La page affichait les séances datées d'une semaine, et son « Ajouter un EDT »
 * créait une séance : l'emploi du temps lui-même ne se consultait ni ne se
 * corrigeait nulle part, il ne s'alimentait que par import.
 */
export default function WeeklySchedulePage() {
  const navigate = useNavigate();
  const { addToast } = useToastCtx() ?? {};
  const filtres = useFiltresAcademiques();
  const { annees, filieres } = filtres;

  const [rechargement, setRechargement] = useState(0);
  const [etat, setEtat] = useState({ cle: '', creneaux: [], erreur: '' });
  const [formulaire, setFormulaire] = useState({ ouvert: false, creneau: null });
  const [rapport, setRapport] = useState({ chargement: false, donnees: null, erreur: '' });

  // Import EDT
  const [showImportModal, setShowImportModal] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importDragOver, setImportDragOver] = useState(false);
  const [importUploading, setImportUploading] = useState(false);
  // Deux formats pour la même modale : PDF analysé par l'IA, ou CSV structuré.
  const [importFormat, setImportFormat] = useState('pdf');
  const [importResultat, setImportResultat] = useState(null);
  const [importError, setImportError] = useState('');
  const importFileRef = useRef(null);

  const cle = [filtres.annee, filtres.filiere, filtres.semestre, rechargement].join('|');

  useEffect(() => {
    let annule = false;
    const params = {};
    if (filtres.annee) params.annee_id = filtres.annee;
    if (filtres.filiere) params.filiere_id = filtres.filiere;
    if (filtres.semestre) params.semestre = filtres.semestre;

    api.get('/admin/emploi-du-temps', { params })
      .then(({ data }) => { if (!annule) setEtat({ cle, creneaux: Array.isArray(data?.data) ? data.data : [], erreur: '' }); })
      .catch((err) => { if (!annule) setEtat({ cle, creneaux: [], erreur: err.response?.data?.message || "L'emploi du temps n'a pas pu être chargé." }); });

    return () => { annule = true; };
  }, [cle, filtres.annee, filtres.filiere, filtres.semestre]);

  const charge = etat.cle === cle;
  const { creneaux } = etat;
  const anneeActive = annees.find((a) => a.active) ?? null;
  const anneeId = filtres.annee || (anneeActive ? String(anneeActive.id) : '');
  const rafraichir = () => setRechargement((n) => n + 1);

  // ─── La grille ─────────────────────────────────────────────
  const jours = useMemo(() => (creneaux.some((c) => c.jour_semaine === 7) ? [1, 2, 3, 4, 5, 6, 7] : [1, 2, 3, 4, 5, 6]), [creneaux]);

  const [debutGrille, finGrille] = useMemo(() => [
    Math.min(8 * 60, ...creneaux.map((c) => Math.floor(enMinutes(c.heure_debut) / 60) * 60)),
    Math.max(18 * 60, ...creneaux.map((c) => Math.ceil(enMinutes(c.heure_fin) / 60) * 60)),
  ], [creneaux]);

  const heures = useMemo(() => {
    const liste = [];
    for (let m = debutGrille; m < finGrille; m += 60) liste.push(m);
    return liste;
  }, [debutGrille, finGrille]);

  const parJour = useMemo(() => Object.fromEntries(jours.map((j) => [j, disposer(
    creneaux.filter((c) => c.jour_semaine === j).map((c) => ({ ...c, debut: enMinutes(c.heure_debut), fin: enMinutes(c.heure_fin) }))
  )])), [jours, creneaux]);

  const enConflit = creneaux.filter((c) => c.conflits?.length);
  const colonnes = { gridTemplateColumns: `64px repeat(${jours.length}, minmax(0, 1fr))` };

  const ouvrir = (creneau = null) => setFormulaire({ ouvert: true, creneau });

  const apresEnregistrement = (message) => {
    addToast?.(message, 'success');
    setFormulaire({ ouvert: false, creneau: null });
    setRapport((r) => ({ ...r, donnees: null }));
    rafraichir();
  };

  const chargerRapport = async () => {
    setRapport({ chargement: true, donnees: null, erreur: '' });
    try {
      const { data } = await api.get('/admin/emploi-du-temps/conflits', { params: anneeId ? { annee_id: anneeId } : {} });
      setRapport({ chargement: false, donnees: data?.data ?? null, erreur: '' });
    } catch (err) {
      setRapport({ chargement: false, donnees: null, erreur: err.response?.data?.message || 'Le rapport des conflits a échoué.' });
    }
  };

  // ─── Import ─────────────────────────────────────────────────
  const handleImportDrop = (e) => {
    e.preventDefault();
    setImportDragOver(false);
    const f = e.dataTransfer.files[0];
    if (!f) return;

    const estPdf = f.type === 'application/pdf' || f.name.endsWith('.pdf');
    const estCsv = f.name.endsWith('.csv');

    if (importFormat === 'pdf' ? estPdf : estCsv) {
      setImportFile(f);
      setImportError('');
    } else {
      setImportError(importFormat === 'pdf' ? 'Veuillez sélectionner un fichier PDF.' : 'Veuillez sélectionner un fichier CSV.');
    }
  };

  /** Import CSV : réponse directe du serveur, sans étape de validation. */
  const handleImportCsv = async () => {
    if (!importFile) return;
    setImportUploading(true);
    setImportError('');
    setImportResultat(null);
    try {
      const formData = new FormData();
      formData.append('file', importFile);
      const { data } = await api.post('/admin/import/csv/schedule', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
      const d = data?.data ?? data;
      setImportResultat({
        crees: d.created ?? d.success ?? 0,
        erreurs: Array.isArray(d.errors) ? d.errors : [],
        avertissements: Array.isArray(d.warnings) ? d.warnings : [],
        // Salles que le fichier nommait sans qu'elles soient déclarées : le
        // serveur les crée, sans GPS ni Wi-Fi. L'administration doit le savoir.
        sallesCreees: Array.isArray(d.salles_creees) ? d.salles_creees : [],
      });
      rafraichir();
    } catch (err) {
      setImportError(err.response?.data?.message || "Erreur lors de l'import du fichier.");
    } finally {
      setImportUploading(false);
    }
  };

  const handleImportUpload = async () => {
    if (!importFile) return;
    if (importFormat === 'csv') return handleImportCsv();
    setImportUploading(true);
    setImportError('');
    try {
      const formData = new FormData();
      formData.append('file', importFile);
      const { data } = await api.post('/admin/import/schedule', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
      const charge = data?.data ?? data;
      const analysisId = charge?.analysis_id ?? charge?.id ?? null;
      if (!analysisId) {
        setImportError("Le serveur n'a pas renvoyé d'identifiant d'analyse. Relancez l'import.");
        setImportUploading(false);
        return;
      }
      // Passage de relais vers l'écran de progression, sous une clé distincte
      // du résultat de l'analyse (« import_analysis »).
      sessionStorage.setItem('import_en_cours', JSON.stringify({ analysis_id: analysisId, type: 'schedule' }));
      navigate('/import/ai-analysis');
    } catch (err) {
      setImportError(err.response?.data?.message || "Erreur lors de l'import du fichier.");
      setImportUploading(false);
    }
  };

  const resetImport = () => {
    setImportFile(null);
    setImportError('');
    setImportUploading(false);
    setImportResultat(null);
  };

  return (
    <div>
      <div className="flex items-center justify-between flex-wrap gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Emploi du temps</h1>
          <p className="text-sm text-on-surface-variant max-w-2xl">
            La semaine type. Les séances en sont générées chaque nuit, pendant la période de chaque semestre,
            hors fermetures. Une séance ponctuelle se programme dans{' '}
            <Link to="/schedules/events" className="font-semibold text-primary hover:underline">Séances</Link>.
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button type="button" onClick={() => ouvrir()} disabled={filtres.anneeClose}
            className="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl font-bold text-sm shadow-sm hover:opacity-90 disabled:opacity-30 disabled:cursor-not-allowed">
            <FiPlus size={15} aria-hidden="true" /> Ajouter un créneau
          </button>
          <button type="button" onClick={() => setShowImportModal(true)} disabled={filtres.anneeClose}
            className="flex items-center gap-2 px-4 py-2.5 bg-surface-container-high text-on-surface rounded-xl font-bold text-sm border border-outline-variant/20 hover:bg-surface-container-low disabled:opacity-30 disabled:cursor-not-allowed">
            <FiUpload size={15} aria-hidden="true" /> Importer un EDT
          </button>
        </div>
      </div>

      <div className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 mb-6">
        <div className="flex flex-wrap items-end gap-3">
          <div className="space-y-1 flex-1 min-w-[10rem]">
            <label htmlFor="edt-filtre-annee" className={LIBELLE_FILTRE}>Année académique</label>
            <select id="edt-filtre-annee" value={filtres.annee} onChange={(e) => filtres.setAnnee(e.target.value)} className={CHAMP_FILTRE}>
              <option value="">Année active</option>
              {annees.map((a) => <option key={a.id} value={a.id}>{a.libelle || a.annee}{a.active ? ' (active)' : ''}</option>)}
            </select>
          </div>
          <div className="space-y-1 flex-1 min-w-[10rem]">
            <label htmlFor="edt-filtre-filiere" className={LIBELLE_FILTRE}>Filière</label>
            <select id="edt-filtre-filiere" value={filtres.filiere} onChange={(e) => filtres.setFiliere(e.target.value)}
              disabled={filtres.anneeVide} className={CHAMP_FILTRE}>
              <option value="">{filtres.anneeVide ? 'Aucune filière cette année' : 'Toutes les filières'}</option>
              {filieres.map((f) => <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>)}
            </select>
          </div>
          <div className="space-y-1 flex-1 min-w-[8rem]">
            <label htmlFor="edt-filtre-semestre" className={LIBELLE_FILTRE}>Semestre</label>
            <select id="edt-filtre-semestre" value={filtres.semestre} onChange={(e) => filtres.setSemestre(e.target.value)}
              disabled={filtres.semestres.length === 0} className={CHAMP_FILTRE}>
              <option value="">Tous les semestres</option>
              {filtres.semestres.map((s) => <option key={s} value={s}>Semestre {s}</option>)}
            </select>
          </div>
        </div>
      </div>

      {filtres.anneeClose && <div className="mb-6"><BandeauAnneeClose annee={filtres.anneeChoisie} /></div>}

      {etat.erreur && (
        <p role="alert" className="mb-6 flex items-start gap-2 p-3 bg-error/10 text-error rounded-lg text-sm">
          <FiAlertTriangle className="mt-0.5 shrink-0" aria-hidden="true" /> {etat.erreur}
        </p>
      )}

      <div className="flex flex-wrap items-center gap-3 mb-3 text-xs text-on-surface-variant" aria-label="Légende">
        {Object.entries(LIBELLES_TYPE).map(([type, libelle]) => (
          <span key={type} className="flex items-center gap-1.5">
            <span className={`w-3 h-3 rounded ${COULEURS[type]}`} aria-hidden="true" /> {libelle}
          </span>
        ))}
        <span className="flex items-center gap-1.5">
          <span className="w-3 h-3 rounded ring-2 ring-error" aria-hidden="true" /> En conflit
        </span>
      </div>

      {!charge ? (
        <div className="flex items-center justify-center h-64"><FiLoader className="animate-spin text-primary w-8 h-8" aria-label="Chargement" /></div>
      ) : creneaux.length === 0 ? (
        <div className="bg-surface-container-lowest rounded-xxl p-10 text-center text-sm text-on-surface-variant border border-outline-variant/10">
          <FiCalendar className="mx-auto mb-3 text-2xl text-primary" aria-hidden="true" />
          Aucun créneau pour ces filtres. Ajoutez-en un, ou importez l'emploi du temps (PDF ou CSV).
        </div>
      ) : (
        <div className="bg-surface-container-lowest rounded-xxl shadow-sm border border-outline-variant/10 overflow-x-auto">
          <div className="min-w-[760px]">
            <div className="grid border-b border-outline-variant/10" style={colonnes}>
              <div />
              {jours.map((j) => <div key={j} className="p-3 text-center text-xs font-bold text-primary">{JOURS[j]}</div>)}
            </div>
            <div className="grid" style={colonnes}>
              <div>
                {heures.map((m) => (
                  <div key={m} style={{ height: HAUTEUR_HEURE }} className="border-b border-outline-variant/5 pr-2 pt-1 text-right text-[10px] font-mono text-on-surface-variant">
                    {enHeure(m)}
                  </div>
                ))}
              </div>
              {jours.map((j) => (
                <div key={j} className="relative border-l border-outline-variant/10" style={{ height: ((finGrille - debutGrille) / 60) * HAUTEUR_HEURE }}>
                  {heures.map((m) => <div key={m} style={{ height: HAUTEUR_HEURE }} className="border-b border-outline-variant/5" />)}
                  {parJour[j].map((c) => (
                    <button key={c.id} type="button" onClick={() => ouvrir(c)} disabled={filtres.anneeClose}
                      title={[`${c.ec?.code} — ${c.ec?.intitule}`, ...(c.conflits ?? [])].join('\n')}
                      aria-label={`${c.ec?.code}, ${JOURS[c.jour_semaine]} ${c.heure_debut}–${c.heure_fin}${c.conflits?.length ? ', en conflit' : ''}`}
                      className={`absolute rounded-lg px-1.5 py-1 text-left overflow-hidden shadow-sm hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-primary disabled:cursor-default ${COULEURS[c.type_cours] ?? COULEURS.cm} ${c.conflits?.length ? 'ring-2 ring-error' : ''}`}
                      style={{
                        top: ((c.debut - debutGrille) / 60) * HAUTEUR_HEURE + 1,
                        height: Math.max(((c.fin - c.debut) / 60) * HAUTEUR_HEURE - 2, 18),
                        left: `calc(${(c.voie / c.voies) * 100}% + 2px)`,
                        width: `calc(${100 / c.voies}% - 4px)`,
                      }}>
                      <span className="flex items-center gap-1 text-[11px] font-bold leading-tight">
                        {c.conflits?.length > 0 && <FiAlertTriangle className="text-error shrink-0" aria-hidden="true" />}
                        <span className="truncate">{c.ec?.code}</span>
                      </span>
                      <span className="block text-[10px] leading-tight truncate">{c.ec?.intitule}</span>
                      <span className="block text-[10px] leading-tight opacity-80 truncate">
                        {LIBELLES_TYPE[c.type_cours] ?? c.type_cours}{c.groupe ? ` ${c.groupe}` : ''}{c.salle ? ` · ${c.salle}` : ''}
                      </span>
                      {c.enseignant && <span className="block text-[10px] leading-tight opacity-80 truncate">{c.enseignant}</span>}
                    </button>
                  ))}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {enConflit.length > 0 && (
        <section aria-labelledby="titre-conflits" className="mt-6 bg-error-container rounded-xl p-4">
          <h2 id="titre-conflits" className="flex items-center gap-2 text-sm font-bold text-on-error-container">
            <FiAlertTriangle aria-hidden="true" /> {enConflit.length} créneau{enConflit.length > 1 ? 'x' : ''} en conflit
          </h2>
          <ul className="mt-2 space-y-1.5 text-xs text-on-error-container">
            {enConflit.map((c) => (
              <li key={c.id}>
                <button type="button" onClick={() => ouvrir(c)} disabled={filtres.anneeClose} className="font-semibold underline disabled:no-underline">
                  {c.ec?.code} · {JOURS[c.jour_semaine]} {c.heure_debut}–{c.heure_fin}
                </button>{' '}
                — {c.conflits.join(' ')}
              </li>
            ))}
          </ul>
        </section>
      )}

      <section aria-labelledby="titre-rapport" className="mt-6 bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/10">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 id="titre-rapport" className="text-sm font-bold text-on-surface">Conflits déjà en base</h2>
            <p className="text-xs text-on-surface-variant">
              Créneaux et séances de l'année enregistrés avant la règle commune. Rien n'est corrigé d'office.
            </p>
          </div>
          <button type="button" onClick={chargerRapport} disabled={rapport.chargement}
            className="flex items-center gap-2 px-4 py-2 bg-surface-container-high rounded-xl text-sm font-semibold hover:opacity-90 disabled:opacity-50">
            {rapport.chargement && <FiLoader className="animate-spin" aria-hidden="true" />} Vérifier
          </button>
        </div>
        {rapport.erreur && <p role="alert" className="mt-3 text-sm text-error">{rapport.erreur}</p>}
        {rapport.donnees && (
          <div className="mt-4 space-y-4 text-xs">
            {[['creneaux', "Dans l'emploi du temps"], ['seances', 'Entre séances']].map(([cleListe, titre]) => {
              const liste = rapport.donnees[cleListe] ?? [];
              return (
                <div key={cleListe}>
                  <h3 className="font-semibold text-on-surface mb-1">{titre} : {liste.length === 0 ? 'aucun conflit' : `${liste.length} conflit${liste.length > 1 ? 's' : ''}`}</h3>
                  {liste.length > 0 && (
                    <ul className="space-y-1 text-on-surface-variant max-h-64 overflow-y-auto">
                      {liste.slice(0, 100).map((p, i) => (
                        <li key={i}>
                          <span className="font-semibold text-on-surface">{p.quand}</span> : {decrire(p.a)} et {decrire(p.b)}
                          {cleListe === 'seances' && <span className="ml-1">({p.a_venir ? 'à venir' : 'passée'})</span>} — {p.motifs.join(' ')}
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </section>

      <FormulaireCreneau
        ouvert={formulaire.ouvert}
        creneau={formulaire.creneau}
        anneeId={anneeId}
        filiereId={filtres.filiere}
        semestre={filtres.semestre}
        onFermer={() => setFormulaire({ ouvert: false, creneau: null })}
        onEnregistre={apresEnregistrement}
      />

      {/* ─── Modal Import EDT ─────────────────────────────── */}
      <Modal
        isOpen={showImportModal}
        // Pas de fermeture pendant l'envoi : resetImport effacerait l'état d'une
        // requête encore en cours.
        onClose={() => { if (!importUploading) { setShowImportModal(false); resetImport(); } }}
        title="Importer un emploi du temps"
        size="md"
      >
        {/* Le PDF passe par une extraction IA suivie d'une validation ; le CSV
            est structuré et s'applique directement, avec les mêmes règles. */}
        <div className="flex gap-1 mb-5 bg-surface-container-high rounded-xl p-1">
          {[['pdf', 'PDF — analyse par IA'], ['csv', 'CSV — structuré']].map(([format, libelle]) => (
            <button key={format} type="button" onClick={() => { setImportFormat(format); resetImport(); }} disabled={importUploading}
              className={`flex-1 px-3 py-2 rounded-lg text-xs font-bold transition-all disabled:opacity-50 ${importFormat === format ? 'bg-primary text-white shadow-sm' : 'text-on-surface-variant hover:text-primary'}`}>
              {libelle}
            </button>
          ))}
        </div>

        <div onDragOver={(e) => { e.preventDefault(); setImportDragOver(true); }} onDragLeave={() => setImportDragOver(false)} onDrop={handleImportDrop}
          className={`border-2 border-dashed rounded-xl p-10 text-center transition-all cursor-pointer ${importDragOver ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-primary/40'} ${importFile ? 'bg-surface-container-low' : ''}`}
          onClick={() => importFileRef.current?.click()}>
          <input ref={importFileRef} type="file" accept={importFormat === 'pdf' ? '.pdf' : '.csv'} aria-label={importFormat === 'pdf' ? "Choisir le fichier PDF de l'emploi du temps à importer" : "Choisir le fichier CSV de l'emploi du temps à importer"} className="hidden" onChange={(e) => {
            const f = e.target.files[0]; if (f) { setImportFile(f); setImportError(''); }
          }} />
          {!importFile ? (
            <>
              <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                <FiUpload className="text-2xl text-primary" />
              </div>
              <h3 className="text-sm font-semibold text-on-surface mb-1">
                {importFormat === 'pdf' ? 'Importez un fichier PDF' : 'Importez un fichier CSV'}
              </h3>
              <p className="text-xs text-on-surface-variant mb-4">
                {importFormat === 'pdf' ? 'Analyse par IA' : 'Une ligne par créneau'} — ou{' '}
                <span className="text-primary font-semibold cursor-pointer hover:underline">parcourez</span>
              </p>
              <p className="text-[10px] text-on-surface-variant/60">
                {importFormat === 'pdf' ? 'PDF uniquement — 10 Mo max' : 'CSV uniquement — 5 Mo max'}
              </p>
            </>
          ) : (
            <div className="flex items-center gap-4 justify-center">
              <FiFileText className="text-2xl text-primary" />
              <div className="text-left">
                <p className="text-sm font-medium text-on-surface">{importFile.name}</p>
                <p className="text-[10px] text-on-surface-variant">{(importFile.size / 1024).toFixed(1)} Ko</p>
              </div>
              <button type="button" aria-label="Retirer le fichier" onClick={(e) => { e.stopPropagation(); resetImport(); }} className="p-2 hover:bg-surface-container-high rounded-lg transition-colors">
                <FiX className="text-outline" />
              </button>
            </div>
          )}
        </div>

        {importError && (
          <div className="mt-4 flex items-center gap-2 p-3 bg-error-container rounded-xl text-on-error-container text-sm">
            <FiAlertTriangle /> {importError}
          </div>
        )}

        {importFile && !importUploading && !importResultat && (
          <button type="button" onClick={handleImportUpload}
            className="mt-6 w-full flex items-center justify-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold text-sm shadow-sm hover:opacity-90">
            <FiUpload /> {importFormat === 'pdf' ? "Analyser avec l'IA" : "Importer l'emploi du temps"}
          </button>
        )}

        {importUploading && (
          <div className="mt-6 rounded-xl p-6 border border-outline-variant/10 text-center">
            <FiLoader className="animate-spin mx-auto text-primary text-2xl mb-3" />
            <p className="font-semibold text-primary text-sm">{importFormat === 'pdf' ? 'Analyse IA en cours…' : 'Import CSV en cours…'}</p>
          </div>
        )}

        {importResultat && (
          <div className="mt-6 rounded-xl p-4 bg-surface-container-low border border-outline-variant/10">
            <p className="text-sm font-bold text-primary mb-1">{importResultat.crees} créneau(x) créé(s)</p>
            {importResultat.sallesCreees.length > 0 && (
              <p role="status" className="text-xs text-on-surface-variant mt-1">
                <span className="font-semibold text-on-surface">Salles créées : {importResultat.sallesCreees.join(', ')}</span>
                {' '}— elles ne vérifient que le QR code.{' '}
                <Link to="/settings/salles?filtre=a-configurer" className="font-semibold text-primary hover:underline">
                  Configurer leur GPS et leur Wi-Fi
                </Link>
              </p>
            )}
            {importResultat.avertissements.length > 0 && (
              <ul className="text-[11px] text-on-surface-variant list-disc list-inside space-y-0.5 mt-2">
                {importResultat.avertissements.slice(0, 20).map((a, i) => (
                  <li key={i}>{typeof a === 'string' ? a : `Ligne ${a.line} : ${a.warning}`}</li>
                ))}
              </ul>
            )}
            {importResultat.erreurs.length > 0 ? (
              <>
                <p className="text-xs font-semibold text-error mt-2 mb-1">{importResultat.erreurs.length} ligne(s) refusée(s) :</p>
                <ul className="text-[11px] text-on-surface-variant list-disc list-inside space-y-0.5 max-h-40 overflow-y-auto">
                  {importResultat.erreurs.slice(0, 20).map((e, i) => (
                    <li key={i}>{typeof e === 'string' ? e : `Ligne ${e.line} : ${e.error}`}</li>
                  ))}
                </ul>
              </>
            ) : (
              <p className="text-xs text-on-surface-variant">Aucune ligne refusée.</p>
            )}
          </div>
        )}

        <div className="mt-6 bg-surface-container-high rounded-xl p-4">
          <h4 className="text-xs font-bold text-primary mb-2">Format attendu</h4>
          {importFormat === 'pdf' ? (
            <p className="text-[11px] text-on-surface-variant">
              Le PDF est analysé pour en extraire les créneaux. Rien n'est enregistré avant votre validation, ligne par ligne.
            </p>
          ) : (
            <>
              <p className="text-[11px] text-on-surface-variant font-mono">
                filiere_code, niveau, annee_libelle, semestre, ue_code, ec_code, jour,
                heure_debut, heure_fin, salle_code, type_cours
              </p>
              <p className="text-[11px] text-on-surface-variant mt-1">
                Facultatives : <span className="font-mono">groupe, enseignant, valide_du, valide_au</span>.
              </p>
              <p className="text-[11px] text-on-surface-variant mt-2">
                Mêmes règles que la grille : conflits de salle, de promotion (cours communs et groupes compris) et d'enseignant.
              </p>
              <div className="mt-3">
                <CsvTemplateDownload types={['edt']} avecColonnes={false} />
              </div>
            </>
          )}
        </div>
      </Modal>
    </div>
  );
}
