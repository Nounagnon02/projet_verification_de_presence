import { useState, useCallback, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FiBell, FiCheck, FiTrash2, FiRefreshCw, FiAlertTriangle, FiCheckCircle, FiInfo, FiAlertCircle } from 'react-icons/fi';
import {
  listerNotifications, compterNotificationsNonLues, marquerNotificationLue,
  marquerToutesNotificationsLues, supprimerNotification,
} from '../../api/resources/notifications';

export default function NotificationsPage() {
  const [error, setError] = useState('');
  const [filter, setFilter] = useState('all'); // 'all' | 'unread'

  // La page affichée pilote le chargement, ce qui remplace l'appel direct des
  // boutons de pagination : il n'y a plus qu'un endroit qui émet la requête.
  const [page, setPage] = useState(1);

  const queryClient = useQueryClient();

  const parametresListe = useMemo(() => {
    const params = { page, per_page: 20 };
    if (filter === 'unread') params.unread_only = true;
    return params;
  }, [page, filter]);

  const notificationsQuery = useQuery({
    queryKey: ['notifications', parametresListe],
    queryFn: async ({ signal }) => {
      const result = await listerNotifications(parametresListe, signal);
      if (!result.success) throw new Error(result.message || 'Erreur lors du chargement des notifications.');
      return result;
    },
  });

  // Requête indépendante : le compteur ne dépend ni de la page, ni du filtre,
  // et son échec reste silencieux — la vitrine des notifications reste
  // consultable même si ce compteur est momentanément indisponible.
  const unreadCountQuery = useQuery({
    queryKey: ['notifications-unread-count'],
    queryFn: () => compterNotificationsNonLues(),
  });

  const notifications = notificationsQuery.data?.data ?? [];
  const pagination = {
    currentPage: notificationsQuery.data?.pagination?.current_page || page,
    lastPage: notificationsQuery.data?.pagination?.last_page || 1,
  };
  const unreadCount = unreadCountQuery.data?.data?.count ?? 0;
  const loading = notificationsQuery.isLoading;

  const rafraichir = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['notifications'] });
    queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
  }, [queryClient]);

  const handleMarkRead = async (id) => {
    try {
      await marquerNotificationLue(id);
      rafraichir();
    } catch {
      setError('Erreur lors du marquage de la notification.');
    }
  };

  const handleMarkAllRead = async () => {
    try {
      const data = await marquerToutesNotificationsLues();
      if (data.success) {
        rafraichir();
      }
    } catch {
      setError('Erreur lors du marquage de toutes les notifications.');
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Supprimer cette notification ?')) return;
    try {
      await supprimerNotification(id);
      // Contrairement au marquage lu, la suppression ne touche pas le
      // compteur de non-lues : une notification déjà lue peut être
      // supprimée sans que ce compteur change.
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    } catch {
      setError('Erreur lors de la suppression.');
    }
  };

  const getTypeIcon = (type) => {
    switch (type) {
      case 'info': return <FiInfo className="text-info" />;
      case 'success': return <FiCheckCircle className="text-secondary" />;
      case 'warning': return <FiAlertTriangle className="text-warning" />;
      case 'error': return <FiAlertCircle className="text-error" />;
      default: return <FiBell className="text-primary" />;
    }
  };

  const getTypeBg = (type) => {
    switch (type) {
      case 'info': return 'bg-info/5 border-info/10';
      case 'success': return 'bg-secondary-container/30 border-secondary/10';
      case 'warning': return 'bg-warning-container/30 border-warning/10';
      case 'error': return 'bg-error-container/30 border-error/10';
      default: return 'bg-primary/5 border-primary/10';
    }
  };

  return (
    <div className="max-w-3xl mx-auto">
      {/* En-tête */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Notifications</h1>
          <p className="text-sm text-on-surface-variant">
            {unreadCount > 0
              ? `Vous avez ${unreadCount} notification${unreadCount > 1 ? 's' : ''} non lue${unreadCount > 1 ? 's' : ''}`
              : 'Aucune notification non lue'}
          </p>
        </div>
        {unreadCount > 0 && (
          <button
            onClick={handleMarkAllRead}
            className="flex items-center gap-1.5 px-4 py-2 bg-secondary/10 text-secondary rounded-xl text-xs font-semibold hover:bg-secondary/20 transition-all"
          >
            <FiCheckCircle size={14} />
            Tout marquer comme lu
          </button>
        )}
      </div>

      {/* Filtres */}
      <div className="flex gap-2 mb-6 bg-surface-container-high rounded-xl p-1 w-fit">
        <button
          onClick={() => setFilter('all')}
          className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${
            filter === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'
          }`}
        >
          Toutes
        </button>
        <button
          onClick={() => setFilter('unread')}
          className={`px-4 py-1.5 rounded-lg text-xs font-semibold transition-all ${
            filter === 'unread' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-primary'
          }`}
        >
          Non lues {unreadCount > 0 && `(${unreadCount})`}
        </button>
      </div>

      {/* État d'erreur */}
      {(error || notificationsQuery.isError) && (
        <div className="flex items-center gap-2 p-3 mb-6 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle size={16} className="flex-shrink-0" />
          <span>{error || 'Erreur lors du chargement des notifications.'}</span>
          <button onClick={() => setError('')} className="ml-auto text-on-error-container/60 hover:text-on-error-container">&times;</button>
        </div>
      )}

      {/* Liste des notifications */}
      {loading ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center">
          <FiRefreshCw className="animate-spin mx-auto text-primary text-3xl mb-4" />
          <p className="text-on-surface-variant">Chargement des notifications...</p>
        </div>
      ) : notifications.length === 0 ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center border border-dashed border-outline-variant/30">
          <div className="w-16 h-16 bg-surface-container-high rounded-full flex items-center justify-center mx-auto mb-6">
            <FiBell className="text-outline" size={28} />
          </div>
          <h3 className="text-lg font-semibold text-on-surface mb-2">Aucune notification</h3>
          <p className="text-sm text-on-surface-variant">
            {filter === 'unread' ? 'Vous avez lu toutes vos notifications.' : 'Aucune notification pour le moment.'}
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {notifications.map((notif) => (
            <div
              key={notif.id}
              className={`rounded-xl p-4 border transition-all ${
                notif.is_read
                  ? 'bg-surface-container-lowest border-outline-variant/10'
                  : `${getTypeBg(notif.type)} shadow-sm`
              }`}
            >
              <div className="flex items-start gap-3">
                <div className={`p-2 rounded-lg flex-shrink-0 ${
                  notif.is_read ? 'bg-surface-container-high text-outline' : ''
                }`}>
                  {getTypeIcon(notif.type)}
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-start justify-between gap-2">
                    <h4 className={`text-sm ${notif.is_read ? 'font-medium text-on-surface' : 'font-bold text-on-surface'}`}>
                      {notif.title}
                      {!notif.is_read && (
                        <span className="ml-2 inline-block w-2 h-2 bg-primary rounded-full" />
                      )}
                    </h4>
                    <div className="flex items-center gap-1 flex-shrink-0">
                      {!notif.is_read && (
                        <button
                          onClick={() => handleMarkRead(notif.id)}
                          className="p-1.5 text-outline hover:text-secondary hover:bg-secondary/10 rounded-lg transition-all"
                          title="Marquer comme lu"
                        >
                          <FiCheck size={14} />
                        </button>
                      )}
                      <button
                        onClick={() => handleDelete(notif.id)}
                        className="p-1.5 text-outline hover:text-error hover:bg-error/10 rounded-lg transition-all"
                        title="Supprimer"
                      >
                        <FiTrash2 size={14} />
                      </button>
                    </div>
                  </div>
                  {notif.message && (
                    <p className="text-xs text-on-surface-variant mt-1">{notif.message}</p>
                  )}
                  <p className="text-[10px] text-outline mt-2">{notif.created_at}</p>
                </div>
              </div>

              {notif.link && (
                <a
                  href={notif.link}
                  className="inline-block mt-2 ml-11 text-xs font-semibold text-primary hover:underline"
                >
                  Voir les détails →
                </a>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Pagination */}
      {pagination.lastPage > 1 && (
        <div className="flex items-center justify-center gap-2 mt-6">
          <button
            disabled={pagination.currentPage <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            className="px-4 py-2 bg-surface-container-lowest border border-outline-variant/10 rounded-xl text-xs font-semibold text-on-surface disabled:opacity-40 hover:bg-surface-container-high transition-all"
          >
            Précédent
          </button>
          <span className="text-xs text-on-surface-variant">
            Page {pagination.currentPage} / {pagination.lastPage}
          </span>
          <button
            disabled={pagination.currentPage >= pagination.lastPage}
            onClick={() => setPage((p) => p + 1)}
            className="px-4 py-2 bg-surface-container-lowest border border-outline-variant/10 rounded-xl text-xs font-semibold text-on-surface disabled:opacity-40 hover:bg-surface-container-high transition-all"
          >
            Suivant
          </button>
        </div>
      )}
    </div>
  );
}
