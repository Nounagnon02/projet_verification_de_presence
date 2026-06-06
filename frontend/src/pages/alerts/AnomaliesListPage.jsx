import { useState, useEffect, useCallback } from 'react';
import {
  FiRefreshCw, FiCheckCircle, FiXCircle, FiAlertTriangle,
  FiShield, FiUser, FiClock, FiAlertCircle, FiInfo
} from 'react-icons/fi';
import api from '../../api/axios';

const SEVERITY_CONFIG = {
  low: { label: 'Faible', color: 'bg-info/10 text-info' },
  medium: { label: 'Moyenne', color: 'bg-warning-container/30 text-warning' },
  high: { label: 'Élevée', color: 'bg-error-container/30 text-error' },
  critical: { label: 'Critique', color: 'bg-error/10 text-error' },
};

const TYPE_CONFIG = {
  double_scan: { label: 'Double scan', icon: FiAlertCircle },
  device_mismatch: { label: 'Appareil différent', icon: FiShield },
  location_anomaly: { label: 'Anomalie de localisation', icon: FiAlertTriangle },
  time_anomaly: { label: 'Anomalie temporelle', icon: FiClock },
  suspicious: { label: 'Comportement suspect', icon: FiAlertTriangle },
  unknown: { label: 'Inconnu', icon: FiInfo },
};

