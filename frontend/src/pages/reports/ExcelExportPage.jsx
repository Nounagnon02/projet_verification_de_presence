import { useState } from 'react';
import { FiDownload, FiFileText } from 'react-icons/fi';
import { exporterPresencesCsv } from '../../api/resources/rapports';
import { enregistrer, nomFichierServeur } from '../../utils/telechargement';

// « Ce semestre » et « Cette année » ont disparu : aucune date sûre ne les
// définit (l'année académique n'est pas l'année civile), et ces boutons
// n'envoyaient de toute façon aucune date — le fichier contenait tout.
const PERIODES = [
  { key: 'week', label: 'Cette semaine' },
  { key: 'month', label: 'Ce mois' },
  { key: 'all', label: 'Toute la période' },
  { key: 'custom', label: 'Personnalisée' },
];

// Colonnes proposées, dans l'ordre du fichier. Les cases n'étaient transmises
// nulle part : le fichier gardait toutes les colonnes.
const COLONNES = [
  ['name', 'Nom'],
  ['matricule', 'Matricule'],
  ['filiere', 'Filière'],
  ['course', 'Cours'],
  ['date', 'Date'],
  ['time', 'Heure'],
  ['status', 'Statut'],
];

/** « 2026-09-14 » à partir d'une date locale. */
const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/** Bornes d'une période prédéfinie, jusqu'à aujourd'hui inclus. */
function bornes(periode, maintenant = new Date()) {
  if (periode === 'week') {
    const lundi = new Date(maintenant);
    lundi.setDate(maintenant.getDate() - ((maintenant.getDay() + 6) % 7));
    return { date_debut: iso(lundi), date_fin: iso(maintenant) };
  }
  if (periode === 'month') {
    return { date_debut: iso(new Date(maintenant.getFullYear(), maintenant.getMonth(), 1)), date_fin: iso(maintenant) };
  }
  return {};
}

export default function ExcelExportPage() {
  const [dateRange, setDateRange] = useState('month');
  const [customStart, setCustomStart] = useState('');
  const [customEnd, setCustomEnd] = useState('');
  const [exporting, setExporting] = useState(false);
  const [columns, setColumns] = useState(Object.fromEntries(COLONNES.map(([cle]) => [cle, true])));

  const toggleCol = (key) => setColumns({ ...columns, [key]: !columns[key] });
  const colonnesChoisies = COLONNES.map(([cle]) => cle).filter((cle) => columns[cle]);

  const handleExport = async () => {
    setExporting(true);
    try {
      const params = dateRange === 'custom'
        ? { date_debut: customStart || undefined, date_fin: customEnd || undefined }
        : bornes(dateRange);
      params.colonnes = colonnesChoisies.join(',');

      const { data, headers } = await exporterPresencesCsv(params);

      // Le nom donné par le serveur résume la période exportée.
      enregistrer(data, nomFichierServeur(headers, 'presences.csv'));
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de l'export");
    } finally {
      setExporting(false);
    }
  };

  const periodLabel = dateRange === 'custom'
    ? `${customStart || '...'} → ${customEnd || '...'}`
    : PERIODES.find((p) => p.key === dateRange)?.label;

  return (
    <div>
      {/* Le titre annoncait « Excel » alors que l'endpoint renvoie text/csv
          et nomme le fichier .csv — la carte plus bas disait deja « Export
          CSV », la page se contredisait. Le vrai export XLSX existe, mais
          ailleurs : Presences puis Historique. */}
      <h1 className="text-2xl font-bold font-headline text-primary mb-2">Export CSV</h1>
      <p className="text-sm text-on-surface-variant mb-8">Configurez et exportez les données de présence</p>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
            <h2 className="text-base font-bold font-headline text-primary mb-4">Période</h2>
            <div className="flex gap-3 flex-wrap">
              {PERIODES.map((opt) => (
                <button
                  key={opt.key}
                  onClick={() => setDateRange(opt.key)}
                  aria-pressed={dateRange === opt.key}
                  className={`px-4 py-2 rounded-xl text-xs font-semibold transition-all ${dateRange === opt.key ? 'bg-primary text-white shadow-sm' : 'bg-surface-container-high text-on-surface-variant hover:text-primary'}`}
                >
                  {opt.label}
                </button>
              ))}
            </div>
            {dateRange === 'custom' && (
              <div className="flex gap-4 mt-4">
                <div>
                  <label htmlFor="export-debut" className="text-xs text-on-surface-variant block mb-1">Date début</label>
                  <input id="export-debut" type="date" value={customStart} onChange={e => setCustomStart(e.target.value)} className="px-3 py-2 bg-surface-container-high rounded-lg text-sm" />
                </div>
                <div>
                  <label htmlFor="export-fin" className="text-xs text-on-surface-variant block mb-1">Date fin</label>
                  <input id="export-fin" type="date" value={customEnd} onChange={e => setCustomEnd(e.target.value)} className="px-3 py-2 bg-surface-container-high rounded-lg text-sm" />
                </div>
              </div>
            )}
          </div>

          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
            <h2 className="text-base font-bold font-headline text-primary mb-4">Colonnes à inclure</h2>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
              {COLONNES.map(([cle, libelle]) => (
                <label key={cle} className="flex items-center gap-3 p-3 bg-surface-container-high rounded-xl cursor-pointer hover:bg-surface-container transition-colors">
                  <input type="checkbox" checked={columns[cle]} onChange={() => toggleCol(cle)} className="w-4 h-4 rounded accent-primary" />
                  <span className="text-sm">{libelle}</span>
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
            <button onClick={handleExport} disabled={exporting || colonnesChoisies.length === 0}
              className="w-full bg-primary text-white py-3 rounded-xl font-semibold text-sm hover:opacity-90 disabled:opacity-50 transition-all flex items-center justify-center gap-2">
              <FiDownload /> {exporting ? 'Export en cours...' : "Générer l'export"}
            </button>
          </div>

          <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm">
            <h3 className="text-sm font-bold text-primary mb-3">Récapitulatif</h3>
            <div className="space-y-2 text-xs text-on-surface-variant">
              <p className="flex justify-between"><span>Période</span><span className="font-semibold text-primary">{periodLabel}</span></p>
              <p className="flex justify-between"><span>Colonnes</span><span className="font-semibold text-primary">{colonnesChoisies.length}</span></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
