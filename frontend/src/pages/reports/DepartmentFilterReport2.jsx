import { useState, useEffect } from 'react';
import { FiLoader } from 'react-icons/fi';
import Badge from '../../components/ui/Badge';
import ProgressBar from '../../components/charts/ProgressBar';
import api from '../../api/axios';

export default function DepartmentFilterReport2() {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const { data: res } = await api.get('/admin/filieres');
        const filieres = res.data || res;
        if (Array.isArray(filieres)) {
          setData(filieres.map(f => ({
            dep: f.intitule || f.code,
            code: f.code,
            rate: 85,
            change: '+0%',
            students: f.etudiants_count || 0,
            sessions: f.ues_count || 0,
          })));
        }
      } catch {
        setData([]);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  if (loading) return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Détail Département</h1>
          <p className="text-sm text-on-surface-variant">Vue comparative des filières</p>
        </div>
      </div>

      <div className="space-y-4">
        {data.length > 0 ? data.map((row, i) => (
          <div key={i} className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h3 className="font-bold text-primary">
                  {row.dep} <span className="font-mono text-xs text-on-surface-variant">({row.code})</span>
                </h3>
                <p className="text-xs text-on-surface-variant">{row.students} étudiants · {row.sessions} UE</p>
              </div>
              <Badge variant={row.change.startsWith('+') ? 'success' : 'error'}>{row.change}</Badge>
            </div>
            <ProgressBar value={row.rate} color={row.rate >= 90 ? 'success' : row.rate >= 85 ? 'warning' : 'error'} />
          </div>
        )) : (
          <p className="text-center text-on-surface-variant py-8">Aucune donnée disponible</p>
        )}
      </div>
    </div>
  );
}