export default function AnomaliesListPage() {
  const [alerts, setAlerts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [pagination, setPagination] = useState({ currentPage: 1, lastPage: 1 });
  const [resolving, setResolving] = useState(null);

  const fetchAlerts = useCallback(async (page = 1) => {
    try {
      setLoading(true);
      setError('');
      const { data } = await api.get('/admin/alerts', { params: { page } });
      if (data.success && data.data) {
        setAlerts(data.data);
        setPagination({
          currentPage: data.meta?.current_page || page,
          lastPage: data.meta?.last_page || 1,
        });
      }
    } catch (err) {
      setError('Erreur lors du chargement des alertes.');
      console.error('[Anomalies]', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchAlerts(); }, [fetchAlerts]);

  const handleResolve = async (id, status) => {
    setResolving(id);
    setError('');
    setSuccess('');
    try {
      const { data } = await api.post(`/admin/alerts/${id}/resolve`, { status });
      if (data.success) {
        setSuccess(`Alerte marquée comme ${status === 'valide' ? 'valide (présence restaurée)' : 'invalide (ignorée)'}.`);
        setAlerts(prev => prev.filter(a => a.id !== id));
      }
    } catch (err) {
      setError('Erreur lors de la résolution de l\'alerte.');
    } finally {
      setResolving(null);
    }
  };

  const getTypeConfig = (type) => TYPE_CONFIG[type] || TYPE_CONFIG.unknown;
  const getSeverityConfig = (severity) => SEVERITY_CONFIG[severity] || SEVERITY_CONFIG.low;

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* En-tête */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-primary font-headline">Alertes de fraude</h1>
          <p className="text-sm text-on-surface-variant">Détection des comportements suspects (CDC 11.1)</p>
        </div>
        <button onClick={() => fetchAlerts()}
          className="flex items-center gap-1.5 px-4 py-2 bg-surface-container-high rounded-xl text-xs font-semibold text-on-surface-variant hover:bg-surface-container-high/80 transition-all">
          <FiRefreshCw size={14} /> Actualiser
        </button>
      </div>

      {/* Alertes */}
      {error && (
        <div className="flex items-center gap-2 p-3 bg-error-container/30 rounded-xl text-on-error-container text-sm">
          <FiAlertTriangle size={16} className="flex-shrink-0" />
          <span className="flex-1">{error}</span>
          <button onClick={() => setError('')} className="text-on-error-container/60">&times;</button>
        </div>
      )}
      {success && (
        <div className="flex items-center gap-2 p-3 bg-secondary-container/30 rounded-xl text-on-secondary-container text-sm border border-secondary/10">
          <FiCheckCircle size={16} className="flex-shrink-0" />
          <span className="flex-1">{success}</span>
          <button onClick={() => setSuccess('')} className="text-on-secondary-container/60">&times;</button>
        </div>
      )}

      {loading ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center">
          <FiRefreshCw className="animate-spin mx-auto text-primary text-3xl mb-4" />
          <p className="text-on-surface-variant">Analyse des alertes...</p>
        </div>
      ) : alerts.length === 0 ? (
        <div className="bg-surface-container-lowest rounded-xl p-12 shadow-sm text-center border border-dashed border-outline-variant/30">
          <div className="w-16 h-16 bg-secondary/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <FiCheckCircle className="text-secondary" size={28} />
          </div>
          <h3 className="text-lg font-semibold text-on-surface mb-2">Aucune alerte</h3>
          <p className="text-sm text-on-surface-variant">
            Aucune activité suspecte détectée. Le système de détection de fraude (CDC 11.1) surveille en continu les scans de présence.
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {alerts.map((alert) => {
            const typeConfig = getTypeConfig(alert.type);
            const TypeIcon = typeConfig.icon;
            const severityConfig = getSeverityConfig(alert.severite);
            return (
              <div key={alert.id}
                className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10">
                <div className="flex items-start gap-3">
                  <div className={`p-2 rounded-lg flex-shrink-0 ${
                    alert.severite === 'critical' || alert.severite === 'high'
                      ? 'bg-error-container/30 text-error'
                      : 'bg-warning-container/30 text-warning'
                  }`}>
                    <TypeIcon size={20} />
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="px-2.5 py-0.5 rounded-md text-[10px] font-bold font-mono bg-surface-container-high text-outline">
                        {typeConfig.label}
                      </span>
                      <span className={`px-2.5 py-0.5 rounded-md text-[10px] font-bold ${severityConfig.color}`}>
                        {severityConfig.label}
                      </span>
                    </div>

                    <p className="text-sm text-on-surface mt-2">{alert.description}</p>

                    {alert.etudiant && (
                      <div className="flex items-center gap-2 mt-2 text-xs text-on-surface-variant">
                        <FiUser size={12} />
                        <span className="font-semibold">{alert.etudiant.prenom} {alert.etudiant.nom}</span>
                        <span className="font-mono text-[10px]">({alert.etudiant.matricule})</span>
                      </div>
                    )}

                    <p className="text-[10px] text-outline mt-1">
                      {new Date(alert.creee_le).toLocaleDateString('fr-FR', {
                        day: 'numeric', month: 'long', year: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                      })}
                    </p>
                  </div>

                  <div className="flex items-center gap-1 flex-shrink-0">
                    <button
                      onClick={() => handleResolve(alert.id, 'valide')}
                      disabled={resolving === alert.id}
                      className="flex items-center gap-1.5 px-3 py-1.5 bg-secondary/10 text-secondary rounded-lg text-[10px] font-bold hover:bg-secondary/20 transition-all disabled:opacity-50"
                      title="Marquer comme valide (présence restaurée)"
                    >
                      <FiCheckCircle size={12} />
                      Valide
                    </button>
                    <button
                      onClick={() => handleResolve(alert.id, 'invalide')}
                      disabled={resolving === alert.id}
                      className="flex items-center gap-1.5 px-3 py-1.5 bg-error/10 text-error rounded-lg text-[10px] font-bold hover:bg-error/20 transition-all disabled:opacity-50"
                      title="Marquer comme invalide (alerte ignorée)"
                    >
                      <FiXCircle size={12} />
                      Ignorer
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Pagination */}
      {pagination.lastPage > 1 && (
        <div className="flex items-center justify-center gap-2 mt-6">
          <button disabled={pagination.currentPage <= 1}
            onClick={() => fetchAlerts(pagination.currentPage - 1)}
            className="px-4 py-2 bg-surface-container-lowest border border-outline-variant/10 rounded-xl text-xs font-semibold text-on-surface disabled:opacity-40 hover:bg-surface-container-high transition-all">
            Précédent
          </button>
          <span className="text-xs text-on-surface-variant">Page {pagination.currentPage} / {pagination.lastPage}</span>
          <button disabled={pagination.currentPage >= pagination.lastPage}
            onClick={() => fetchAlerts(pagination.currentPage + 1)}
            className="px-4 py-2 bg-surface-container-lowest border border-outline-variant/10 rounded-xl text-xs font-semibold text-on-surface disabled:opacity-40 hover:bg-surface-container-high transition-all">
            Suivant
          </button>
        </div>
      )}
    </div>
  );
}
