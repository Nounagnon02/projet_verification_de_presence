import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiChevronRight, FiCheck, FiAlertCircle, FiPlus, FiArrowRight, FiLoader } from 'react-icons/fi';
import { MdCloudDone } from 'react-icons/md';
import api from '../../api/axios';
import useFiltresAcademiques from '../../hooks/useFiltresAcademiques';
import BandeauAnneeClose from '../../components/ui/BandeauAnneeClose';
import { VOLUMES } from '../../utils/typesSeance';

const heures = (valeur) => {
  const n = parseInt(String(valeur ?? ''), 10);
  return Number.isNaN(n) || n < 0 ? 0 : n;
};

/**
 * Une ligne est prête : code et intitulé, et des heures par type pour un EC.
 * Un total seul ne suffit plus : sur la maquette réelle, l'IA y mettait le
 * CTT (125 h pour 5 crédits, dont 75 h de travail personnel), et l'EC,
 * enregistré « à ventiler », ne se terminait jamais.
 */
const estComplete = (ligne) => Boolean(
  ligne.code && ligne.intitule
  && (ligne.isUe || VOLUMES.some(([champ]) => heures(ligne[champ]) > 0))
);

const LIGNE_VIDE = { id: 0, code: '', intitule: '', semestre: '', credits: '', edited: true, isUe: true };

/**
 * Lit l'analyse déposée en session par l'écran d'import et la traduit en lignes
 * de cours éditables.
 *
 * Fonction pure au niveau module : la lecture de sessionStorage est synchrone,
 * elle sert donc de valeur initiale d'état. Effectuée dans un effet, elle
 * imposait un premier rendu avec une liste vide, aussitôt remplacé.
 *
 * Deux sources possibles, par ordre de préférence : une analyse dédiée aux
 * cours, sinon une analyse d'emploi du temps dont on dérive les cours.
 */
function lireCoursEnSession() {
  const dedie = sessionStorage.getItem('import_courses_analysis');

  if (dedie) {
    try {
      const parsed = JSON.parse(dedie);
      // Le job stocke analyse.result = gemini.data = { ues: [...] } ; l'API
      // renvoie { analysis_id, type, status, result: { ues: [...] }, ... }.
      const root = parsed?.result || parsed?.data || parsed;
      const uesData = root?.ues || root?.data?.ues || [];

      if (Array.isArray(uesData) && uesData.length > 0) {
        // Aplatir UEs et ECs en lignes de cours.
        const lignes = [];

        uesData.forEach((ue) => {
          lignes.push({
            id: lignes.length,
            code: ue.code || '',
            intitule: ue.intitule || '',
            semestre: `S${ue.semestre || 1}`,
            credits: ue.credits?.toString() || '',
            edited: false,
            isUe: true,
          });

          (ue.ecs || []).forEach((ec) => {
            lignes.push({
              id: lignes.length,
              code: ec.code || '',
              intitule: ec.intitule || '',
              semestre: `S${ue.semestre || 1}`,
              credits: '',
              // Heures en présentiel par type ; jamais le TPE ni le CTT. Les
              // crédits d'un EC n'existent pas : on en fabriquait à partir du
              // volume, puis un volume à partir de ces crédits.
              volume_cm: heures(ec.cm ?? ec.volume_cm),
              volume_td: heures(ec.td ?? ec.volume_td),
              volume_tp: heures(ec.tp ?? ec.volume_tp),
              volume_td_tp: heures(ec.td_tp ?? ec.volume_td_tp),
              // Pour information : ce que le document donne en plus, jamais planifié.
              volume_horaire: heures(ec.volume_horaire),
              tpe: heures(ec.tpe),
              ctt: heures(ec.ctt),
              edited: false,
              isUe: false,
            });
          });
        });

        return {
          courses: lignes,
          sourceType: 'dedicated',
          meta: {
            filename: parsed?.metadata?.filename || root?.metadata?.filename || 'Catalogue cours.pdf',
            score: parsed?.score_de_confiance ?? root?.score_de_confiance ?? 0.95,
            total: uesData.length,
          },
        };
      }
    } catch { /* on se rabat sur l'emploi du temps */ }
  }

  const edt = sessionStorage.getItem('import_analysis');

  if (edt) {
    try {
      const parsed = JSON.parse(edt);
      const root = parsed?.data || parsed;
      const coursesData = root?.data?.courses || root?.courses || [];

      if (Array.isArray(coursesData) && coursesData.length > 0) {
        return {
          courses: coursesData.map((c, i) => ({
            id: i,
            code: c.code || '',
            intitule: c.intitule || '',
            semestre: c.semestre || '',
            credits: c.credits?.toString() || '',
            edited: false,
            isUe: true,
          })),
          sourceType: 'schedule',
          meta: {
            filename: root?.metadata?.filename || 'Emploi du temps.pdf',
            score: root?.score_de_confiance ?? 0.9,
            total: coursesData.length,
          },
        };
      }
    } catch { /* on se rabat sur une ligne vide */ }
  }

  // Aucune donnée exploitable : une ligne vierge à remplir à la main.
  return { courses: [LIGNE_VIDE], sourceType: 'schedule', meta: { filename: 'Document importé', score: 0 } };
}

