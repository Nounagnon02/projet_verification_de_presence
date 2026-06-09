import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { MdAdd, MdSchool, MdSearch, MdChevronRight, MdRefresh } from 'react-icons/md';
import api from '../../api/axios';

export default function EtablissementManagementPage() {
  const [etablissements, setEtablissements] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const navigate = useNavigate();

  const fetchData = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/super-admin/etablissements');
      if (data.success) {
        setEtablissements(data.data || []);
      }
    } catch (err) {
      console.error('Erreur chargement facultés:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchData(); }, []);

  const filtered = etablissements.filter(
    (e) =>
      e.nom?.toLowerCase().includes(search.toLowerCase()) ||
      e.code?.toLowerCase().includes(search.toLowerCase()) ||
      e.email?.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-[#011549] font-headline">Facultés & Écoles</h1>
          <p className="text-sm text-slate-500 mt-1">Gérer les établissements de l'UAC</p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={fetchData}
            className="p-2.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors"
            title="Rafraîchir"
          >
            <MdRefresh size={18} />
          </button>
          <Link
            to="/super-admin/etablissements/create"
            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#011549] text-white text-sm font-semibold hover:bg-[#011549]/90 transition-colors"
          >
            <MdAdd size={16} />
            Ajouter une faculté
          </Link>
        </div>
      </div>

      {/* Search */}
      <div className="relative max-w-md">
        <MdSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
        <input
          type="text"
          placeholder="Rechercher par nom, code ou email..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#011549]/20 focus:border-[#011549] transition-all"
        />
      </div>

      {/* Loading */}
      {loading && (
        <div className="flex items-center justify-center py-16">
          <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
        </div>
      )}

      {/* Liste */}
      {!loading && (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          {filtered.length > 0 ? (
            <div className="divide-y divide-slate-50">
              {filtered.map((etablissement) => (
                <div
                  key={etablissement.id}
                  onClick={() => navigate(`/super-admin/etablissements/${etablissement.id}`)}
                  className="flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors cursor-pointer"
                >
                  <div className="flex items-center gap-4">
                    <div className="w-10 h-10 bg-[#011549]/5 rounded-xl flex items-center justify-center text-[#011549]">
                      <MdSchool size={20} />
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-[#011549]">{etablissement.nom}</p>
                      <div className="flex items-center gap-3 mt-0.5">
                        <span className="text-xs font-mono text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded">
                          {etablissement.code}
                        </span>
                        <span className="text-xs text-slate-400">{etablissement.email}</span>
                        {etablissement.telephone && (
                          <span className="text-xs text-slate-400">{etablissement.telephone}</span>
                        )}
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    {etablissement.actif ? (
                      <span className="text-xs bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-full font-medium">Actif</span>
                    ) : (
                      <span className="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full font-medium">Inactif</span>
                    )}
                    <MdChevronRight className="text-slate-400" />
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="px-6 py-12 text-center">
              <MdSchool size={40} className="mx-auto text-slate-200 mb-3" />
              <p className="text-sm text-slate-500 mb-2">Aucune faculté trouvée</p>
              {search ? (
                <p className="text-xs text-slate-400">Essayez de modifier votre recherche</p>
              ) : (
                <Link
                  to="/super-admin/etablissements/create"
                  className="text-sm text-blue-600 hover:underline font-medium"
                >
                  Ajouter la première faculté
                </Link>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
