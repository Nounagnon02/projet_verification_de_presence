import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { MdSchool, MdGroup, MdHowToReg, MdEvent, MdBusiness, MdChevronRight } from 'react-icons/md';
import api from '../../api/axios';

export default function SuperAdminDashboardPage() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchDashboard = async () => {
      try {
        const { data } = await api.get('/super-admin/dashboard');
        if (data.success) {
          setStats(data.data);
        }
      } catch (err) {
        console.error('Erreur chargement dashboard super admin:', err);
      } finally {
        setLoading(false);
      }
    };
    fetchDashboard();
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  const kpis = [
    { label: 'Facultés / Écoles', value: stats?.total_facultes ?? 0, icon: <MdBusiness />, color: 'bg-blue-500' },
    { label: 'Étudiants', value: stats?.total_etudiants ?? 0, icon: <MdGroup />, color: 'bg-emerald-500' },
    { label: 'Présences totales', value: stats?.total_presences ?? 0, icon: <MdHowToReg />, color: 'bg-violet-500' },
    { label: 'Cours aujourd\'hui', value: stats?.cours_aujourdhui ?? 0, icon: <MdEvent />, color: 'bg-amber-500' },
  ];

  return (
    <div className="max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-[#011549] font-headline">Tableau de bord UAC</h1>
        <p className="text-sm text-slate-500 mt-1">Supervision globale de toutes les facultés et écoles</p>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {kpis.map((kpi) => (
          <div key={kpi.label} className="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div className="flex items-center gap-4">
              <div className={`w-12 h-12 ${kpi.color} rounded-xl flex items-center justify-center text-white text-xl`}>
                {kpi.icon}
              </div>
              <div>
                <p className="text-2xl font-bold text-[#011549]">{kpi.value.toLocaleString()}</p>
                <p className="text-xs text-slate-500">{kpi.label}</p>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Liste des facultés */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <h2 className="text-base font-semibold text-[#011549]">Facultés et Écoles</h2>
          <Link
            to="/super-admin/etablissements"
            className="text-sm text-blue-600 hover:text-blue-800 font-medium"
          >
            Voir tout →
          </Link>
        </div>
        <div className="divide-y divide-slate-50">
          {stats?.facultes?.length > 0 ? (
            stats.facultes.map((fac) => (
              <Link
                key={fac.id}
                to={`/super-admin/etablissements/${fac.id}`}
                className="flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors"
              >
                <div className="flex items-center gap-4">
                  <div className="w-10 h-10 bg-[#011549]/5 rounded-xl flex items-center justify-center text-[#011549]">
                    <MdSchool size={20} />
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-[#011549]">{fac.nom}</p>
                    <p className="text-xs text-slate-500">{fac.code} — {fac.filieres_count ?? 0} filière(s)</p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  {fac.actif ? (
                    <span className="text-xs bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-full font-medium">Actif</span>
                  ) : (
                    <span className="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full font-medium">Inactif</span>
                  )}
                  <MdChevronRight className="text-slate-400" />
                </div>
              </Link>
            ))
          ) : (
            <div className="px-6 py-8 text-center text-sm text-slate-400">
              Aucune faculté enregistrée pour le moment.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
