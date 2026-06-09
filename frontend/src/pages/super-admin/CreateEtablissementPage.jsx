import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { MdArrowBack, MdBusiness, MdEmail, MdPhone, MdLocationOn, MdCheck, MdWarning } from 'react-icons/md';
import api from '../../api/axios';

export default function CreateEtablissementPage() {
  const navigate = useNavigate();
  const [form, setForm] = useState({
    code: '',
    nom: '',
    email: '',
    telephone: '',
    adresse: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    if (!form.code.trim() || !form.nom.trim() || !form.email.trim()) {
      setError('Le code, le nom et l\'email sont obligatoires.');
      return;
    }

    setLoading(true);
    try {
      const { data } = await api.post('/super-admin/etablissements', form);
      if (data.success) {
        const etab = data.data?.etablissement || data.data;
        const credentials = data.data?.credentials;
        setSuccess(
          `Faculté "${etab.nom}" créée avec succès !`
          + (credentials
            ? ` Email: ${credentials.email}, Mot de passe: ${credentials.password}`
            : '')
        );
        setTimeout(() => navigate('/super-admin/etablissements'), 3000);
      }
    } catch (err) {
      const msg = err.response?.data?.message
        || err.response?.data?.errors
          ? Object.values(err.response.data.errors).flat().join(', ')
          : 'Erreur lors de la création.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex items-center gap-4">
        <button
          onClick={() => navigate('/super-admin/etablissements')}
          className="p-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors"
        >
          <MdArrowBack size={18} />
        </button>
        <div>
          <h1 className="text-2xl font-bold text-[#011549] font-headline">Ajouter une faculté</h1>
          <p className="text-sm text-slate-500 mt-1">Créer un nouvel établissement et son compte administrateur</p>
        </div>
      </div>

      {/* Success */}
      {success && (
        <div className="flex items-start gap-3 p-4 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200">
          <MdCheck className="text-lg shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-medium">{success}</p>
            <p className="text-xs mt-1">Redirection vers la liste...</p>
          </div>
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="flex items-start gap-3 p-4 bg-red-50 text-red-700 rounded-xl border border-red-200">
          <MdWarning className="text-lg shrink-0 mt-0.5" />
          <p className="text-sm">{error}</p>
        </div>
      )}

      {/* Form */}
      <form onSubmit={handleSubmit} className="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 space-y-6">
        <div className="space-y-1.5">
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500" htmlFor="code">
            Code * <span className="text-xs font-normal normal-case tracking-normal text-slate-400">(ex: FAST, EPAC, FDS)</span>
          </label>
          <div className="relative">
            <MdBusiness className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              id="code"
              name="code"
              value={form.code}
              onChange={handleChange}
              className="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20 focus:border-[#011549] transition-all"
              placeholder="FAST"
              required
              disabled={loading}
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500" htmlFor="nom">
            Nom complet *
          </label>
          <div className="relative">
            <MdBusiness className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              id="nom"
              name="nom"
              value={form.nom}
              onChange={handleChange}
              className="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20 focus:border-[#011549] transition-all"
              placeholder="Faculté des Sciences et Techniques"
              required
              disabled={loading}
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500" htmlFor="email">
            Email de contact *
          </label>
          <div className="relative">
            <MdEmail className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              id="email"
              name="email"
              type="email"
              value={form.email}
              onChange={handleChange}
              className="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20 focus:border-[#011549] transition-all"
              placeholder="contact@fast.uac.bj"
              required
              disabled={loading}
            />
          </div>
          <p className="text-xs text-slate-400">Un compte administrateur sera créé avec cet email.</p>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500" htmlFor="telephone">
            Téléphone
          </label>
          <div className="relative">
            <MdPhone className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              id="telephone"
              name="telephone"
              value={form.telephone}
              onChange={handleChange}
              className="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20 focus:border-[#011549] transition-all"
              placeholder="+229 01 23 45 67"
              disabled={loading}
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500" htmlFor="adresse">
            Adresse
          </label>
          <div className="relative">
            <MdLocationOn className="absolute left-3.5 top-3 text-slate-400" />
            <textarea
              id="adresse"
              name="adresse"
              value={form.adresse}
              onChange={handleChange}
              rows={2}
              className="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20 focus:border-[#011549] transition-all resize-none"
              placeholder="Abomey-Calavi, Bénin"
              disabled={loading}
            />
          </div>
        </div>

        <div className="flex items-center gap-3 pt-2">
          <button
            type="submit"
            disabled={loading}
            className="px-6 py-3 bg-[#011549] text-white rounded-xl font-semibold text-sm hover:bg-[#011549]/90 transition-colors disabled:opacity-50 flex items-center gap-2"
          >
            {loading && <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>}
            Créer la faculté
          </button>
          <button
            type="button"
            onClick={() => navigate('/super-admin/etablissements')}
            className="px-6 py-3 border border-slate-200 text-slate-600 rounded-xl font-medium text-sm hover:bg-slate-50 transition-colors"
            disabled={loading}
          >
            Annuler
          </button>
        </div>
      </form>
    </div>
  );
}
