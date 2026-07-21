import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiChevronRight, FiCheck, FiAlertCircle, FiFilter, FiPlus, FiSave, FiArrowRight } from 'react-icons/fi';
import { MdCloudDone, MdAutoAwesome } from 'react-icons/md';
import api from '../../api/axios';

export default function CourseValidationPage() {
  const navigate = useNavigate();
  const [courses, setCourses] = useState([]);
  const [sourceType, setSourceType] = useState('schedule'); // 'schedule' or 'dedicated'
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState('');
  const [analysisMeta, setAnalysisMeta] = useState({ filename: 'Document importé', score: 0 });

  useEffect(() => {
    // Chercher d'abord une analyse dédiée aux cours
    const dedicated = sessionStorage.getItem('import_courses_analysis');
    const schedule = sessionStorage.getItem('import_analysis');

    if (dedicated) {
      try {
        const parsed = JSON.parse(dedicated);
        // Le job stocke analyse.result = gemini.data = { ues: [...] }
        // L'API retourne { analysis_id, type, status, result: { ues: [...] }, ... }
        const root = parsed?.result || parsed?.data || parsed;
        const uesData = root?.ues || root?.data?.ues || [];

        if (Array.isArray(uesData) && uesData.length > 0) {
          // Aplatir UEs + ECs en lignes de cours
          const flat = [];
          uesData.forEach((ue) => {
            flat.push({
              id: flat.length,
              code: ue.code || '',
              intitule: ue.intitule || '',
              semestre: `S${ue.semestre || 1}`,
              credits: ue.credits?.toString() || '',
              edited: false,
              isUe: true,
            });
            (ue.ecs || []).forEach((ec) => {
              flat.push({
                id: flat.length,
                code: ec.code || '',
                intitule: ec.intitule || '',
                semestre: `S${ue.semestre || 1}`,
                credits: (ec.volume_horaire ? Math.round(ec.volume_horaire / 10) : 3).toString(),
                edited: false,
                isUe: false,
              });
            });
          });

          setCourses(flat);
          setSourceType('dedicated');
          setAnalysisMeta({
            filename: parsed?.metadata?.filename || root?.metadata?.filename || 'Catalogue cours.pdf',
            score: parsed?.score_de_confiance ?? root?.score_de_confiance ?? 0.95,
            total: uesData.length,
          });
          return;
        }
      } catch { /* fallback to schedule */ }
    }

    // Fallback : analyse d'emploi du temps (cours dérivés des événements)
    if (schedule) {
      try {
        const parsed = JSON.parse(schedule);
        const root = parsed?.data || parsed;
        const coursesData = root?.data?.courses || root?.courses || [];

        if (Array.isArray(coursesData) && coursesData.length > 0) {
          setCourses(coursesData.map((c, i) => ({
            id: i,
            code: c.code || '',
            intitule: c.intitule || '',
            semestre: c.semestre || '',
            credits: c.credits?.toString() || '',
            edited: false,
            isUe: true,
          })));
          setSourceType('schedule');
          setAnalysisMeta({
            filename: root?.metadata?.filename || 'Emploi du temps.pdf',
            score: root?.score_de_confiance ?? 0.9,
            total: coursesData.length,
          });
          return;
        }
      } catch { /* fallback to empty */ }
    }

    // Aucune donnée
    setCourses([{ id: 0, code: '', intitule: '', semestre: '', credits: '', edited: true, isUe: true }]);
  }, [navigate]);

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
    if (course.code && course.intitule && course.credits) {
      return { label: 'Prêt', color: 'bg-secondary-container text-on-secondary-container' };
    }
    return { label: 'Action requis', color: 'bg-tertiary-fixed text-on-tertiary-fixed' };
  };

  const readyCount = courses.filter((c) => c.code && c.intitule && c.credits).length;
  const alertCount = courses.filter((c) => !c.code || !c.intitule || !c.credits).length;
  const confidence = Math.round(analysisMeta.score * 100);

  const handleSave = async () => {
    const validCourses = courses.filter((c) => c.code && c.intitule && c.credits);
    if (validCourses.length === 0) {
      setError('Aucun cours valide à importer. Remplissez au moins le code, l\'intitulé et les crédits.');
      return;
    }

    setSaving(true);
    setError('');

    try {
      const payload = {
        ues: validCourses.map((c) => ({
          code: c.code.toUpperCase(),
          intitule: c.intitule,
          filiere_id: 1,
          annee_id: 1,
          semestre: parseInt(c.semestre.toString().replace(/[^0-9]/g, ''), 10) || 1,
          volume_horaire: Math.max(parseInt(c.credits, 10) * 10, 30),
          ecs: [],
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
          <p className="text-on-surface-variant mb-2">{readyCount} cours créés avec succès.</p>
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
              <h2 className="text-lg font-bold text-primary">Données extraites ({courses.length})</h2>
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
                    <th className="px-4 py-4">Code</th>
                    <th className="px-4 py-4">Intitulé</th>
                    <th className="px-4 py-4">Semestre</th>
                    <th className="px-4 py-4">Crédits</th>
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
                          <input
                            type="text"
                            value={course.code}
                            onChange={(e) => updateCourse(course.id, 'code', e.target.value)}
                            className={`bg-transparent border-b py-1 text-sm font-medium focus:ring-0 w-24 font-mono ${course.code ? 'border-transparent text-primary' : 'border-dashed border-outline-variant text-on-surface-variant'}`}
                            placeholder={course.isUe ? 'UE-INF-301' : 'INF3011'}
                          />
                        </td>
                        <td className="px-4 py-4">
                          <input
                            type="text"
                            value={course.intitule}
                            onChange={(e) => updateCourse(course.id, 'intitule', e.target.value)}
                            className={`bg-transparent border-b py-1 text-sm focus:ring-0 w-full ${course.intitule ? 'border-transparent' : 'border-dashed border-outline-variant'}`}
                            placeholder="Nom du cours..."
                          />
                        </td>
                        <td className="px-4 py-4">
                          <select
                            value={course.semestre.toString().replace(/[^0-9]/g, '')}
                            onChange={(e) => updateCourse(course.id, 'semestre', `S${e.target.value}`)}
                            className="bg-transparent text-sm border-none focus:ring-0 py-1">
                            <option value="">Semestre</option>
                            {[1, 2, 3, 4, 5, 6].map((s) => (
                              <option key={s} value={s}>S{s}</option>
                            ))}
                          </select>
                        </td>
                        <td className="px-4 py-4">
                          <input
                            type="number"
                            value={course.credits}
                            onChange={(e) => updateCourse(course.id, 'credits', e.target.value)}
                            className="bg-transparent border-b py-1 text-sm font-mono focus:ring-0 w-16 text-center"
                            placeholder="0"
                            min="1"
                            max="30"
                          />
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

          <div className="flex flex-col gap-3">
            <button onClick={handleSave} disabled={saving || readyCount === 0}
              className="w-full py-4 rounded-xl bg-gradient-to-br from-primary to-primary-container text-white font-bold text-sm shadow-xl shadow-primary/20 hover:scale-[1.02] transition-transform disabled:opacity-50 disabled:hover:scale-100 flex items-center justify-center gap-2">
              <MdCloudDone className="text-[20px]" />
              {saving ? 'Enregistrement...' : 'Valider et enregistrer'}
            </button>
            <button onClick={() => navigate('/import')}
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
