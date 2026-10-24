import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { MdSchool, MdGroup, MdHowToReg, MdEvent, MdBusiness, MdChevronRight } from 'react-icons/md';
import { FiAlertTriangle } from 'react-icons/fi';
import { recupererTableauDeBordSuperAdmin } from '../../api/resources/etablissements';
import KPICard from '../../components/cards/KPICard';

export default function SuperAdminDashboardPage() {
  const dashboardQuery = useQuery({
    queryKey: ['tableau-de-bord-super-admin'],
    queryFn: ({ signal }) => recupererTableauDeBordSuperAdmin(signal),
    // Sur « 2FA requise », axios.js redirige la page entière avant que cette
    // erreur ne compte vraiment : inutile de la rejouer trois fois pendant
    // l'instant où React a encore la main.
    retry: (nombre, erreur) => erreur?.response?.data?.code !== 'two_factor_setup_required' && nombre < 2,
  });
  const stats = dashboardQuery.data?.data ?? null;
  const loading = dashboardQuery.isLoading;

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  // Un échec de chargement ne doit jamais se lire comme « 0 faculté, 0
  // étudiant » : c'était le cas avant cette correction, et ça masquait un vrai
  // 403 (2FA non activée) derrière un tableau de bord d'apparence simplement
  // vide.
  if (dashboardQuery.isError) {
    const message =
      dashboardQuery.error?.response?.data?.message ||
      'Impossible de charger le tableau de bord. Réessayez dans un instant.';
    return (
      <div className="max-w-7xl mx-auto">
        <div className="flex items-start gap-3 p-4 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle size={18} className="flex-shrink-0 mt-0.5" />
          <span>{message}</span>
        </div>
      </div>
    );
  }

  const kpis = [
    {
      label: 'Facultés / Écoles',
      value: (stats?.total_facultes ?? 0).toLocaleString(),
      icon: <MdBusiness size={20} />,
    },
    { label: 'Étudiants', value: (stats?.total_etudiants ?? 0).toLocaleString(), icon: <MdGroup size={20} /> },
    {
      label: 'Présences totales',
      value: (stats?.total_presences ?? 0).toLocaleString(),
      icon: <MdHowToReg size={20} />,
    },
    { label: "Cours aujourd'hui", value: (stats?.cours_aujourdhui ?? 0).toLocaleString(), icon: <MdEvent size={20} /> },
  ];

  return (
    <div className="max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div>
        <h1 className="titre-page">Tableau de bord UAC</h1>
        <p className="sous-titre-page">Supervision globale de toutes les facultés et écoles</p>
      </div>

      {/* KPIs : deux colonnes dès le téléphone, une carte par ligne y occupait
          un écran entier pour quatre chiffres. */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
        {kpis.map((kpi) => (
          <KPICard key={kpi.label} label={kpi.label} value={kpi.value} icon={kpi.icon} />
        ))}
      </div>

      {/* Liste des facultés */}
      <div className="carte overflow-hidden">
        <div className="px-4 sm:px-6 py-4 border-b border-outline-variant/20 flex items-center justify-between">
          <h2 className="text-base font-semibold text-primary">Facultés et Écoles</h2>
          <Link to="/super-admin/etablissements" className="text-sm text-primary font-medium hover:underline">
            Voir tout →
          </Link>
        </div>
        <div className="divide-y divide-outline-variant/10">
          {stats?.facultes?.length > 0 ? (
            stats.facultes.map((fac) => (
              <Link
                key={fac.id}
                to={`/super-admin/etablissements/${fac.id}`}
                className="flex items-center justify-between gap-3 px-4 sm:px-6 py-4 hover:bg-surface-container-low transition-colors"
              >
                <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                  <div
                    className="w-10 h-10 shrink-0 bg-primary/5 rounded-xl flex items-center justify-center text-primary"
                    aria-hidden="true"
                  >
                    <MdSchool size={20} />
                  </div>
                  <div className="min-w-0">
                    <p className="text-sm font-semibold text-primary">{fac.nom}</p>
                    <p className="text-xs text-on-surface-variant">
                      {fac.code} : {fac.filieres_count ?? 0} filière(s)
                    </p>
                  </div>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  {fac.actif ? (
                    <span className="text-xs bg-statut-succes-fond text-statut-succes px-2 py-0.5 rounded-full font-semibold">
                      Actif
                    </span>
                  ) : (
                    <span className="text-xs bg-surface-container-high text-on-surface-variant px-2 py-0.5 rounded-full font-semibold">
                      Inactif
                    </span>
                  )}
                  <MdChevronRight className="text-on-surface-variant" aria-hidden="true" />
                </div>
              </Link>
            ))
          ) : (
            <div className="px-6 py-8 text-center text-sm text-on-surface-variant">
              Aucune faculté enregistrée pour le moment.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
