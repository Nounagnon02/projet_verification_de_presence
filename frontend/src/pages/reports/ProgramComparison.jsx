import { useState, useEffect } from 'react';
import { FiLoader, FiAlertCircle } from 'react-icons/fi';
import ProgressBar from '../../components/charts/ProgressBar';
import Badge from '../../components/ui/Badge';
import api from '../../api/axios';

export default function ProgramComparison() {
  const [annees, setAnnees] = useState([]);
  const [selectedAnnee, setSelectedAnnee] = useState('');
  const [programs, setPrograms] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Charger les années disponibles
  useEffect(() => {
    const init = async () => {
      try {
        const { data: anneeRes } = await api.get('/admin/annees-academiques');
        const anneeList = anneeRes.data || anneeRes;
        if (Array.isArray(anneeList)) {
          setAnnees(anneeList);
          const active = anneeList.find(y => y.active);
          setSelectedAnnee(String(active?.id || anneeList[anneeList.length - 1]?.id || ''));
        }
      } catch {
        setError('Impossible de charger les années académiques.');
      }
    };
    init();
  }, []);

  // Charger les stats réelles des filières
  useEffect(() => {
    if (!selectedAnnee) return;

    const fetchData = async () => {
      setLoading(true);
      setError('');
      try {
        const { data: res } = await api.get('/admin/reports/filiere-stats', {
          params: { annee_id: selectedAnnee },
        });
        const list = res.data || res;
        if (Array.isArray(list)) {
          const sorted = list
            .sort((a, b) => (b.taux || 0) - (a.taux || 0))
            .map((f, i) => ({
              name: f.intitule || f.code,
              code: f.code,
              niveau: f.niveau || '',
              rate: f.taux || 0,
              presences: f.total_presences || 0,
              evenements: f.total_evenements || 0,
              students: f.etudiants_count || 0,
              rank: i + 1,
            }));
          setPrograms(sorted);
        } else {
          setPrograms([]);
        }
      } catch {
        setError('Impossible de charger les statistiques.');
        setPrograms([]);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [selectedAnnee]);

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary mb-1">Comparaison Filières</h1>
          <p className="text-sm text-on-surface-variant">Classement des filières par taux de présence</p>
        </div>

        {/* Filtre année */}
        <div className="w-full md:w-64">
          <select
            value={selectedAnnee}
            onChange={e => setSelectedAnnee(e.target.value)}
            className="w-full px-4 py-2.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-all text-on-surface focus:outline-none text-sm"
          >
            <option value="">Sélectionner une année</option>
            {annees.map(a => (
              <option key={a.id} value={a.id}>{a.annee || a.libelle}</option>
            ))}
          </select>
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>
      ) : error ? (
        <div className="flex items-start gap-2.5 p-4 bg-error/10 rounded-xl text-error border border-error/10">
          <FiAlertCircle className="text-lg shrink-0 mt-0.5" />
          <p className="text-sm">{error}</p>
        </div>
      ) : (
        <div className="bg-surface-container-lowest rounded-2xl shadow-sm overflow-hidden border border-outline-variant/10">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-xs text-on-surface-variant uppercase tracking-wider">
                <th className="p-4 font-semibold">Rang</th>
                <th className="p-4 font-semibold">Filière</th>
                <th className="p-4 font-semibold text-right">Niveau</th>
                <th className="p-4 font-semibold text-right">Taux</th>
                <th className="p-4 w-1/4"></th>
                <th className="p-4 font-semibold text-right">Présences</th>
              </tr>
            </thead>
            <tbody>
              {programs.length > 0 ? programs.map((p, i) => (
                <tr key={i} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                  <td className="p-4">
                    <Badge variant={i === 0 ? 'success' : i < 3 ? 'info' : 'neutral'}>
                      #{p.rank}
                    </Badge>
                  </td>
                  <td className="p-4 font-medium">{p.name}</td>
                  <td className="p-4 text-right font-mono text-xs text-on-surface-variant">{p.niveau}</td>
                  <td className="p-4 text-right font-bold" style={{ color: p.rate >= 80 ? '#2E7D32' : p.rate >= 50 ? '#F57F17' : '#C62828' }}>
                    {p.rate}%
                  </td>
                  <td className="p-4">
                    <ProgressBar value={p.rate} size="sm" showValue={false} color={p.rate >= 80 ? 'success' : p.rate >= 50 ? 'warning' : 'error'} />
                  </td>
                  <td className="p-4 text-right text-on-surface-variant">
                    {p.presences} <span className="text-xs">/ {p.evenements} séances</span>
                  </td>
                </tr>
              )) : (
                <tr><td colSpan={6} className="p-8 text-center text-on-surface-variant">Aucune filière trouvée</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
