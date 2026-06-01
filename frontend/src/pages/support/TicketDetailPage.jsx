import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FiArrowLeft, FiSend, FiUser, FiClock, FiCheckCircle, FiAlertCircle } from 'react-icons/fi';
import Badge from '../../components/ui/Badge';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';

const statusMap = { ouvert: 'success', en_cours: 'warning', resolu: 'info', ferme: 'neutral' };
const statusLabels = { ouvert: 'Ouvert', en_cours: 'En cours', resolu: 'Résolu', ferme: 'Fermé' };

export default function TicketDetailPage() {
  const { id } = useParams();
  const { data: ticket, loading, refetch } = useApi(`/admin/tickets/${id}`);
  const [reply, setReply] = useState('');
  const [sending, setSending] = useState(false);

  const handleReply = async (e) => {
    e.preventDefault();
    if (!reply.trim() || sending) return;
    setSending(true);
    try {
      await api.post(`/admin/tickets/${id}/reply`, { message: reply });
      setReply('');
      refetch();
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de l'envoi");
    } finally {
      setSending(false);
    }
  };

  const updateStatus = async (status) => {
    try {
      await api.patch(`/admin/tickets/${id}/status`, { status });
      refetch();
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors du changement de statut");
    }
  };

  if (loading) {
    return <div className="text-center py-12 text-on-surface-variant">Chargement...</div>;
  }

  if (!ticket) {
    return <div className="text-center py-12 text-on-surface-variant">Ticket non trouvé</div>;
  }

  return (
    <div className="max-w-4xl">
      <div className="mb-6">
        <Link to="/support/tickets" className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary transition-colors">
          <FiArrowLeft size={16} /> Retour aux tickets
        </Link>
      </div>

      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10 mb-6">
        <div className="flex items-start justify-between mb-4">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <span className="text-[10px] font-mono text-on-surface-variant bg-surface-container-high px-2 py-0.5 rounded">#{ticket.id}</span>
              <Badge variant={statusMap[ticket.status] || 'neutral'}>{statusLabels[ticket.status] || ticket.status}</Badge>
              {ticket.priority === 'haute' && <Badge variant="warning">Haute</Badge>}
              {ticket.priority === 'critique' && <Badge variant="error">Critique</Badge>}
            </div>
            <h1 className="text-xl font-bold text-primary font-headline">{ticket.subject}</h1>
            <p className="text-xs text-on-surface-variant mt-1">
              {ticket.category} · Créé le {ticket.created_at} par {ticket.user?.name}
            </p>
          </div>
          <div className="flex gap-2">
            {(ticket.status === 'ouvert' || ticket.status === 'en_cours') && (
              <>
                <button onClick={() => updateStatus('resolu')} className="flex items-center gap-1.5 px-4 py-2 bg-surface-container-high text-on-surface rounded-xl text-xs font-semibold hover:bg-surface-container transition-all">
                  <FiCheckCircle size={14} /> Marquer résolu
                </button>
                <button onClick={() => updateStatus('ferme')} className="flex items-center gap-1.5 px-4 py-2 bg-error/10 text-error rounded-xl text-xs font-semibold hover:bg-error/20 transition-all">
                  <FiAlertCircle size={14} /> Fermer
                </button>
              </>
            )}
            {ticket.status === 'resolu' && (
              <button onClick={() => updateStatus('ferme')} className="flex items-center gap-1.5 px-4 py-2 bg-error/10 text-error rounded-xl text-xs font-semibold hover:bg-error/20 transition-all">
                <FiAlertCircle size={14} /> Fermer
              </button>
            )}
          </div>
        </div>
        {ticket.message && (
          <div className="mt-4 pt-4 border-t border-outline-variant/10">
            <p className="text-sm text-on-surface">{ticket.message}</p>
          </div>
        )}
      </div>

      <div className="space-y-4 mb-6">
        {(ticket.messages || []).map((msg) => (
          <div key={msg.id} className="flex gap-4">
            <div className="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold shrink-0 bg-primary/10 text-primary">
              <FiUser size={14} />
            </div>
            <div className="flex-1 max-w-[80%]">
              <div className="inline-block text-left bg-surface-container-high rounded-xxl p-4 shadow-sm">
                <div className="flex items-center gap-2 mb-2">
                  <span className="text-xs font-semibold text-primary">{msg.user?.name || 'Utilisateur'}</span>
                  <span className="text-[10px] text-on-surface-variant">·</span>
                  <span className="text-[10px] text-on-surface-variant flex items-center gap-1"><FiClock size={10} />{msg.created_at}</span>
                </div>
                <p className="text-sm text-on-surface leading-relaxed">{msg.message}</p>
              </div>
            </div>
          </div>
        ))}
      </div>

      {ticket.status !== 'ferme' && (
        <form onSubmit={handleReply} className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
          <h3 className="text-sm font-bold text-primary mb-4">Votre réponse</h3>
          <textarea rows="4" className="w-full px-4 py-3 bg-surface-container-high rounded-xl text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all resize-none"
            value={reply} onChange={(e) => setReply(e.target.value)} placeholder="Écrivez votre message..." />
          <div className="flex justify-end mt-4">
            <button type="submit" disabled={sending || !reply.trim()} className="flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 disabled:opacity-50 transition-all">
              <FiSend size={16} /> {sending ? 'Envoi...' : 'Envoyer'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
