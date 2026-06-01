import { useState } from 'react';
import { FiSearch, FiSend, FiMessageSquare, FiUser, FiClock, FiCheck } from 'react-icons/fi';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';

export default function LiveChatDashboard() {
  const [activeConv, setActiveConv] = useState(null);
  const [message, setMessage] = useState('');
  const [search, setSearch] = useState('');
  const [sending, setSending] = useState(false);

  const { data: conversations, loading, refetch: refetchConvs } = useApi('/admin/chat/conversations', { status: 'actif' });
  const { data: messages, loading: loadingMsgs, refetch: refetchMsgs } = useApi(
    activeConv ? `/admin/chat/conversations/${activeConv}/messages` : null
  );

  const filtered = (conversations || []).filter((c) =>
    c.user?.name?.toLowerCase().includes(search.toLowerCase())
  );

  const handleSend = async (e) => {
    e.preventDefault();
    if (!message.trim() || sending || !activeConv) return;
    setSending(true);
    try {
      await api.post(`/admin/chat/conversations/${activeConv}/messages`, { message });
      setMessage('');
      refetchMsgs();
      refetchConvs();
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de l'envoi");
    } finally {
      setSending(false);
    }
  };

  const closeConversation = async () => {
    if (!activeConv || !window.confirm('Fermer cette conversation ?')) return;
    try {
      await api.post(`/admin/chat/conversations/${activeConv}/close`);
      setActiveConv(null);
      refetchConvs();
    } catch (err) {
      alert(err.response?.data?.message || "Erreur");
    }
  };

  const activeConvData = conversations?.find((c) => c.id === activeConv);

  return (
    <div className="h-[calc(100vh-7rem)] flex gap-0">
      {/* Conversation list */}
      <div className="w-80 bg-surface-container-lowest rounded-l-xxl shadow-sm border border-outline-variant/10 flex flex-col shrink-0">
        <div className="p-4 border-b border-outline-variant/10">
          <h2 className="text-base font-bold text-primary font-headline mb-3">Conversations</h2>
          <div className="relative">
            <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-outline" size={14} />
            <input className="w-full pl-9 pr-3 py-2 bg-surface-container-high rounded-lg text-xs focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              placeholder="Rechercher..." value={search} onChange={(e) => setSearch(e.target.value)} />
          </div>
        </div>
        <div className="flex-1 overflow-y-auto">
          {loading ? (
            <div className="text-center py-8 text-xs text-on-surface-variant">Chargement...</div>
          ) : filtered.length === 0 ? (
            <div className="text-center py-8 text-xs text-on-surface-variant">Aucune conversation</div>
          ) : filtered.map((conv) => (
            <button key={conv.id} onClick={() => setActiveConv(conv.id)}
              className={`w-full text-left p-4 border-b border-outline-variant/5 hover:bg-surface-container-low transition-colors ${activeConv === conv.id ? 'bg-primary/[0.03] border-l-2 border-primary' : ''}`}>
              <div className="flex items-start gap-3">
                <div className="relative shrink-0">
                  <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-sm font-bold text-primary">
                    {(conv.user?.name || '?').charAt(0)}
                  </div>
                  {conv.status === 'actif' && <div className="absolute -top-0.5 -right-0.5 w-3 h-3 bg-secondary rounded-full border-2 border-surface-container-lowest" />}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between mb-0.5">
                    <h3 className="text-sm font-semibold text-primary truncate">{conv.user?.name || 'Inconnu'}</h3>
                    <span className="text-[10px] text-on-surface-variant shrink-0">{conv.last_message?.created_at || conv.created_at}</span>
                  </div>
                  <p className="text-xs text-on-surface-variant truncate">{conv.last_message?.message || 'Aucun message'}</p>
                  <div className="flex items-center justify-between mt-1">
                    <span className="text-[10px] text-on-surface-variant">{conv.department || 'Général'}</span>
                    {(conv.unread || 0) > 0 && (
                      <span className="w-5 h-5 bg-primary text-white rounded-full flex items-center justify-center text-[10px] font-bold">{conv.unread}</span>
                    )}
                  </div>
                </div>
              </div>
            </button>
          ))}
        </div>
        <div className="p-4 border-t border-outline-variant/10 text-center">
          <span className="text-[10px] text-on-surface-variant">{filtered.length} conversation{filtered.length !== 1 ? 's' : ''}</span>
        </div>
      </div>

      {/* Chat panel */}
      <div className="flex-1 flex flex-col bg-surface-container-lowest shadow-sm border border-l-0 border-outline-variant/10">
        {!activeConvData ? (
          <div className="flex-1 flex items-center justify-center text-on-surface-variant">
            <div className="text-center">
              <FiMessageSquare size={48} className="mx-auto mb-3 opacity-30" />
              <p className="text-sm">Sélectionnez une conversation</p>
            </div>
          </div>
        ) : (
          <>
            <div className="p-4 border-b border-outline-variant/10 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-xl bg-primary/10 flex items-center justify-center text-sm font-bold text-primary">
                  {(activeConvData.user?.name || '?').charAt(0)}
                </div>
                <div>
                  <h3 className="text-sm font-semibold text-primary">{activeConvData.user?.name}</h3>
                  <p className="text-[10px] text-on-surface-variant">
                    {activeConvData.status === 'actif' ? 'En ligne' : 'Hors ligne'} · {activeConvData.department || 'Général'}
                  </p>
                </div>
              </div>
              <button onClick={closeConversation} className="flex items-center gap-1.5 px-3 py-1.5 bg-error/10 text-error rounded-lg text-xs font-semibold hover:bg-error/20 transition-all">
                Fermer
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6 space-y-4">
              {loadingMsgs ? (
                <div className="text-center py-8 text-xs text-on-surface-variant">Chargement...</div>
              ) : (messages || []).length === 0 ? (
                <div className="text-center py-8 text-xs text-on-surface-variant">Aucun message</div>
              ) : (messages || []).map((msg) => (
                <div key={msg.id} className={`flex ${msg.is_admin ? 'justify-end' : 'justify-start'}`}>
                  <div className={`max-w-[70%]`}>
                    <div className={`rounded-xxl p-3 text-sm leading-relaxed ${
                      msg.is_admin
                        ? 'bg-primary text-white rounded-br-md'
                        : 'bg-surface-container-high text-on-surface rounded-bl-md'
                    }`}>
                      {msg.message}
                    </div>
                    <div className={`flex items-center gap-1 mt-1 ${msg.is_admin ? 'justify-end' : 'justify-start'} px-1`}>
                      <span className="text-[10px] text-on-surface-variant">{msg.created_at}</span>
                      {msg.is_admin && <FiCheck size={12} className="text-secondary" />}
                    </div>
                  </div>
                </div>
              ))}
            </div>

            <form onSubmit={handleSend} className="p-4 border-t border-outline-variant/10">
              <div className="flex items-center gap-3">
                <input className="flex-1 px-4 py-3 bg-surface-container-high rounded-xl text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
                  value={message} onChange={(e) => setMessage(e.target.value)} placeholder="Écrivez votre message..." />
                <button type="submit" disabled={sending || !message.trim()}
                  className="w-11 h-11 bg-primary text-white rounded-xl flex items-center justify-center hover:opacity-90 disabled:opacity-50 transition-all">
                  <FiSend size={18} />
                </button>
              </div>
            </form>
          </>
        )}
      </div>

      {/* Visitor info sidebar */}
      {activeConvData && (
        <div className="w-64 bg-surface-container-lowest rounded-r-xxl shadow-sm border border-l-0 border-outline-variant/10 p-5 flex flex-col shrink-0">
          <div className="text-center mb-6">
            <div className="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center text-2xl font-bold text-primary mx-auto mb-3">
              {(activeConvData.user?.name || '?').charAt(0)}
            </div>
            <h3 className="font-bold text-primary text-sm">{activeConvData.user?.name}</h3>
            <p className="text-xs text-on-surface-variant">{activeConvData.department || 'Général'}</p>
          </div>
          <div className="space-y-3 text-xs">
            <div className="flex justify-between">
              <span className="text-on-surface-variant">Statut</span>
              <span className="font-semibold text-secondary">{activeConvData.status === 'actif' ? 'Actif' : 'Fermé'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-on-surface-variant">Département</span>
              <span className="font-semibold">{activeConvData.department || 'Général'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-on-surface-variant">Créée le</span>
              <span className="font-semibold">{activeConvData.created_at}</span>
            </div>
          </div>
          <div className="mt-6 pt-4 border-t border-outline-variant/10">
            <h4 className="text-xs font-bold text-primary mb-2">Actions rapides</h4>
            <div className="space-y-2">
              <button onClick={closeConversation} className="w-full text-xs py-2 bg-surface-container-high rounded-lg hover:bg-surface-container transition-colors">
                Marquer comme résolu
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
