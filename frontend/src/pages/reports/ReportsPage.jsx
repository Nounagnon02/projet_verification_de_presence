import { useState } from 'react';
import { FiDownload, FiFileText, FiBarChart2, FiCalendar, FiUsers, FiLoader } from 'react-icons/fi';
import api from '../../api/axios';
import useApi from '../../hooks/useApi';
import { useToastCtx } from '../../context/ToastContext';

const ReportsPage = () => {
  const [period, setPeriod] = useState('monthly');
  const [generating, setGenerating] = useState(null);
  const { addToast } = useToastCtx();
  const { data: stats } = useApi('/admin/presence/stats');

  const s = stats || {};
  const globalRate = s.taux_global !== undefined ? `${s.taux_global}%` : '—';

  const reports = [
    { id: 1, title: "Rapport d'assiduité global", desc: 'Taux de présence par cours et par étudiant', icon: <FiBarChart2 />, type: 'global' },
    { id: 2, title: 'Liste des présences', desc: 'Étudiants ayant validé leur présence', icon: <FiUsers />, type: 'presences' },
    { id: 3, title: 'Rapport par filière', desc: 'Assiduité détaillée par département', icon: <FiFileText />, type: 'department' },
    { id: 4, title: 'Statistiques hebdomadaires', desc: 'Évolution de la présence sur la période', icon: <FiCalendar />, type: 'weekly' },
  ];

  const exportReport = async (type) => {
    setGenerating(type);
    try {
      let url = '/admin/reports/presence/1/pdf';
      if (type === 'department') url = `/admin/reports/department/1`;
      const { data } = await api.get(url, { responseType: 'blob' });
      const blob = new Blob([data]);
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `rapport_${type}_${Date.now()}.pdf`;
      link.click();
      URL.revokeObjectURL(link.href);
      addToast?.('Rapport téléchargé', 'success');
    } catch {
      addToast?.('Erreur lors du téléchargement du rapport', 'error');
    } finally {
      setGenerating(null);
    }
  };

  const periodLabels = { weekly: 'Cette semaine', monthly: 'Ce mois', yearly: 'Cette année' };

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Rapports</h1>
          <p className="text-sm text-on-surface-variant">Génération et export des rapports d'assiduité</p>
        </div>
        <div className="flex bg-surface-container-high rounded-xl p-1">
          {['weekly', 'monthly', 'yearly'].map((p) => (
            <button key={p} onClick={() => setPeriod(p)}
              className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${period === p ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'}`}>
              {p === 'weekly' ? 'Semaine' : p === 'monthly' ? 'Mois' : 'Année'}
            </button>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {reports.map((r) => (
          <div key={r.id} className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10 hover:shadow-md transition-all">
            <div className="flex items-start gap-4">
              <div className="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary shrink-0">
                {r.icon}
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="font-bold text-primary text-sm">{r.title}</h3>
                <p className="text-xs text-on-surface-variant mt-1">{r.desc}</p>
                <div className="flex items-center gap-3 mt-4">
                  <button onClick={() => exportReport(r.type)} disabled={generating === r.type}
                    className="flex items-center gap-2 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold hover:opacity-90 transition-all disabled:opacity-50">
                    <FiDownload /> {generating === r.type ? 'Génération...' : 'PDF'}
                  </button>
                </div>
              </div>
            </div>
          </div>
        ))}

        <div className="md:col-span-2 bg-gradient-to-br from-primary to-primary-container rounded-xxl p-6 shadow-sm">
          <div className="flex items-center justify-between text-on-primary">
            <div>
              <h3 className="font-bold text-lg">Période : {periodLabels[period]}</h3>
              <p className="text-sm opacity-80 mt-1">Taux de présence global : {globalRate}</p>
              <p className="text-xs opacity-60 mt-2">Basé sur les données réelles</p>
            </div>
            <FiBarChart2 className="text-4xl opacity-40" />
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReportsPage;
