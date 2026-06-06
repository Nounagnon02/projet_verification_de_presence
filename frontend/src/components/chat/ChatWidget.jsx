import { useState, useEffect, useRef } from 'react';
import { FiMessageSquare, FiX, FiSend, FiMinimize2 } from 'react-icons/fi';

const quickReplies = [
  'Comment valider une présence ?',
  'Exporter un rapport',
  'Contacter le support',
];

export default function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false);
  const [isMinimized, setIsMinimized] = useState(false);
  const [messages, setMessages] = useState([
    { id: 1, from: 'bot', text: '👋 Bonjour ! Comment puis-je vous aider ?' },
  ]);
  const [input, setInput] = useState('');
  const messagesEndRef = useRef(null);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const addBotMessage = (text) => {
    setMessages(prev => [...prev, { id: Date.now(), from: 'bot', text }]);
  };

  const handleSend = (e) => {
    e.preventDefault();
    if (!input.trim()) return;
    const userMsg = input.trim();
    setMessages(prev => [...prev, { id: Date.now(), from: 'user', text: userMsg }]);
    setInput('');

    setTimeout(() => {
      const responses = {
        'comment valider une présence': 'Pour valider une présence, l\'étudiant scanne le QR code affiché en cours ou saisit son matricule dans la page de validation.',
        'exporter un rapport': 'Rendez-vous dans Rapports Mensuels > Export Excel. Sélectionnez la période et les colonnes souhaitées.',
        'contacter le support': 'Vous pouvez créer un ticket dans la section Support ou utiliser le chat en direct (Lun-Ven 8h-18h).',
      };
      const found = Object.entries(responses).find(([key]) => userMsg.toLowerCase().includes(key));
      addBotMessage(found ? found[1] : 'Je vous invite à consulter le centre d\'aide ou à contacter notre équipe support pour plus de détails.');
    }, 800);
  };

  if (!isOpen) {
    return (
      <button onClick={() => setIsOpen(true)}
        className="fixed bottom-24 right-6 z-50 w-14 h-14 bg-primary text-white rounded-full shadow-lg hover:shadow-xl hover:scale-105 transition-all flex items-center justify-center">
        <FiMessageSquare size={24} />
      </button>
    );
  }

  return (
    <div className={`fixed right-6 z-50 shadow-2xl transition-all duration-300 ${isMinimized ? 'bottom-24' : 'bottom-24'}`} style={{ width: '360px' }}>
      {/* Header */}
      <div className="bg-primary text-white rounded-t-2xl p-4 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center text-sm font-bold">U</div>
          <div>
            <h3 className="text-sm font-bold">Support</h3>
            <p className="text-[10px] opacity-80">Réponse sous 5 min</p>
          </div>
        </div>
        <div className="flex gap-1">
          <button onClick={() => setIsMinimized(!isMinimized)} className="p-1.5 hover:bg-white/10 rounded-lg transition-colors">
            <FiMinimize2 size={16} />
          </button>
          <button onClick={() => setIsOpen(false)} className="p-1.5 hover:bg-white/10 rounded-lg transition-colors">
            <FiX size={16} />
          </button>
        </div>
      </div>

      {!isMinimized && (
        <>
          {/* Messages */}
          <div className="bg-surface-container-lowest h-80 overflow-y-auto p-4 space-y-3 border-x border-outline-variant/10">
            {messages.map((msg) => (
              <div key={msg.id} className={`flex ${msg.from === 'user' ? 'justify-end' : 'justify-start'}`}>
                <div className={`max-w-[85%] rounded-2xl p-3 text-sm leading-relaxed ${
                  msg.from === 'user'
                    ? 'bg-primary text-white rounded-br-md'
                    : 'bg-surface-container-high text-on-surface rounded-bl-md'
                }`}>
                  {msg.text}
                </div>
              </div>
            ))}
            <div ref={messagesEndRef} />

            {/* Quick replies */}
            {messages.length <= 2 && (
              <div className="pt-2 space-y-1.5">
                <p className="text-[10px] text-on-surface-variant">Suggestions :</p>
                {quickReplies.map((qr, i) => (
                  <button key={i} onClick={() => { setInput(qr); }}
                    className="block w-full text-left text-xs py-2 px-3 bg-surface-container-high hover:bg-surface-container rounded-xl transition-colors text-on-surface">
                    {qr}
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Input */}
          <form onSubmit={handleSend} className="bg-surface-container-lowest p-3 border border-t-0 border-outline-variant/10 rounded-b-2xl">
            <div className="flex items-center gap-2">
              <input className="flex-1 px-3 py-2 bg-surface-container-high rounded-xl text-xs border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
                value={input} onChange={(e) => setInput(e.target.value)} placeholder="Écrivez votre message..." />
              <button type="submit" className="w-9 h-9 bg-primary text-white rounded-xl flex items-center justify-center hover:opacity-90 transition-all shrink-0">
                <FiSend size={15} />
              </button>
            </div>
          </form>
        </>
      )}
    </div>
  );
}
