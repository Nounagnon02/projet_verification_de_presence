import { useState, useEffect } from 'react';
import { FiLoader } from 'react-icons/fi';
import ProgressBar from '../../components/charts/ProgressBar';
import Badge from '../../components/ui/Badge';
import api from '../../api/axios';

export default function ProgramComparison() {
  const [programs, setPrograms] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const { data: res } = await api.get('/admin/filieres');
        const filieres = res.data || res;
        if (Array.isArray(filieres)) {
          const sorted = [...filieres]
            .sort((a, b) => (b.etudiants_count || 0) - (a.etudiants_count || 0))
            .map((f, i) => ({
              name: f.intitule || f.code,
              code: f.code,
              rate: 85,
              rank: i + 1,
            }));
          setPrograms(sorted);
        }
      } catch {
        setPrograms([]);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  if (loading) return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;

  return (
    <div>
      <h1 className="text-2xl font-bold font-headline text-primary mb-2">Comparaison Filières</h1>
      <p className="text-sm text-on-surface-variant mb-8">Classement des filières</p>

      <div className="bg-surface-container-lowest rounded-xxl shadow-sm overflow-hidden mb-8">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b text-left text-xs text-on-surface-variant uppercase tracking-wider">
              <th className="p-4 font-semibold">Rang</th>
              <th className="p-4 font-semibold">Filière</th>
              <th className="p-4 font-semibold text-right">Code</th>
              <th className="p-4 w-1/3"></th>
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
                <td className="p-4 text-right font-mono text-xs">{p.code}</td>
                <td className="p-4">
                  <ProgressBar value={p.rate} size="sm" showValue={false} color={p.rate >= 90 ? 'success' : p.rate >= 85 ? 'warning' : 'error'} />
                </td>
              </tr>
            )) : (
              <tr><td colSpan={4} className="p-8 text-center text-on-surface-variant">Aucune filière</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
