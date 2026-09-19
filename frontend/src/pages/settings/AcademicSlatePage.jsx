import { useQuery } from '@tanstack/react-query';
import { FiRefreshCw, FiBook, FiUsers, FiCalendar, FiCheckCircle } from 'react-icons/fi';
import { listerAnnees, listerFilieres } from '../../api/resources/reference';
import { listerUes } from '../../api/resources/maquette';
import { listerEtudiants } from '../../api/resources/etudiants';

export default function AcademicSlatePage() {
  // Quatre lectures indépendantes, chacune sa clé : la plus lente ne retarde
  // plus la mise en cache des trois autres.
  const anneesQuery = useQuery({ queryKey: ['annees-academiques'], queryFn: () => listerAnnees() });
  const filieresQuery = useQuery({ queryKey: ['filieres'], queryFn: () => listerFilieres() });
  const uesQuery = useQuery({ queryKey: ['ues'], queryFn: () => listerUes() });
  // Seul le total importe ici : une page suffit.
  const etudiantsQuery = useQuery({ queryKey: ['etudiants', { per_page: 1 }], queryFn: () => listerEtudiants({ per_page: 1 }) });

  const loading = anneesQuery.isLoading || filieresQuery.isLoading || uesQuery.isLoading || etudiantsQuery.isLoading;

  if (loading) {
    return (
      <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center">
        <FiRefreshCw className="animate-spin mx-auto text-primary text-3xl mb-4" />
        <p className="text-on-surface-variant">Chargement du slate académique...</p>
      </div>
    );
  }

  const annees = anneesQuery.data?.data ?? [];
  const filieres = filieresQuery.data?.data ?? [];
  const ues = uesQuery.data?.data ?? [];

  const stats = {
    anneeActive: annees.find(a => a.active) || annees[0] || null,
    totalAnnees: annees.length,
    totalFilieres: filieres.length,
    totalUes: ues.length,
    totalEtudiants: etudiantsQuery.data?.pagination?.total || etudiantsQuery.data?.meta?.total || '...',
  };

  return (
    <div className="max-w-4xl mx-auto space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-primary font-headline">Slate Académique</h1>
        <p className="text-sm text-on-surface-variant">Vue d'ensemble de l'année académique en cours</p>
      </div>

      {/* Année active */}
      {stats?.anneeActive && (
        <div className="bg-gradient-to-br from-primary/5 to-primary-container/10 rounded-2xl p-6 border border-primary/10">
          <div className="flex items-center gap-3 mb-2">
            <FiCalendar className="text-primary" size={24} />
            <div>
              <p className="text-xs text-on-surface-variant font-semibold uppercase tracking-wider">Année académique active</p>
              <h2 className="text-xl font-bold text-primary">{stats.anneeActive.libelle}</h2>
            </div>
            <span className="ml-auto px-3 py-1 bg-secondary/10 text-secondary rounded-full text-xs font-bold">Active</span>
          </div>
          <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-on-surface-variant">Début :</span>{' '}
              <span className="font-semibold text-on-surface">
                {new Date(stats.anneeActive.date_debut).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
              </span>
            </div>
            <div>
              <span className="text-on-surface-variant">Fin :</span>{' '}
              <span className="font-semibold text-on-surface">
                {new Date(stats.anneeActive.date_fin).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
              </span>
            </div>
          </div>
        </div>
      )}

      {/* Stats cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/10">
          <div className="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center mb-3">
            <FiCalendar className="text-primary" size={20} />
          </div>
          <p className="text-2xl font-bold text-on-surface">{stats?.totalAnnees || 0}</p>
          <p className="text-xs text-on-surface-variant mt-0.5">Années académiques</p>
        </div>
        <div className="bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/10">
          <div className="w-10 h-10 bg-fuchsia-500/10 rounded-xl flex items-center justify-center mb-3">
            <FiBook className="text-fuchsia-500" size={20} />
          </div>
          <p className="text-2xl font-bold text-on-surface">{stats?.totalFilieres || 0}</p>
          <p className="text-xs text-on-surface-variant mt-0.5">Filières</p>
        </div>
        <div className="bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/10">
          <div className="w-10 h-10 bg-amber-500/10 rounded-xl flex items-center justify-center mb-3">
            <FiBook className="text-amber-500" size={20} />
          </div>
          <p className="text-2xl font-bold text-on-surface">{stats?.totalUes || 0}</p>
          <p className="text-xs text-on-surface-variant mt-0.5">Unités d'Enseignement</p>
        </div>
        <div className="bg-surface-container-lowest rounded-xl p-5 shadow-sm border border-outline-variant/10">
          <div className="w-10 h-10 bg-secondary/10 rounded-xl flex items-center justify-center mb-3">
            <FiUsers className="text-secondary" size={20} />
          </div>
          <p className="text-2xl font-bold text-on-surface">{stats?.totalEtudiants || 0}</p>
          <p className="text-xs text-on-surface-variant mt-0.5">Étudiants</p>
        </div>
      </div>

      {/* Navigation rapide */}
      <div className="bg-surface-container-lowest rounded-xl p-6 shadow-sm border border-outline-variant/10">
        <h2 className="text-lg font-bold text-on-surface mb-4">Gestion rapide</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <a href="/settings/academic-years"
            className="flex items-center gap-3 p-3 bg-surface-container-high rounded-xl hover:bg-primary/5 transition-all">
            <FiCalendar className="text-primary" size={18} />
            <span className="text-sm font-semibold text-on-surface">Gérer les années académiques</span>
          </a>
          <a href="/settings/filieres"
            className="flex items-center gap-3 p-3 bg-surface-container-high rounded-xl hover:bg-primary/5 transition-all">
            <FiBook className="text-primary" size={18} />
            <span className="text-sm font-semibold text-on-surface">Gérer les filières</span>
          </a>
          <a href="/courses/ues"
            className="flex items-center gap-3 p-3 bg-surface-container-high rounded-xl hover:bg-primary/5 transition-all">
            <FiCheckCircle className="text-primary" size={18} />
            <span className="text-sm font-semibold text-on-surface">Gérer les UE / EC</span>
          </a>
        </div>
      </div>
    </div>
  );
}