/**
 * Le semestre determine le niveau : S3 est en L2, S8 en M1.
 *
 * Meme table que App\Services\SemesterService cote serveur, qui refuse
 * desormais une UE dont le semestre est etranger au niveau de la filiere. La
 * reprendre ici permet de guider le choix AVANT l'envoi, plutot que de laisser
 * l'utilisateur decouvrir le refus apres coup.
 */
const NIVEAU_PAR_SEMESTRE = {
  1: 'L1', 2: 'L1',
  3: 'L2', 4: 'L2',
  5: 'L3', 6: 'L3',
  7: 'M1', 8: 'M1',
  9: 'M2', 10: 'M2',
};

function niveauxDesLignes(lignes) {
  const niveaux = lignes
    .filter((l) => l.isUe)
    .map((l) => NIVEAU_PAR_SEMESTRE[parseInt(String(l.semestre).replace(/[^0-9]/g, ''), 10)])
    .filter(Boolean);

  return [...new Set(niveaux)];
}

/**
 * Regroupe les lignes a plat en UEs portant leurs ECs.
 *
 * L'ecran affiche une ligne par UE ET par EC, le drapeau « isUe » les
 * distinguant. Ce drapeau etait calcule puis ignore a l'enregistrement : chaque
 * ligne partait en UE. Un catalogue de seize UE et vingt-deux ECs creait donc
 * trente-huit UE, aucune ne portant d'EC.
 *
 * Chaque ligne d'UE ouvre un groupe ; les lignes d'EC qui suivent s'y
 * rattachent, jusqu'a la prochaine UE. Les ECs precedant toute UE sont ignores :
 * ils n'ont pas de parent, et les inventer un serait pire que de les omettre.
 *
 * Chaque EC porte ses heures par type (CM, TD, TP, reserve TP/TD) ; le serveur
 * en deduit le volume de l'UE. Les credits restent des credits : on fabriquait
 * un volume a partir d'eux (« credits x 10 »).
 */
function grouperUesEtEcs(lignes) {
  const nombre = (valeur, defaut = 0) => {
    const n = parseInt(String(valeur ?? '').replace(/[^0-9]/g, ''), 10);
    return Number.isNaN(n) ? defaut : n;
  };

  const groupes = [];

  lignes.forEach((ligne) => {
    if (ligne.isUe) {
      groupes.push({
        code: ligne.code,
        intitule: ligne.intitule,
        semestre: nombre(ligne.semestre, 1) || 1,
        credits: ligne.credits === '' || ligne.credits === undefined ? null : nombre(ligne.credits, 0),
        ecs: [],
      });
      return;
    }

    const parent = groupes[groupes.length - 1];
    if (!parent) return;

    parent.ecs.push({
      code: ligne.code,
      intitule: ligne.intitule,
      volumes: Object.fromEntries(VOLUMES.map(([champ]) => [champ, heures(ligne[champ])])),
    });
  });

  return groupes;
}

