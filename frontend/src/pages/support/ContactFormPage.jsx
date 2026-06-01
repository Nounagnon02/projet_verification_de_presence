import { useState } from 'react';
import { FiSend, FiPaperclip, FiAlertCircle, FiCheck, FiMail, FiMessageSquare } from 'react-icons/fi';
import { Link } from 'react-router-dom';
import api from '../../api/axios';

export default function ContactFormPage() {
  const [form, setForm] = useState({ subject: '', message: '', priority: 'moyenne' });
  const [errors, setErrors] = useState({});
  const [submitted, setSubmitted] = useState(false);
  const [ticketId, setTicketId] = useState(null);
  const [sending, setSending] = useState(false);

  const validate = () => {
    const errs = {};
    if (!form.subject.trim()) errs.subject = 'Le sujet est requis';
    if (!form.message.trim()) errs.message = 'Le message est requis';
    else if (form.message.trim().length < 10) errs.message = 'Minimum 10 caractères';
    return errs;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const errs = validate();
    setErrors(errs);
    if (Object.keys(errs).length > 0) return;

    setSending(true);
    try {
      const res = await api.post('/admin/tickets', form);
      setTicketId(res.data.data?.id);
      setSubmitted(true);
    } catch (err) {
      alert(err.response?.data?.message || "Erreur lors de l'envoi");
    } finally {
      setSending(false);
    }
  };

  if (submitted) {
    return (
      <div className="max-w-2xl mx-auto text-center py-12">
        <div className="bg-surface-container-lowest rounded-xxl p-12 shadow-sm border border-outline-variant/10">
          <div className="w-16 h-16 bg-secondary/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <FiCheck className="text-secondary" size={32} />
          </div>
          <h1 className="text-2xl font-bold text-primary font-headline mb-2">Message envoyé !</h1>
          <p className="text-sm text-on-surface-variant mb-2">Notre équipe traitera votre demande dans les plus brefs délais.</p>
          {ticketId && <p className="text-xs text-on-surface-variant mb-8">Numéro de ticket : #{ticketId}</p>}
          <div className="flex gap-4 justify-center">
            <button onClick={() => { setSubmitted(false); setForm({ subject: '', message: '', priority: 'moyenne' }); setTicketId(null); }}
              className="px-6 py-2.5 bg-surface-container-high text-on-surface rounded-xl font-semibold text-sm hover:bg-surface-container transition-all">
              Nouveau message
            </button>
            <Link to="/support/tickets" className="px-6 py-2.5 bg-primary text-white rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
              Voir mes tickets
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-primary font-headline">Contacter le support</h1>
        <p className="text-sm text-on-surface-variant mt-1">Notre équipe vous répondra sous 24 à 48 heures</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2">
          <form onSubmit={handleSubmit} className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10 space-y-5">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-on-surface-variant">Sujet <span className="text-error">*</span></label>
                <input className={`w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 transition-all focus:outline-none ${errors.subject ? 'border-error' : 'border-transparent focus:border-primary'}`}
                  value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} placeholder="Objet de votre message" />
                {errors.subject && <p className="text-[10px] text-error flex items-center gap-1 mt-1"><FiAlertCircle size={12} />{errors.subject}</p>}
              </div>
              <div className="space-y-1.5">
                <label className="text-xs font-semibold text-on-surface-variant">Priorité</label>
                <select className="w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 border-transparent focus:border-primary focus:outline-none transition-all"
                  value={form.priority} onChange={(e) => setForm({ ...form, priority: e.target.value })}>
                  <option value="basse">Basse</option>
                  <option value="moyenne">Normale</option>
                  <option value="haute">Haute</option>
                  <option value="critique">Critique</option>
                </select>
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-on-surface-variant">Message <span className="text-error">*</span></label>
              <textarea rows="5" className={`w-full px-3 py-2.5 bg-surface-container-high rounded-lg text-sm border-b-2 transition-all focus:outline-none resize-none ${errors.message ? 'border-error' : 'border-transparent focus:border-primary'}`}
                value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} placeholder="Décrivez votre problème en détail..." />
              {errors.message && <p className="text-[10px] text-error flex items-center gap-1 mt-1"><FiAlertCircle size={12} />{errors.message}</p>}
            </div>

            <button type="submit" disabled={sending} className="flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 disabled:opacity-50 transition-all">
              <FiSend size={16} /> {sending ? 'Envoi en cours...' : 'Envoyer le message'}
            </button>
          </form>
        </div>

        <div className="space-y-4">
          <div className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm border border-outline-variant/10">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center">
                <FiMail className="text-primary" size={16} />
              </div>
              <div>
                <h3 className="text-sm font-bold text-primary">Email</h3>
                <p className="text-xs text-on-surface-variant">support@uac-presence.bj</p>
              </div>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center">
                <FiMessageSquare className="text-primary" size={16} />
              </div>
              <div>
                <h3 className="text-sm font-bold text-primary">Chat en direct</h3>
                <p className="text-xs text-on-surface-variant">Lun-Ven 8h-18h</p>
              </div>
            </div>
          </div>

          <div className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm border border-outline-variant/10">
            <h3 className="text-sm font-bold text-primary mb-2">Délais de réponse</h3>
            <div className="space-y-2 text-xs text-on-surface-variant">
              <div className="flex justify-between"><span>Critique</span><span className="font-semibold text-secondary">&lt; 4h</span></div>
              <div className="flex justify-between"><span>Haute</span><span className="font-semibold text-primary">&lt; 12h</span></div>
              <div className="flex justify-between"><span>Normale</span><span className="font-semibold">24-48h</span></div>
              <div className="flex justify-between"><span>Basse</span><span className="font-semibold">3-5 jours</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
