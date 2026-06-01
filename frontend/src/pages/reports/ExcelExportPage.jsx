import { useState } from 'react';
import { FiDownload, FiFileText } from 'react-icons/fi';
import api from '../../api/axios';

export default function ExcelExportPage() {
  const [dateRange, setDateRange] = useState('month');
  const [customStart, setCustomStart] = useState('');
  const [customEnd, setCustomEnd] = useState('');
  const [exporting, setExporting] = useState(false);
  const [columns, setColumns] = useState({
    name: true, matricule: true, course: true, date: true, time: true, status: true,
  });

  const toggleCol = (key) => setColumns({ ...columns, [key]: !columns[key] });

  const handleExport = async () => {
    setExporting(true);
    try {
      const params = {};
      if (dateRange === 'custom' && customStart) params.date_debut = customStart;
      if (dateRange === 'custom' && customEnd) params.date_fin = customEnd;

      const { data } = await api.get('/admin/reports/excel/export', {
        params,
        responseType: 'blob',
      });

      const url = URL.createObjectURL(new Blob([data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `export_presences_${Date.now()}.csv`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de l'export");
    } finally {
      setExporting(false);
    }
  };

  const periodLabel = dateRange === 'custom'
    ? `${customStart || '...'} → ${customEnd || '...'}`
    : { week: 'Cette semaine', month: 'Ce mois', semester: 'Ce semestre', year: 'Cette année' }[dateRange];

  return (
    <div>
      <h1 className="text-2xl font-bold font-headline text-primary mb-2">Export Excel</h1>
      <p className="text-sm text-on-surface-variant mb-8">Configurez et exportez les données de présence</p>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
            <h2 className="text-base font-bold font-headline text-primary mb-4">Période</h2>
            <div className="flex gap-3 flex-wrap">
              {[
                { key: 'week', label: 'Cette semaine' },
                { key: 'month', label: 'Ce mois' },
                { key: 'semester', label: 'Ce semestre' },
                { key: 'year', label: 'Cette année' },
                { key: 'custom', label: 'Personnalisée' },
              ].map((opt) => (
                <button
                  key={opt.key}
                  onClick={() => setDateRange(opt.key)}
                  className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${dateRange === opt.key ? 'bg-primary text-white shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-primary'}`}
                >
                  {opt.label}
                </button>
              ))}
            </div>
            {dateRange === 'custom' && (
              <div className="flex gap-4 mt-4">
                <div>
                  <label className="text-xs text-on-surface-variant block mb-1">Date début</label>
                  <input type="date" value={customStart} onChange={e => setCustomStart(e.target.value)} className="px-3 py-2 bg-surface-container-high rounded-lg text-sm" />
                </div>
                <div>
                  <label className="text-xs text-on-surface-variant block mb-1">Date fin</label>
                  <input type="date" value={customEnd} onChange={e => setCustomEnd(e.target.value)} className="px-3 py-2 bg-surface-container-high rounded-lg text-sm" />
                </div>
              </div>
            )}
          </div>

          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
            <h2 className="text-base font-bold font-headline text-primary mb-4">Colonnes à inclure</h2>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
              {Object.entries(columns).map(([key, val]) => (
                <label key={key} className="flex items-center gap-3 p-3 bg-surface-container-high rounded-xl cursor-pointer hover:bg-surface-container transition-colors">
                  <input type="checkbox" checked={val} onChange={() => toggleCol(key)} className="w-4 h-4 rounded accent-primary" />
                  <span className="text-sm capitalize">{key === 'matricule' ? 'Matricule' : key}</span>
                </label>
              ))}
            </div>
          </div>
        </div>

        <div className="space-y-6">
          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
            <div className="p-4 bg-primary/5 rounded-xl text-center mb-6">
              <FiFileText className="mx-auto text-primary mb-2" size={32} />
              <p className="text-sm font-semibold text-primary">Export CSV</p>
              <p className="text-xs text-on-surface-variant">Fichier CSV compatible Excel</p>
            </div>
            <button onClick={handleExport} disabled={exporting}
              className="w-full bg-primary text-white py-3 rounded-xl font-semibold text-sm hover:opacity-90 disabled:opacity-50 transition-all flex items-center justify-center gap-2">
              <FiDownload /> {exporting ? 'Export en cours...' : "Générer l'export"}
            </button>
          </div>

          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
            <h3 className="text-sm font-bold text-primary mb-3">Récapitulatif</h3>
            <div className="space-y-2 text-xs text-on-surface-variant">
              <p className="flex justify-between"><span>Période</span><span className="font-semibold text-primary">{periodLabel}</span></p>
              <p className="flex justify-between"><span>Colonnes</span><span className="font-semibold text-primary">{Object.values(columns).filter(Boolean).length}</span></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
