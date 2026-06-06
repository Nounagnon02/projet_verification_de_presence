import { useState, useEffect } from 'react';
import { FiLoader, FiAlertCircle } from 'react-icons/fi';
import BarChart from '../../components/charts/BarChart';
import api from '../../api/axios';

export default function SemesterComparison() {
  const [filieres, setFilieres] = useState([]);
  const [selectedFiliere, setSelectedFiliere] = useState('');
  const [annees, setAnnees] = useState([]);
  const [selectedAnnee, setSelectedAnnee] = useState('');
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Charger la liste des filières et années au montage
  useEffect(() => {
    const init = async () => {
      try {
        const [filRes, anneeRes] = await Promise.all([
          api.get('/admin/filieres'),
          api.get('/admin/annees-academiques'),
        ]);

        const filList = filRes.data?.data || filRes.data || [];
        const anneeList = anneeRes.data?.data || anneeRes.data || [];

        if (Array.isArray(filList)) setFilieres(filList);
        if (Array.isArray(anneeList)) {
          setAnnees(anneeList);
          if (anneeList.length > 0) {
            // Sélectionner l'année active par défaut
            const active = anneeList.find(y => y.active);
            setSelectedAnnee(String(active?.id || anneeList[anneeList.length - 1]?.id || ''));
          }
        }
      } catch {
        setError('Impossible de charger les filtres.');
      } finally {
        setLoading(false);
      }
    };
    init();
  }, []);

  // Charger les données de comparaison
  useEffect(() => {
    if (!selectedFiliere || !selectedAnnee) {
      setData(null);
      return;
    }

    const fetchData = async () => {
      setLoading(true);
      setError('');
      try {
        const { data: res } = await api.get('/admin/reports/semester-comparison', {
          params: { filiere_id: selectedFiliere, annee_id: selectedAnnee },
        });
        const d = res.data || res;
        setData(d);
      } catch {
        setError('Impossible de charger les données de comparaison.');
        setData(null);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [selectedFiliere, selectedAnnee]);

  if (loading && !data) {
    return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;
  }

  const semestres = data?.semestres || [];
  // Préparer les données pour les graphiques
  const chartData = semestres.map(s => ({
    label: s.label,
    value: s.taux,
    presences: s.total_presences,
  }));

  return (
    <div>
      <h1 className="text-2xl font-bold font-headline text-primary mb-2">Comparaison Semestrielle</h1>
      <p className="text-sm text-on-surface-variant mb-8">Comparez les taux de présence entre semestres par filière</p>

      {/* Filtres */}
      <div className="flex flex-wrap gap-4 mb-8 bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
        <div className="flex-1 min-w-[200px]">
          <label className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant block mb-1.5">
            Filière
          </label>
          <select
            value={selectedFiliere}
            onChange={e => setSelectedFiliere(e.target.value)}
            className="w-full px-4 py-2.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-all text-on-surface focus:outline-none text-sm"
          >
            <option value="">Sélectionner une filière</option>
            {filieres.map(f => (
              <option key={f.id} value={f.id}>{f.code} — {f.intitule} ({f.niveau})</option>
            ))}
          </select>
        </div>
        <div className="flex-1 min-w-[200px]">
          <label className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant block mb-1.5">
            Année académique
          </label>
          <select
            value={selectedAnnee}
            onChange={e => setSelectedAnnee(e.target.value)}
            className="w-full px-4 py-2.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-all text-on-surface focus:outline-none text-sm"
          >
            <option value="">Sélectionner une année</option>
            {annees.map(a => (
              <option key={a.id} value={a.id}>{a.annee || a.libelle} {a.active ? '(active)' : ''}</option>
            ))}
          </select>
        </div>
      </div>

      {error && (
        <div className="flex items-start gap-2.5 p-4 bg-error/10 rounded-xl text-error border border-error/10 mb-5">
          <FiAlertCircle className="text-lg shrink-0 mt-0.5" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {!selectedFiliere || !selectedAnnee ? (
        <div className="text-center py-16 text-on-surface-variant">
          <p>Sélectionnez une filière et une année académique pour voir la comparaison.</p>
        </div>
      ) : chartData.length === 0 ? (
        <div className="text-center py-16 text-on-surface-variant">
          <p>Aucune donnée disponible pour cette filière et cette année.</p>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Graphique des semestres */}
            <div className="bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10">
              <h2 className="text-base font-bold font-headline text-primary mb-1">
                {data?.filiere?.niveau} — {data?.filiere?.intitule}
              </h2>
              <p className="text-xs text-on-surface-variant mb-4">Taux de présence par semestre</p>
              <BarChart data={chartData} bars="value" height={220} />
            </div>

            {/* Tableau détaillé */}
            <div className="bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10">
              <h2 className="text-base font-bold font-headline text-primary mb-4">Détail par semestre</h2>
              <div className="space-y-3">
                {semestres.map(s => (
                  <div key={s.semestre} className="flex items-center justify-between p-3 bg-surface-container-high rounded-xl">
                    <div>
                      <span className="font-bold text-primary">{s.label}</span>
                    </div>
                    <div className="text-right">
                      <span className={`font-bold text-lg ${s.taux >= 80 ? 'text-success' : s.taux >= 50 ? 'text-warning' : 'text-error'}`}>
                        {s.taux}%
                      </span>
                      <p className="text-xs text-on-surface-variant">{s.total_presences} présences</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
