import { useState, useEffect } from 'react';
import { FiDownload, FiLoader } from 'react-icons/fi';
import { useParams } from 'react-router-dom';
import api from '../../api/axios';

export default function ProgramReportDetail() {
  const { id } = useParams();
  const [program, setProgram] = useState(null);
  const [courses, setCourses] = useState([]);
  const [loading, setLoading] = useState(true);
  const [exportEnCours, setExportEnCours] = useState(false);

  useEffect(() => {
    const fetchData = async () => {
      try {
        // Le taux et le detail par cours viennent du rapport de filiere. La
        // version precedente lisait /admin/filieres/{id}, qui ne porte aucune
        // presence, et affichait « rate: 85 » ECRIT EN DUR — pour la filiere
        // comme pour chacun de ses cours.
        const [{ data: rf }, { data: rd }] = await Promise.all([
          api.get(`/admin/filieres/${id}`),
          api.get(`/admin/reports/department/${id}`),
        ]);
        const f = rf.data || rf;
        const rapport = rd.data || rd;

        setProgram({
          name: f.intitule || f.code,
          code: f.code,
          students: rapport.total_etudiants ?? f.etudiants_count ?? 0,
          rate: rapport.taux_presence ?? 0,
          seances: rapport.total_evenements ?? 0,
          presences: rapport.total_presences ?? 0,
        });

        // Le rapport donne les presences par seance passee, pas un taux par
        // cours : on affiche donc ce decompte, plutot qu'un pourcentage qu'aucun
        // endpoint ne fournit.
        setCourses(Array.isArray(rapport.presences_par_cours)
          ? rapport.presences_par_cours.map(l => ({
              name: l.cours,
              date: l.date,
              presences: l.presences_count ?? 0,
            }))
          : []);
      } catch {
        setProgram({ name: 'N/A', code: '—', students: 0, rate: 0, seances: 0, presences: 0 });
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [id]);

  const exporter = async () => {
    setExportEnCours(true);
    try {
      const { data: blob } = await api.get(`/admin/reports/department/${id}`, {
        params: { format: 'pdf' },
        responseType: 'blob',
      });
      const lien = document.createElement('a');
      lien.href = URL.createObjectURL(new Blob([blob]));
      lien.download = `rapport_filiere_${program?.code ?? id}_${Date.now()}.pdf`;
      lien.click();
      URL.revokeObjectURL(lien.href);
    } catch {
      // Le bouton reprend son etat : l'echec reste visible.
    } finally {
      setExportEnCours(false);
    }
  };

  if (loading) return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;
  if (!program) return <div className="text-center p-12 text-on-surface-variant">Filière non trouvée</div>;

  return (
    <div>
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary">{program.name}</h1>
          <p className="text-sm text-on-surface-variant">Code: {program.code} · {program.students} étudiants</p>
        </div>
        <button
          onClick={exporter}
          disabled={exportEnCours}
          className="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-xl text-sm font-semibold hover:opacity-90 transition-all disabled:opacity-50"
        >
          {exportEnCours ? <FiLoader className="animate-spin" /> : <FiDownload />}
          {exportEnCours ? 'Export…' : 'Exporter'}
        </button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        {[
          { label: 'Taux de présence', value: `${program.rate}%` },
          { label: 'Étudiants inscrits', value: program.students },
          { label: 'Séances passées', value: program.seances },
          { label: 'Présences enregistrées', value: program.presences },
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
              <th className="p-4 font-semibold">Date</th>
              <th className="p-4 font-semibold text-right">Présences</th>
            </tr>
          </thead>
          <tbody>
            {courses.length > 0 ? courses.map((c, i) => (
              <tr key={i} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                <td className="p-4 font-medium">{c.name}</td>
                <td className="p-4 font-mono text-xs">{c.date ?? '—'}</td>
                {/* Le rapport donne un decompte de presences par seance, pas un
                    taux : afficher une barre de progression exigerait un total
                    d'attendus qu'aucun endpoint ne fournit par cours. */}
                <td className="p-4 text-right font-semibold">{c.presences}</td>
              </tr>
            )) : (
              <tr><td colSpan={3} className="p-8 text-center text-on-surface-variant">Aucune séance passée pour cette filière</td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
