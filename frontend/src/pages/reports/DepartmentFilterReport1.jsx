import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { FiDownload, FiLoader } from 'react-icons/fi';
import api from '../../api/axios';

/**
 * Sert aussi bien « /reports/department » (liste complète) que
 * « /reports/department/:id » (une filière déjà présélectionnée) : c'est
 * DepartmentFilterReport2 qui portait cette seconde route, sans jamais lire
 * cet identifiant ni interroger le moindre vrai chiffre — un taux de 85 %
 * ÉCRIT EN DUR présenté comme une statistique pour chaque filière. Cette
 * page-ci fait déjà tout ce qu'il fallait : elle n'avait qu'à recevoir l'id.
 */
export default function DepartmentFilterReport1() {
  const { id } = useParams();
  const idPreselectionne = id ? Number(id) : null;

  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(idPreselectionne);

  useEffect(() => {
    const fetchData = async () => {
      try {
        // /admin/reports/filiere-stats et non /admin/filieres : le second ne
        // renvoie que la liste des filieres, sans aucune presence. La page
        // affichait donc un taux de 85 % ECRIT EN DUR et un nombre de presents
        // calcule comme « etudiants x 0,85 » — des chiffres fabriques, presentes
        // comme des statistiques.
        const { data: res } = await api.get('/admin/reports/filiere-stats');
        const filieres = res.data || res;
        if (Array.isArray(filieres)) {
          setData(filieres.map(f => ({
            id: f.id,
            code: f.code,
            department: f.intitule || f.code,
            students: f.etudiants_count || 0,
            present: f.total_presences ?? 0,
            rate: f.taux ?? 0,
            evenements: f.total_evenements ?? 0,
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

  const [exportEnCours, setExportEnCours] = useState(false);

  const exporter = async () => {
    if (!selected) return;
    setExportEnCours(true);
    try {
      const { data: blob } = await api.get(`/admin/reports/department/${selected}`, {
        params: { format: 'pdf' },
        responseType: 'blob',
      });
      const lien = document.createElement('a');
      lien.href = URL.createObjectURL(new Blob([blob]));
      lien.download = `rapport_filiere_${selected}_${Date.now()}.pdf`;
      lien.click();
      URL.revokeObjectURL(lien.href);
    } catch {
      // L'echec reste visible : le bouton reprend son etat initial.
    } finally {
      setExportEnCours(false);
    }
  };

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
        {/* Le bouton n'avait aucun gestionnaire. L'export porte sur la filiere
            selectionnee : sans selection il n'y a rien a exporter, ce que
            l'etat desactive dit plutot que de laisser cliquer dans le vide. */}
        <button
          onClick={exporter}
          disabled={!selected || exportEnCours}
          className="flex items-center gap-2 px-4 py-2 bg-surface-container-low rounded-xl text-sm text-on-surface-variant hover:bg-surface-container-high transition-colors disabled:opacity-50"
          title={selected ? 'Exporter le rapport de la filière sélectionnée' : 'Sélectionnez une filière'}
        >
          {exportEnCours ? <FiLoader className="animate-spin" /> : <FiDownload />}
          {exportEnCours ? 'Export…' : 'Exporter'}
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
              <th className="p-4 font-semibold text-right">Taux</th>
            </tr>
          </thead>
          <tbody>
            {filtered.length > 0 ? filtered.map((row) => (
              <tr key={row.id} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                <td className="p-4 font-medium">{row.department}</td>
                <td className="p-4 text-right">{row.students}</td>
                <td className="p-4 text-right font-mono text-xs">{row.code}</td>
                <td className="p-4 text-right">{row.rate}%</td>
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
