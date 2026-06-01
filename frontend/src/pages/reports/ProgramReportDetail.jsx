import { useState, useEffect } from 'react';
import { FiDownload, FiLoader } from 'react-icons/fi';
import { useParams } from 'react-router-dom';
import Badge from '../../components/ui/Badge';
import ProgressBar from '../../components/charts/ProgressBar';
import api from '../../api/axios';

export default function ProgramReportDetail() {
  const { id } = useParams();
  const [program, setProgram] = useState(null);
  const [courses, setCourses] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const { data: res } = await api.get(`/admin/filieres/${id}`);
        const f = res.data || res;
        setProgram({
          name: f.intitule || f.code,
          code: f.code,
          students: f.etudiants_count || 0,
          rate: 85,
          head: '—',
        });
        const ues = Array.isArray(f.ues) ? f.ues : [];
        setCourses(ues.flatMap(ue => {
          const ecs = Array.isArray(ue.ecs) ? ue.ecs : [];
          return ecs.length > 0
            ? ecs.map(ec => ({ name: ec.intitule, code: ec.code, students: 0, rate: 85 }))
            : [{ name: ue.intitule, code: ue.code, students: 0, rate: 85 }];
        }));
      } catch {
        setProgram({ name: 'N/A', code: '—', students: 0, rate: 0, head: '—' });
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [id]);

  if (loading) return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;
  if (!program) return <div className="text-center p-12 text-on-surface-variant">Filière non trouvée</div>;

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">{program.name}</h1>
          <p className="text-sm text-on-surface-variant">Code: {program.code} · {program.students} étudiants</p>
        </div>
        <button className="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all">
          <FiDownload /> Exporter
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        {[
          { label: 'Taux Présence', value: `${program.rate}%`, trend: 'up' },
          { label: 'Étudiants', value: program.students, trend: 'neutral' },
          { label: 'Cours', value: courses.length, trend: 'neutral' },
          { label: 'Code', value: program.code, trend: 'neutral' },
        ].map((s, i) => (
          <div key={i} className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm">
            <p className="text-2xl font-bold font-headline text-primary">{s.value}</p>
            <p className="text-xs text-on-surface-variant mt-1">{s.label}</p>
          </div>
        ))}
      </div>

      <div className="bg-surface-container-lowest rounded-xxl shadow-sm overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b text-left text-xs text-on-surface-variant uppercase tracking-wider">
              <th className="p-4 font-semibold">Cours</th>
              <th className="p-4 font-semibold">Code</th>
              <th className="p-4 w-1/4"></th>
            </tr>
          </thead>
          <tbody>
            {courses.length > 0 ? courses.map((c, i) => (
              <tr key={i} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                <td className="p-4 font-medium">{c.name}</td>
                <td className="p-4 font-mono text-xs">{c.code}</td>
                <td className="p-4">
                  <ProgressBar value={c.rate} size="sm" showValue={false} color={c.rate >= 90 ? 'success' : 'warning'} />
                </td>
              </tr>
            )) : (
              <tr><td colSpan={3} className="p-8 text-center text-on-surface-variant">Aucun cours</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
