import { useState, useEffect } from 'react';
import { FiPrinter, FiDownload, FiLoader } from 'react-icons/fi';
import { formatDate } from '../../utils/formatters';
import api from '../../api/axios';

export default function ReportPrintPreview() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const { data: res } = await api.get('/admin/presence/stats');
        const s = res.data || res;
        setStats(s);
      } catch {
        setStats(null);
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  if (loading) return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;

  const s = stats || {};
  const totalEtudiants = s.total_etudiants || 0;
  const totalPresences = s.total_presences || 0;
  const tauxGlobal = s.taux_global !== undefined ? `${s.taux_global}%` : '—';
  const totalEvenements = s.total_evenements || 0;

  return (
    <div>
      <div className="flex items-center justify-between mb-8 print:hidden">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">Aperçu Impression</h1>
          <p className="text-sm text-on-surface-variant">Rapport de synthèse</p>
        </div>
        <div className="flex gap-3">
          <button onClick={() => window.print()} className="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
            <FiPrinter /> Imprimer
          </button>
          <button className="flex items-center gap-2 px-5 py-2.5 bg-surface-container-low rounded-xl text-sm text-on-surface-variant hover:bg-surface-container-high transition-colors">
            <FiDownload /> PDF
          </button>
        </div>
      </div>

      <div className="bg-white rounded-xxl shadow-sm p-8 print:p-0 print:shadow-none max-w-4xl mx-auto">
        <div className="text-center mb-8 border-b pb-6">
          <h1 className="text-xl font-bold text-primary font-headline">Université d'Abomey-Calavi</h1>
          <p className="text-sm text-on-surface-variant">Rapport de Présence - Synthèse Générale</p>
          <p className="text-xs text-on-surface-variant mt-1">Généré le {formatDate(new Date())}</p>
        </div>

        <div className="grid grid-cols-5 gap-4 mb-8">
          {[
            { label: 'Total étudiants', value: totalEtudiants },
            { label: 'Taux présence', value: tauxGlobal },
            { label: 'Présences', value: totalPresences },
            { label: 'Événements', value: totalEvenements },
            { label: 'Taux', value: tauxGlobal },
          ].map((s, i) => (
            <div key={i} className="text-center p-4 bg-surface rounded-xl">
              <p className="text-xl font-bold font-headline text-primary">{s.value}</p>
              <p className="text-[10px] text-on-surface-variant">{s.label}</p>
            </div>
          ))}
        </div>

        <h2 className="text-base font-bold font-headline text-primary mb-4">Résumé</h2>
        <div className="text-sm text-on-surface-variant space-y-2 mb-8">
          <p>Total étudiants : <strong>{totalEtudiants}</strong></p>
          <p>Total présences enregistrées : <strong>{totalPresences}</strong></p>
          <p>Nombre d'événements : <strong>{totalEvenements}</strong></p>
          <p>Taux de présence global : <strong>{tauxGlobal}</strong></p>
        </div>

        <div className="text-center text-xs text-on-surface-variant pt-6 border-t">
          <p>Document généré automatiquement par le Système de Gestion de Présence UAC</p>
        </div>
      </div>

      <style>{`
        @media print {
          body { background: white; }
          @page { margin: 2cm; }
        }
      `}</style>
    </div>
  );
}
