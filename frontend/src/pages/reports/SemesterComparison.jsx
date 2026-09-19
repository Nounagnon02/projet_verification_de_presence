import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { FiLoader, FiAlertCircle } from 'react-icons/fi';
import BarChart from '../../components/charts/BarChart';
import { listerFilieres, listerAnnees } from '../../api/resources/reference';
import { rapportComparaisonSemestres } from '../../api/resources/rapports';

export default function SemesterComparison() {
  const [selectedFiliere, setSelectedFiliere] = useState('');
  const [selectedAnnee, setSelectedAnnee] = useState('');

  const filieresQuery = useQuery({ queryKey: ['filieres'], queryFn: () => listerFilieres() });
  const anneesQuery = useQuery({ queryKey: ['annees-academiques'], queryFn: () => listerAnnees() });
  const filieres = filieresQuery.data?.data ?? filieresQuery.data ?? [];
  const annees = anneesQuery.data?.data ?? anneesQuery.data ?? [];

  // Sélectionne l'année active par défaut dès que la liste arrive, sans
  // écraser un choix déjà fait par l'utilisateur. Ajusté pendant le rendu
  // (pas un effet) : la donnée est déjà là quand ce composant s'affiche.
  const [anneesVues, setAnneesVues] = useState(null);
  if (annees.length > 0 && annees !== anneesVues) {
    setAnneesVues(annees);
    if (!selectedAnnee) {
      const active = annees.find(y => y.active);
      setSelectedAnnee(String(active?.id || annees[annees.length - 1]?.id || ''));
    }
  }

  const comparaisonQuery = useQuery({
    queryKey: ['rapport-comparaison-semestres', selectedFiliere, selectedAnnee],
    queryFn: ({ signal }) => rapportComparaisonSemestres({ filiere_id: selectedFiliere, annee_id: selectedAnnee }, signal),
    enabled: Boolean(selectedFiliere && selectedAnnee),
  });

  const loading = filieresQuery.isLoading || anneesQuery.isLoading || comparaisonQuery.isFetching;
  const error = filieresQuery.isError || anneesQuery.isError
    ? 'Impossible de charger les filtres.'
    : comparaisonQuery.isError
      ? 'Impossible de charger les données de comparaison.'
      : '';
  const data = comparaisonQuery.isError ? null : (comparaisonQuery.data?.data ?? comparaisonQuery.data ?? null);

  if (loading && !data) {
    return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;
  }

  const semestres = data?.semestres || [];
  // Préparer les données pour les graphiques
  const chartData = semestres.map(s => ({
    label: s.label,
    value: s.taux,
    presences: s.total_presences,
  }));

  return (
    <div>
      <h1 className="text-2xl font-bold font-headline text-primary mb-2">Comparaison Semestrielle</h1>
      <p className="text-sm text-on-surface-variant mb-8">Comparez les taux de présence entre semestres par filière</p>

      {/* Filtres */}
      <div className="flex flex-wrap gap-4 mb-8 bg-surface-container-lowest rounded-2xl p-4 border border-outline-variant/10">
        <div className="flex-1 min-w-[200px]">
          <label htmlFor="comparaison-semestre-filiere" className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant block mb-1.5">
            Filière
          </label>
          <select
            id="comparaison-semestre-filiere"
            value={selectedFiliere}
            onChange={e => setSelectedFiliere(e.target.value)}
            className="w-full px-4 py-2.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-all text-on-surface focus:outline-none text-sm"
          >
            <option value="">Sélectionner une filière</option>
            {filieres.map(f => (
              <option key={f.id} value={f.id}>{f.code} — {f.intitule} ({f.niveau})</option>
            ))}
          </select>
        </div>
        <div className="flex-1 min-w-[200px]">
          <label htmlFor="comparaison-semestre-annee" className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant block mb-1.5">
            Année académique
          </label>
          <select
            id="comparaison-semestre-annee"
            value={selectedAnnee}
            onChange={e => setSelectedAnnee(e.target.value)}
            className="w-full px-4 py-2.5 bg-surface-container-high rounded-xl border-b-2 border-transparent focus:border-primary focus:bg-surface-container-lowest transition-all text-on-surface focus:outline-none text-sm"
          >
            <option value="">Sélectionner une année</option>
            {annees.map(a => (
              <option key={a.id} value={a.id}>{a.libelle} {a.active ? '(active)' : ''}</option>
            ))}
          </select>
        </div>
      </div>

      {error && (
        <div className="flex items-start gap-2.5 p-4 bg-error/10 rounded-xl text-error border border-error/10 mb-5">
          <FiAlertCircle className="text-lg shrink-0 mt-0.5" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {!selectedFiliere || !selectedAnnee ? (
        <div className="text-center py-16 text-on-surface-variant">
          <p>Sélectionnez une filière et une année académique pour voir la comparaison.</p>
        </div>
      ) : chartData.length === 0 ? (
        <div className="text-center py-16 text-on-surface-variant">
          <p>Aucune donnée disponible pour cette filière et cette année.</p>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Graphique des semestres */}
            <div className="bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10">
              <h2 className="text-base font-bold font-headline text-primary mb-1">
                {data?.filiere?.niveau} — {data?.filiere?.intitule}
              </h2>
              <p className="text-xs text-on-surface-variant mb-4">Taux de présence par semestre</p>
              <BarChart data={chartData} bars="value" height={220} />
            </div>

            {/* Tableau détaillé */}
            <div className="bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10">
              <h2 className="text-base font-bold font-headline text-primary mb-4">Détail par semestre</h2>
              <div className="space-y-3">
                {semestres.map(s => (
                  <div key={s.semestre} className="flex items-center justify-between p-3 bg-surface-container-high rounded-xl">
                    <div>
                      <span className="font-bold text-primary">{s.label}</span>
                    </div>
                    <div className="text-right">
                      <span className={`font-bold text-lg ${s.taux >= 80 ? 'text-success' : s.taux >= 50 ? 'text-warning' : 'text-error'}`}>
                        {s.taux}%
                      </span>
                      <p className="text-xs text-on-surface-variant">{s.total_presences} présences</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