export default function CourseValidationPage() {
  const navigate = useNavigate();

  // Lecture une seule fois, à l'initialisation des états.
  const [initial] = useState(lireCoursEnSession);
  const [courses, setCourses] = useState(() => initial.courses);
  const [sourceType] = useState(() => initial.sourceType);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');
  const [analysisMeta] = useState(() => initial.meta);

  // Destination de l'import. Elle etait ecrite en dur — filiere_id: 1,
  // annee_id: 1 — si bien que TOUTE analyse atterrissait dans la premiere
  // filiere et la premiere annee de la base.
  const filtres = useFiltresAcademiques({ preselectionnerAnneeActive: true });

  // Niveaux impliques par les semestres extraits. Un catalogue de S3 designe
  // L2 : proposer L1 n'aurait aucun sens, et le serveur le refuserait.
  const niveauxAttendus = niveauxDesLignes(courses);

  const filieresCompatibles = niveauxAttendus.length > 0
    ? filtres.filieres.filter((f) => niveauxAttendus.includes(f.niveau))
    : filtres.filieres;

  const updateCourse = (id, field, value) => {
    setCourses((prev) => prev.map((c) => (c.id === id ? { ...c, [field]: value, edited: true } : c)));
  };

  const addRow = () => {
    const maxId = courses.reduce((max, c) => Math.max(max, c.id), 0);
    setCourses((prev) => [...prev, { id: maxId + 1, code: '', intitule: '', semestre: '', credits: '', edited: true, isUe: true }]);
  };

  const removeRow = (id) => {
    setCourses((prev) => prev.filter((c) => c.id !== id));
  };

  const statusInfo = (course) => {
    if (!course.intitule && !course.code) {
      return { label: 'Vide', color: 'bg-surface-container-high text-on-surface-variant' };
    }
    if (estComplete(course)) {
      return { label: 'Prêt', color: 'bg-secondary-container text-on-secondary-container' };
    }
    if (!course.isUe && course.code && course.intitule) {
      return { label: 'Heures à saisir', color: 'bg-warning-container text-on-surface' };
    }
    return { label: 'Action requis', color: 'bg-tertiary-fixed text-on-tertiary-fixed' };
  };

  const readyCount = courses.filter(estComplete).length;
  const alertCount = courses.filter((c) => !estComplete(c)).length;
  const confidence = Math.round(analysisMeta.score * 100);

  const handleSave = async () => {
    const validCourses = courses.filter(estComplete);
    if (validCourses.length === 0) {
      setError('Aucun cours valide à importer. Remplissez au moins le code, l\'intitulé et les crédits.');
      return;
    }

    if (!filtres.filiere || !filtres.annee) {
      setError('Choisissez la filière et l\'année académique de destination avant de valider.');
      return;
    }

    const groupes = grouperUesEtEcs(validCourses);

    if (groupes.length === 0) {
      setError('Aucune UE à importer : la première ligne doit être une UE, pas un EC.');
      return;
    }

    setSaving(true);
    setError('');

    try {
      const payload = {
        // Les ECs repartent SOUS leur UE. Ils etaient jetes — « ecs: [] » — et
        // chaque ligne, UE comme EC, etait envoyee en tant qu'UE : un import de
        // seize UE et vingt-deux ECs creait trente-huit UE sans aucun EC.
        //
        // La filiere et l'annee etaient ecrites en dur a 1. Toute analyse
        // atterrissait donc dans la premiere filiere et la premiere annee de la
        // base, quel que soit le document — des UE de S3 se sont ainsi
        // retrouvees en L1.
        ues: groupes.map((g) => ({
          code: g.code.toUpperCase(),
          intitule: g.intitule,
          filiere_id: Number(filtres.filiere),
          annee_id: Number(filtres.annee),
          semestre: g.semestre,
          credits: g.credits,
          // Heures par type, et elles seules : un total lu dans le document
          // (souvent le CTT) ne s'enregistre jamais comme volume.
          ecs: g.ecs.map((e) => ({
            code: e.code.toUpperCase(),
            intitule: e.intitule,
            ...e.volumes,
          })),
        })),
      };

      const { data: res } = await api.post('/admin/import/validate-courses', payload);
      if (res.success) {
        setSaved(true);
        sessionStorage.setItem('import_courses_result', JSON.stringify(res));
      } else {
        setError(res.message || 'Erreur lors de la sauvegarde.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur de connexion au serveur.');
    } finally {
      setSaving(false);
    }
  };

  if (saved) {
    return (
      <div className="max-w-lg mx-auto py-12">
        <div className="bg-surface-container-lowest rounded-xl p-8 shadow-sm text-center">
          <div className="w-16 h-16 bg-secondary/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <FiCheck className="text-secondary" size={32} />
          </div>
          <h1 className="text-2xl font-bold font-headline text-primary mb-3">Cours importés !</h1>
          <p className="text-on-surface-variant mb-2">
            {courses.filter((c) => c.isUe && estComplete(c)).length} UE et {courses.filter((c) => !c.isUe && estComplete(c)).length} EC enregistrés.
            {alertCount > 0 && ` ${alertCount} ligne(s) laissée(s) de côté, faute d'heures par type.`}
          </p>
          <div className="flex flex-col sm:flex-row gap-3 justify-center mt-6">
            {sourceType === 'schedule' && (
              <button onClick={() => navigate('/import/validate-schedule')}
                className="bg-primary text-white px-8 py-3 rounded-xl font-semibold hover:opacity-90 transition-all flex items-center justify-center gap-2">
                Valider l'emploi du temps <FiArrowRight />
              </button>
            )}
            <button onClick={() => navigate('/courses')}
              className="bg-surface-container-high text-on-surface px-8 py-3 rounded-xl font-semibold hover:bg-surface-container transition-all">
              Voir les cours
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div>
      {/* Breadcrumb & Header */}
      <div className="mb-10">
        <nav className="flex items-center gap-2 text-xs text-outline mb-3">
          <span>Gestion des cours</span>
          <FiChevronRight className="text-[14px]" />
          <span>Importation IA</span>
          <FiChevronRight className="text-[14px]" />
          <span className="text-primary font-semibold">Validation des données</span>
        </nav>
        <div className="flex justify-between items-end gap-4 flex-wrap">
          <div>
            <h1 className="text-3xl font-extrabold text-primary tracking-tight">Valider les cours extraits</h1>
            <p className="text-on-surface-variant mt-2 max-w-2xl">
              {sourceType === 'dedicated'
                ? 'Revisez les Unités d\'Enseignement et Éléments Constitutifs extraits par l\'IA.'
                : 'Les cours ci-dessous ont été dérivés de l\'analyse de l\'emploi du temps. Modifiez si nécessaire.'}
            </p>
          </div>
          {/* Stepper */}
          <div className="hidden md:flex items-center gap-4 bg-surface-container-low px-6 py-3 rounded-2xl shadow-sm">
            <div className="flex items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-secondary text-white flex items-center justify-center text-[10px]">
                <FiCheck className="text-[16px]" />
              </span>
              <span className="text-xs font-semibold text-secondary">Upload</span>
            </div>
            <div className="w-8 h-px bg-outline-variant"></div>
            <div className="flex items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-secondary text-white flex items-center justify-center text-[10px]">
                <FiCheck className="text-[16px]" />
              </span>
              <span className="text-xs font-semibold text-secondary">Analyse</span>
            </div>
            <div className="w-8 h-px bg-outline-variant"></div>
            <div className="flex items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center text-[12px] font-bold">3</span>
              <span className="text-xs font-bold text-primary">Validation</span>
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-12 gap-8">
        {/* Main Table */}
        <div className="col-span-12 lg:col-span-9">
          <div className="bg-surface-container-lowest rounded-[24px] overflow-hidden shadow-sm">
            <div className="px-8 py-6 flex justify-between items-center border-b border-surface-container-high">
              <h2 className="text-lg font-bold text-primary">
                Données extraites — {courses.filter((c) => c.isUe).length} UE,{' '}
                {courses.filter((c) => !c.isUe).length} EC
              </h2>
              <div className="flex gap-2">
                <span className="text-[10px] font-medium text-on-surface-variant bg-surface-container-low px-3 py-1.5 rounded-lg">
                  {sourceType === 'dedicated' ? 'Analyse dédiée' : 'Dérivé de l\'emploi du temps'}
                </span>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-surface-container-low/50 text-[11px] uppercase tracking-wider text-outline font-bold">
                    <th className="px-8 py-4 w-12">#</th>
                    {/* Rien ne distinguait une UE d'un EC : trente-huit lignes
                        melant les deux avaient l'air d'un catalogue de
                        trente-huit UE. */}
                    <th className="px-4 py-4 w-16">Type</th>
                    <th className="px-4 py-4">Code</th>
                    <th className="px-4 py-4">Intitulé</th>
                    <th className="px-4 py-4">Semestre</th>
                    <th className="px-4 py-4">Crédits / heures</th>
                    <th className="px-4 py-4">Statut</th>
                    <th className="px-8 py-4 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y-0">
                  {courses.map((course) => {
                    const status = statusInfo(course);
                    return (
                      <tr key={course.id} className={`hover:bg-surface-container-low group transition-colors ${course.isUe ? '' : 'bg-surface/30'}`}>
                        <td className="px-8 py-4 text-xs text-on-surface-variant font-mono">{course.id + 1}</td>
                        <td className="px-4 py-4">
                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${course.isUe
                            ? 'bg-primary/10 text-primary'
                            : 'bg-surface-container-high text-on-surface-variant'}`}>
                            {course.isUe ? 'UE' : 'EC'}
                          </span>
                        </td>
                        <td className="px-4 py-4">
                          <input
                            type="text"
                            value={course.code}
                            onChange={(e) => updateCourse(course.id, 'code', e.target.value)}
                            className={`bg-transparent border-b py-1 text-sm font-medium focus:ring-0 w-24 font-mono ${course.code ? 'border-transparent text-primary' : 'border-dashed border-outline-variant text-on-surface-variant'}`}
                            placeholder={course.isUe ? 'UE-INF-301' : 'INF3011'}
                            aria-label={`Code de la ligne ${course.id + 1}`}
                          />
                        </td>
                        <td className="px-4 py-4">
                          <input
                            type="text"
                            value={course.intitule}
                            onChange={(e) => updateCourse(course.id, 'intitule', e.target.value)}
                            className={`bg-transparent border-b py-1 text-sm focus:ring-0 w-full ${course.intitule ? 'border-transparent' : 'border-dashed border-outline-variant'}`}
                            placeholder="Nom du cours..."
                            aria-label={`Intitulé de la ligne ${course.id + 1}`}
                          />
                        </td>
                        <td className="px-4 py-4">
                          <select
                            value={course.semestre.toString().replace(/[^0-9]/g, '')}
                            onChange={(e) => updateCourse(course.id, 'semestre', `S${e.target.value}`)}
                            aria-label={`Semestre de la ligne ${course.id + 1}`}
                            className="bg-transparent text-sm border-none focus:ring-0 py-1">
                            <option value="">Semestre</option>
                            {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((s) => (
                              <option key={s} value={s}>S{s}</option>
                            ))}
                          </select>
                        </td>
                        <td className="px-4 py-4">
                          {course.isUe ? (
                            <input
                              type="number"
                              value={course.credits}
                              onChange={(e) => updateCourse(course.id, 'credits', e.target.value)}
                              className="bg-transparent border-b py-1 text-sm font-mono focus:ring-0 w-16 text-center"
                              placeholder="—"
                              min="0"
                              max="60"
                              aria-label={`Crédits de ${course.code || "l'UE"}`}
                            />
                          ) : (
                            <div className="space-y-1">
                            <div className="flex gap-1.5">
                              {VOLUMES.map(([champ, libelle]) => (
                                <label key={champ} className="flex flex-col items-center text-[9px] font-semibold text-on-surface-variant">
                                  {libelle}
                                  <input
                                    type="number"
                                    min="0"
                                    value={course[champ] ?? 0}
                                    onChange={(e) => updateCourse(course.id, champ, e.target.value)}
                                    className="bg-transparent border-b py-1 text-sm font-mono focus:ring-0 w-12 text-center"
                                    aria-label={`${libelle} de ${course.code || "l'EC"}`}
                                  />
                                </label>
                              ))}
                            </div>
                            {(course.volume_horaire > 0 || course.tpe > 0 || course.ctt > 0) && (
                              <p className="text-[10px] text-on-surface-variant max-w-[14rem]">
                                Lu dans le document : {[
                                  course.volume_horaire > 0 && `total ${course.volume_horaire} h`,
                                  course.tpe > 0 && `TPE ${course.tpe} h`,
                                  course.ctt > 0 && `CTT ${course.ctt} h`,
                                ].filter(Boolean).join(' · ')}. Seules les heures de cours, par type, se planifient.
                              </p>
                            )}
                            </div>
                          )}
                        </td>
                        <td className="px-4 py-4">
                          <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold ${status.color}`}>
                            <span className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: 'currentColor' }}></span>
                            {status.label}
                          </span>
                        </td>
                        <td className="px-8 py-4 text-right">
                          <button
                            onClick={() => removeRow(course.id)}
                            className="text-outline hover:text-error transition-colors p-1 opacity-0 group-hover:opacity-100"
                            title="Supprimer">
                            <FiAlertCircle className="text-[16px]" />
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                  <tr className="hover:bg-surface-container-low group transition-colors border-t border-dashed border-outline-variant">
                    <td className="px-8 py-4"></td>
                    <td colSpan={6} className="px-4 py-4">
                      <button onClick={addRow} className="flex items-center gap-2 text-primary text-xs font-bold hover:underline">
                        <FiPlus className="text-[18px]" /> Ajouter une ligne manuellement
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {/* Sidebar */}
        <div className="col-span-12 lg:col-span-3 space-y-6">
          <div className="bg-primary-container p-6 rounded-[24px] text-white">
            <h3 className="text-sm font-bold opacity-80 mb-4">Aperçu de l'import</h3>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-xs">Éléments détectés</span>
                <span className="font-mono font-bold">{courses.length}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-xs">Prêts</span>
                <span className="font-mono font-bold text-secondary-fixed">{readyCount}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-xs">Alertes</span>
                <span className="font-mono font-bold text-tertiary-fixed">{alertCount}</span>
              </div>
              <div className="pt-4 border-t border-white/10">
                <div className="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                  <div className="bg-secondary-fixed h-full rounded-full transition-all" style={{ width: `${confidence}%` }}></div>
                </div>
                <p className="text-[10px] mt-2 opacity-60">Confiance IA : {confidence}%</p>
              </div>
            </div>
          </div>

          <div className="bg-surface-container-lowest p-6 rounded-[24px] shadow-sm">
            <h3 className="text-sm font-bold text-primary mb-4">Document source</h3>
            <div className="aspect-[3/4] bg-surface-container-low rounded-xl overflow-hidden relative flex items-center justify-center">
              <div className="text-center p-4">
                <p className="text-xs text-on-surface-variant font-medium">{analysisMeta.filename}</p>
                <p className="text-[10px] text-on-surface-variant/60 mt-1">Analysé par IA Gemini</p>
              </div>
            </div>
          </div>

          {/* Destination de l'import.
              Elle etait ecrite en dur : filiere_id: 1, annee_id: 1. Toute
              analyse atterrissait donc dans la premiere filiere et la premiere
              annee de la base, quel que soit le document. */}
          <div className="bg-surface-container-lowest p-6 rounded-[24px] shadow-sm space-y-4">
            <h3 className="text-sm font-bold text-primary">Destination</h3>

            <div className="space-y-1">
              <label htmlFor="import-annee" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Année académique</label>
              <select id="import-annee" value={filtres.annee} onChange={(e) => filtres.setAnnee(e.target.value)} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50">
                <option value="">Choisir une année…</option>
                {filtres.annees.map((a) => (
                  <option key={a.id} value={a.id}>{a.libelle}{a.active ? ' (active)' : ''}</option>
                ))}
              </select>
            </div>

            {filtres.anneeClose && <BandeauAnneeClose annee={filtres.anneeChoisie} />}

            <div className="space-y-1">
              <label htmlFor="import-filiere" className="text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">Filière</label>
              <select id="import-filiere" value={filtres.filiere} onChange={(e) => filtres.setFiliere(e.target.value)}
                disabled={!filtres.annee} className="w-full px-3 py-2 bg-surface-container-high rounded-lg text-sm border border-outline-variant/20 focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-50">
                <option value="">Choisir une filière…</option>
                {filieresCompatibles.map((f) => (
                  <option key={f.id} value={f.id}>{f.code} — {f.intitule}</option>
                ))}
              </select>
            </div>

            {niveauxAttendus.length > 0 && (
              <p className="text-xs text-on-surface-variant">
                Les semestres extraits désignent {niveauxAttendus.join(' et ')} : seules
                les filières de ce niveau sont proposées. Une UE de S3 rangée en L1
                serait refusée.
              </p>
            )}

            {filtres.annee && filieresCompatibles.length === 0 && (
              <p className="text-xs text-error">
                Aucune filière de niveau {niveauxAttendus.join(' ou ')} pour cette année.
                Créez-la, ou corrigez les semestres ci-contre.
              </p>
            )}
          </div>

          <div className="flex flex-col gap-3">
            <button onClick={handleSave} disabled={saving || readyCount === 0 || !filtres.filiere || !filtres.annee || filtres.anneeClose}
              className="w-full py-4 rounded-xl bg-gradient-to-br from-primary to-primary-container text-white font-bold text-sm shadow-xl shadow-primary/20 hover:scale-[1.02] transition-transform disabled:opacity-50 disabled:hover:scale-100 flex items-center justify-center gap-2">
              <MdCloudDone className="text-[20px]" />
              {saving ? <><FiLoader className="animate-spin" /> Enregistrement...</> : 'Valider et enregistrer'}
            </button>
            <button onClick={() => navigate('/courses')}
              className="w-full py-4 rounded-xl bg-surface-container-highest text-on-surface font-bold text-sm hover:bg-surface-container-high transition-colors">
              Annuler
            </button>
          </div>

          {error && (
            <div className="flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-xs">
              <FiAlertCircle /> {error}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
