import { useState, useEffect } from 'react';
import { FiDownload, FiLoader } from 'react-icons/fi';
import Badge from '../../components/ui/Badge';
import api from '../../api/axios';

export default function DepartmentFilterReport1() {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const { data: res } = await api.get('/admin/filieres');
        const filieres = res.data || res;
        if (Array.isArray(filieres)) {
          setData(filieres.map(f => ({
            id: f.id, code: f.code,
            department: f.intitule || f.code,
            students: f.etudiants_count || 0,
            present: Math.round((f.etudiants_count || 0) * 0.85),
            rate: 85,
            trend: 'up',
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

  const filtered = selected
    ? data.filter(d => d.id === selected)
    : data;

  if (loading) return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Rapport par Département</h1>
          <p className="text-sm text-on-surface-variant">Filtrez par département/filière</p>
        </div>
        <button className="flex items-center gap-2 px-4 py-2 bg-surface-container-low rounded-xl text-sm text-on-surface-variant hover:bg-surface-container-high transition-colors">
          <FiDownload /> Exporter
        </button>
      </div>

      <div className="flex gap-3 mb-6 overflow-x-auto">
        <button onClick={() => setSelected(null)}
          className={`px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all ${!selected ? 'bg-primary text-white shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-primary'}`}>
          Tous
        </button>
        {data.map(f => (
          <button key={f.id} onClick={() => setSelected(f.id)}
            className={`px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all ${selected === f.id ? 'bg-primary text-white shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-primary'}`}>
            {f.code}
          </button>
        ))}
      </div>

      <div className="bg-surface-container-lowest rounded-xxl shadow-sm border border-outline-variant/10 overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b text-left text-xs text-on-surface-variant uppercase tracking-wider">
              <th className="p-4 font-semibold">Département</th>
              <th className="p-4 font-semibold text-right">Étudiants</th>
              <th className="p-4 font-semibold text-right">Code</th>
              <th className="p-4 font-semibold text-right">Tendance</th>
            </tr>
          </thead>
          <tbody>
            {filtered.length > 0 ? filtered.map((row) => (
              <tr key={row.id} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                <td className="p-4 font-medium">{row.department}</td>
                <td className="p-4 text-right">{row.students}</td>
                <td className="p-4 text-right font-mono text-xs">{row.code}</td>
                <td className="p-4 text-right">
                  <Badge variant={row.trend === 'up' ? 'success' : 'error'}>
                    {row.trend === 'up' ? '↑' : '↓'}
                  </Badge>
                </td>
              </tr>
            )) : (
              <tr><td colSpan={4} className="p-8 text-center text-on-surface-variant">Aucune donnée</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
