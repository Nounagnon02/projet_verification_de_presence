import { useState } from 'react';
import { FiBook, FiPlus, FiClock, FiUsers, FiLoader } from 'react-icons/fi';
import Tabs from '../../components/ui/Tabs';
import Badge from '../../components/ui/Badge';
import SearchInput from '../../components/ui/SearchInput';
import useApi from '../../hooks/useApi';

export default function CourseListPage() {
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');

  const { data: ues, loading: uesLoading } = useApi('/admin/ues');
  const { data: ecs, loading: ecsLoading } = useApi('/admin/ecs');

  const tabs = [
    { key: 'all', label: 'Tous' },
    { key: 'ue', label: 'UE' },
    { key: 'ec', label: 'EC' },
  ];

  const ueList = (Array.isArray(ues) ? ues : []).map(u => ({
    id: u.id, code: u.code, name: u.intitule, type: 'ue',
    semester: `S${u.semestre || ''}`, credits: u.volume_horaire || 0,
    students: u.filiere?.etudiants_count || 0,
    filiere: u.filiere?.code || '',
  }));

  const ecList = (Array.isArray(ecs) ? ecs : []).map(e => ({
    id: e.id, code: e.code, name: e.intitule, type: 'ec',
    semester: e.ue?.semestre ? `S${e.ue.semestre}` : '',
    credits: e.volume_horaire || 0,
    students: 0,
    parent: e.ue?.intitule || '',
  }));

  const allCourses = [...ueList, ...ecList];

  const filtered = allCourses.filter(c => {
    if (filter === 'ue' && c.type !== 'ue') return false;
    if (filter === 'ec' && c.type !== 'ec') return false;
    if (search && !c.name?.toLowerCase().includes(search.toLowerCase()) && !c.code?.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  const loading = uesLoading || ecsLoading;

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Liste des Cours</h1>
          <p className="text-sm text-on-surface-variant">Gérez les Unités d'Enseignement (UE) et Éléments Constitutifs (EC)</p>
        </div>
        <button className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all shadow-sm">
          <FiPlus /> Définir nouveau cours
        </button>
      </div>

      <div className="flex flex-col md:flex-row gap-4 mb-8">
        <Tabs tabs={tabs} activeTab={filter} onChange={setFilter} />
        <SearchInput value={search} onChange={setSearch} placeholder="Rechercher un cours..." className="md:ml-auto md:max-w-xs w-full" />
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-64">
          <FiLoader className="animate-spin text-primary w-8 h-8" />
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {filtered.map((course) => (
              <div key={course.id} className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-transparent hover:border-primary/20 transition-all">
                <div className="flex items-start justify-between mb-4">
                  <div className="p-2.5 bg-primary/5 rounded-xl">
                    <FiBook className="text-primary" size={20} />
                  </div>
                  <Badge variant={course.type === 'ue' ? 'info' : 'neutral'}>
                    {course.type === 'ue' ? 'UE' : 'EC'}
                  </Badge>
                </div>
                <h3 className="text-base font-bold text-primary font-headline mb-1">{course.name}</h3>
                <p className="text-xs font-mono text-on-surface-variant mb-4">{course.code}</p>
                {course.parent && (
                  <p className="text-xs text-on-surface-variant mb-3">Composante de : <span className="font-semibold">{course.parent}</span></p>
                )}
                <div className="flex items-center gap-4 text-xs text-on-surface-variant pt-3 border-t border-outline-variant/5">
                  <span className="flex items-center gap-1"><FiClock size={12} /> {course.semester}</span>
                  <span>{course.credits}h</span>
                  {course.filiere && <span>{course.filiere}</span>}
                </div>
              </div>
            ))}

            <button className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border-2 border-dashed border-outline-variant/30 hover:border-primary/30 transition-all flex flex-col items-center justify-center min-h-[200px] group">
              <div className="p-3 bg-surface-container-high rounded-2xl mb-3 group-hover:bg-primary/10 transition-colors">
                <FiPlus className="text-on-surface-variant group-hover:text-primary" size={24} />
              </div>
              <p className="text-sm font-medium text-on-surface-variant group-hover:text-primary transition-colors">
                Définir nouveau cours
              </p>
            </button>
          </div>

          <div className="mt-8 bg-gradient-to-br from-primary to-primary-container rounded-xxl p-6 text-white">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
              {[
                { label: "Total UE", value: ueList.length },
                { label: "Total EC", value: ecList.length },
                { label: "Étudiants", value: ueList.reduce((s, u) => s + (u.students || 0), 0) || '—' },
                { label: "Total cours", value: allCourses.length },
              ].map((s, i) => (
                <div key={i} className="text-center">
                  <p className="text-2xl font-bold font-headline">{s.value}</p>
                  <p className="text-xs text-white/80">{s.label}</p>
                </div>
              ))}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
