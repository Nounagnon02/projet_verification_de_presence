import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  MdArrowBack, MdBusiness, MdEmail, MdPhone, MdLocationOn,
  MdEdit, MdDelete, MdRefresh, MdContentCopy, MdCheck, MdWarning,
} from 'react-icons/md';
import api from '../../api/axios';

export default function EtablissementDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [etablissement, setEtablissement] = useState(null);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(false);
  const [form, setForm] = useState({});
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState({ type: '', text: '' });

  const fetchData = async () => {
    setLoading(true);
    try {
      const [etabRes, statsRes] = await Promise.all([
        api.get(`/super-admin/etablissements/${id}`),
        api.get(`/super-admin/etablissements/${id}/stats`),
      ]);
      if (etabRes.data.success) {
        setEtablissement(etabRes.data.data);
        setForm({
          code: etabRes.data.data.code || '',
          nom: etabRes.data.data.nom || '',
          email: etabRes.data.data.email || '',
          telephone: etabRes.data.data.telephone || '',
          adresse: etabRes.data.data.adresse || '',
        });
      }
      if (statsRes.data.success) {
        setStats(statsRes.data.data);
      }
    } catch (err) {
      console.error('Erreur chargement:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchData(); }, [id]);

  const handleSave = async () => {
    setSaving(true);
    setMessage({ type: '', text: '' });
    try {
      const { data } = await api.put(`/super-admin/etablissements/${id}`, form);
      if (data.success) {
        setEtablissement(data.data);
        setEditing(false);
        setMessage({ type: 'success', text: 'Faculté mise à jour avec succès.' });
      }
    } catch (err) {
      const msg = err.response?.data?.message
        || err.response?.data?.errors
          ? Object.values(err.response.data.errors).flat().join(', ')
          : 'Erreur lors de la mise à jour.';
      setMessage({ type: 'error', text: msg });
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer cette faculté ? Cette action est irréversible.')) return;
    try {
      await api.delete(`/super-admin/etablissements/${id}`);
      navigate('/super-admin/etablissements');
    } catch (err) {
      setMessage({ type: 'error', text: 'Erreur lors de la suppression.' });
    }
  };

  const handleResendCredentials = async () => {
    try {
      const { data } = await api.post(`/super-admin/etablissements/${id}/resend-credentials`);
      if (data.success) {
        setMessage({ type: 'success', text: 'Identifiants renvoyés par email.' });
      }
    } catch (err) {
      setMessage({ type: 'error', text: "Erreur lors de l'envoi des identifiants." });
    }
  };

  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
      setMessage({ type: 'success', text: 'Copié !' });
      setTimeout(() => setMessage({ type: '', text: '' }), 2000);
    });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  if (!etablissement) {
    return (
      <div className="text-center py-16">
        <p className="text-slate-500">Faculté introuvable.</p>
        <button onClick={() => navigate('/super-admin/etablissements')} className="text-blue-600 text-sm mt-2 hover:underline">
          Retour à la liste
        </button>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex items-center gap-4">
        <button
          onClick={() => navigate('/super-admin/etablissements')}
          className="p-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors"
        >
          <MdArrowBack size={18} />
        </button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold text-[#011549] font-headline">{etablissement.nom}</h1>
          <p className="text-sm text-slate-500 mt-1">Code: {etablissement.code}</p>
        </div>
        <button onClick={fetchData} className="p-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors" title="Rafraîchir">
          <MdRefresh size={18} />
        </button>
        <button onClick={handleDelete} className="p-2 rounded-xl border border-red-200 text-red-500 hover:bg-red-50 transition-colors" title="Supprimer">
          <MdDelete size={18} />
        </button>
      </div>

      {/* Message */}
      {message.text && (
        <div className={`flex items-start gap-3 p-4 rounded-xl border ${
          message.type === 'success'
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
            : 'bg-red-50 text-red-700 border-red-200'
        }`}>
          {message.type === 'success' ? <MdCheck className="text-lg shrink-0 mt-0.5" /> : <MdWarning className="text-lg shrink-0 mt-0.5" />}
          <p className="text-sm">{message.text}</p>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Infos */}
        <div className="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-6">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-semibold text-[#011549]">Informations</h2>
            <button
              onClick={() => setEditing(!editing)}
              className="flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-800"
            >
              <MdEdit size={14} />
              {editing ? 'Annuler' : 'Modifier'}
            </button>
          </div>

          {editing ? (
            <div className="space-y-4">
              <div>
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">Code</label>
                <input
                  value={form.code}
                  onChange={(e) => setForm({ ...form, code: e.target.value })}
                  className="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20"
                />
              </div>
              <div>
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">Nom</label>
                <input
                  value={form.nom}
                  onChange={(e) => setForm({ ...form, nom: e.target.value })}
                  className="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20"
                />
              </div>
              <div>
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">Email</label>
                <input
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  className="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20"
                />
              </div>
              <div>
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">Téléphone</label>
                <input
                  value={form.telephone}
                  onChange={(e) => setForm({ ...form, telephone: e.target.value })}
                  className="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20"
                />
              </div>
              <div>
                <label className="text-xs font-semibold uppercase tracking-wider text-slate-500">Adresse</label>
                <textarea
                  value={form.adresse}
                  onChange={(e) => setForm({ ...form, adresse: e.target.value })}
                  rows={2}
                  className="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20 resize-none"
                />
              </div>
              <button
                onClick={handleSave}
                disabled={saving}
                className="px-4 py-2 bg-[#011549] text-white rounded-lg text-sm font-semibold hover:bg-[#011549]/90 transition-colors disabled:opacity-50 flex items-center gap-2"
              >
                {saving && <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>}
                Enregistrer
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="flex items-start gap-3">
                <MdBusiness className="text-slate-400 mt-0.5" />
                <div>
                  <p className="text-xs text-slate-400">Code</p>
                  <p className="text-sm font-mono text-[#011549]">{etablissement.code}</p>
                </div>
              </div>
              <div className="flex items-start gap-3">
                <MdEmail className="text-slate-400 mt-0.5" />
                <div>
                  <p className="text-xs text-slate-400">Email</p>
                  <p className="text-sm text-[#011549]">{etablissement.email}</p>
                </div>
              </div>
              {etablissement.telephone && (
                <div className="flex items-start gap-3">
                  <MdPhone className="text-slate-400 mt-0.5" />
                  <div>
                    <p className="text-xs text-slate-400">Téléphone</p>
                    <p className="text-sm text-[#011549]">{etablissement.telephone}</p>
                  </div>
                </div>
              )}
              {etablissement.adresse && (
                <div className="flex items-start gap-3">
                  <MdLocationOn className="text-slate-400 mt-0.5" />
                  <div>
                    <p className="text-xs text-slate-400">Adresse</p>
                    <p className="text-sm text-[#011549]">{etablissement.adresse}</p>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Stats & Actions */}
        <div className="space-y-6">
          {/* Stats */}
          <div className="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
            <h2 className="text-base font-semibold text-[#011549]">Statistiques</h2>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-sm text-slate-500">Filières</span>
                <span className="text-sm font-semibold text-[#011549]">{stats?.filieres_count ?? 0}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-slate-500">Étudiants</span>
                <span className="text-sm font-semibold text-[#011549]">{stats?.etudiants_count ?? 0}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-slate-500">Administrateurs</span>
                <span className="text-sm font-semibold text-[#011549]">{stats?.admins_count ?? 0}</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-slate-500">Statut</span>
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                  etablissement.actif ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'
                }`}>
                  {etablissement.actif ? 'Actif' : 'Inactif'}
                </span>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-3">
            <h2 className="text-base font-semibold text-[#011549]">Actions</h2>
            <button
              onClick={handleResendCredentials}
              className="w-full px-4 py-2.5 border border-blue-200 text-blue-600 rounded-xl text-sm font-medium hover:bg-blue-50 transition-colors"
            >
              Renvoyer les identifiants
            </button>
            <button
              onClick={() => copyToClipboard(etablissement.email)}
              className="w-full px-4 py-2.5 border border-slate-200 text-slate-600 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors flex items-center justify-center gap-2"
            >
              <MdContentCopy size={14} />
              Copier l'email
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
