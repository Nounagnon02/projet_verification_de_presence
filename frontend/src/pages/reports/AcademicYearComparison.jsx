import { useQuery } from '@tanstack/react-query';
import { FiLoader, FiAlertCircle } from 'react-icons/fi';
import BarChart from '../../components/charts/BarChart';
import { listerAnnees } from '../../api/resources/reference';
import { rapportSemestre } from '../../api/resources/rapports';

const donnees = (reponse) => reponse?.data ?? reponse ?? [];

export default function AcademicYearComparison() {
  // Une seule requête : la liste des années, puis le rapport de chacune
  // (2 GET indépendants par année, non filtrables séparément par l'écran).
  const yearsQuery = useQuery({
    queryKey: ['rapport-annees-comparaison'],
    queryFn: async () => {
      const anneeList = donnees(await listerAnnees());
      if (!Array.isArray(anneeList) || anneeList.length === 0) return [];

      return Promise.all(
        anneeList.map(async (a) => {
          try {
            const stats = donnees(await rapportSemestre(a.id));
            return {
              id: a.id,
              year: a.libelle || 'N/A',
              label: a.libelle || 'N/A',
              rate: stats.taux_presence || 0,
              students: stats.total_etudiants || 0,
              presences: stats.total_presences || 0,
              evenements: stats.total_evenements || 0,
              value: stats.taux_presence || 0,
              active: a.active || false,
            };
          } catch {
            return {
              id: a.id,
              year: a.libelle || 'N/A',
              label: a.libelle || 'N/A',
              rate: 0,
              students: 0,
              presences: 0,
              evenements: 0,
              value: 0,
              active: a.active || false,
            };
          }
        })
      );
    },
  });

  const loading = yearsQuery.isLoading;
  const error = yearsQuery.isError ? 'Impossible de charger les données.' : '';
  const years = yearsQuery.data ?? [];

  if (loading) return <div className="flex justify-center p-12"><FiLoader className="animate-spin text-primary w-8 h-8" /></div>;

  const details = years.length > 0 ? years : [];

  // Calcul de la progression
  const validYears = years.filter(y => y.rate > 0);
  const latestRate = validYears.length > 0 ? validYears[validYears.length - 1].rate : 0;
  const firstRate = validYears.length > 1 ? validYears[0].rate : latestRate;
  const progression = firstRate > 0 ? ((latestRate - firstRate) / firstRate * 100).toFixed(1) : '0';
  const progressionPositive = parseFloat(progression) >= 0;
  const progressionColor = progressionPositive ? 'text-success' : 'text-error';

  return (
    <div>
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-bold font-headline text-primary mb-1">Comparaison Années Académiques</h1>
          <p className="text-sm text-on-surface-variant">Évolution des présences sur plusieurs années</p>
        </div>
      </div>

      {error && (
        <div className="flex items-start gap-2.5 p-4 bg-error/10 rounded-xl text-error border border-error/10 mb-5">
          <FiAlertCircle className="text-lg shrink-0 mt-0.5" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {details.length === 0 ? (
        <div className="text-center py-16 text-on-surface-variant bg-surface-container-lowest rounded-2xl">
          <p>Aucune donnée disponible</p>
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div className="bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10">
              <h2 className="text-base font-bold font-headline text-primary mb-4">Taux de Présence par Année</h2>
              <BarChart data={details} bars="value" height={200} />
              <div className="flex justify-between mt-3 text-xs text-on-surface-variant">
                <span>{details[0]?.year}</span>
                <span>{details[details.length - 1]?.year}</span>
              </div>
            </div>

            <div className="bg-surface-container-lowest rounded-2xl p-6 shadow-sm border border-outline-variant/10">
              <h2 className="text-base font-bold font-headline text-primary mb-4">Progression Totale</h2>
              <p className={`text-5xl font-bold font-headline mb-2 ${progressionColor}`}>
                {progressionPositive ? '+' : ''}{progression}%
              </p>
              <p className="text-sm text-on-surface-variant">
                {progressionPositive ? 'Augmentation' : 'Diminution'} du taux de présence
              </p>
              <div className="mt-6 grid grid-cols-2 gap-4">
                <div className="bg-surface-container-high rounded-xl p-3">
                  <p className="text-xs text-on-surface-variant">Moyenne</p>
                  <p className="text-xl font-bold text-primary">
                    {details.length > 0
                      ? Math.round(details.reduce((a, b) => a + b.rate, 0) / details.length)
                      : 0}%
                  </p>
                </div>
                <div className="bg-surface-container-high rounded-xl p-3">
                  <p className="text-xs text-on-surface-variant">Séances</p>
                  <p className="text-xl font-bold text-primary">
                    {details.reduce((a, b) => a + b.evenements, 0)}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div className="bg-surface-container-lowest rounded-2xl shadow-sm overflow-hidden border border-outline-variant/10">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-xs text-on-surface-variant uppercase tracking-wider">
                  <th className="p-4 font-semibold">Année</th>
                  <th className="p-4 font-semibold text-right">Étudiants</th>
                  <th className="p-4 font-semibold text-right">Séances</th>
                  <th className="p-4 font-semibold text-right">Présences</th>
                  <th className="p-4 font-semibold text-right">Taux</th>
                  <th className="p-4 font-semibold text-right">Active</th>
                </tr>
              </thead>
              <tbody>
                {details.map((d, i) => (
                  <tr key={i} className="border-b last:border-0 hover:bg-surface-container-low/50 transition-colors">
                    <td className="p-4 font-medium">{d.year}</td>
                    <td className="p-4 text-right text-on-surface-variant">{d.students}</td>
                    <td className="p-4 text-right text-on-surface-variant">{d.evenements}</td>
                    <td className="p-4 text-right text-on-surface-variant">{d.presences}</td>
                    <td className="p-4 text-right font-bold" style={{ color: d.rate >= 80 ? '#2E7D32' : d.rate >= 50 ? '#A65207' : '#C62828' }}>
                      {d.rate}%
                    </td>
                    <td className="p-4 text-right">{d.active ? '✓' : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}
