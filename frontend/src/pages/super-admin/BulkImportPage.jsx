import { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  MdCloudUpload, MdArrowBack, MdCheck, MdWarning,
  MdDownload, MdInsertDriveFile,
} from 'react-icons/md';
import api from '../../api/axios';

export default function BulkImportPage() {
  const navigate = useNavigate();
  const fileInputRef = useRef(null);
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');

  const handleDrop = (e) => {
    e.preventDefault();
    const droppedFile = e.dataTransfer.files[0];
    if (droppedFile && (droppedFile.type === 'text/csv' || droppedFile.name.endsWith('.csv'))) {
      setFile(droppedFile);
      setError('');
    } else {
      setError('Veuillez sélectionner un fichier CSV valide.');
    }
  };

  const handleFileSelect = (e) => {
    const selected = e.target.files[0];
    if (selected) {
      setFile(selected);
      setError('');
    }
  };

  const handleUpload = async () => {
    if (!file) return;

    setLoading(true);
    setError('');
    setResult(null);

    const formData = new FormData();
    formData.append('file', file);

    try {
      const { data } = await api.post('/super-admin/etablissements/import', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      if (data.success) {
        setResult({
          created: data.data?.created ?? 0,
          total: data.data?.total ?? 0,
          errors: data.data?.errors ?? [],
        });
      }
    } catch (err) {
      const msg = err.response?.data?.message || 'Erreur lors de l\'import.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const resetForm = () => {
    setFile(null);
    setResult(null);
    setError('');
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  return (
    <div className="max-w-3xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex items-center gap-4">
        <button
          onClick={() => navigate('/super-admin')}
          className="p-2 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors"
        >
          <MdArrowBack size={18} />
        </button>
        <div>
          <h1 className="text-2xl font-bold text-[#011549] font-headline">Import en masse</h1>
          <p className="text-sm text-slate-500 mt-1">Créer plusieurs facultés à partir d'un fichier CSV</p>
        </div>
      </div>

      {/* Instructions */}
      <div className="bg-blue-50 border border-blue-200 rounded-2xl p-6 space-y-3">
        <h2 className="text-sm font-semibold text-blue-800">Format du fichier CSV</h2>
        <div className="text-sm text-blue-700 space-y-1">
          <p>Colonnes requises : <code className="bg-blue-100 px-1.5 py-0.5 rounded text-xs font-mono">code</code>, <code className="bg-blue-100 px-1.5 py-0.5 rounded text-xs font-mono">nom</code>, <code className="bg-blue-100 px-1.5 py-0.5 rounded text-xs font-mono">email</code></p>
          <p>Colonnes optionnelles : <code className="bg-blue-100 px-1.5 py-0.5 rounded text-xs font-mono">telephone</code>, <code className="bg-blue-100 px-1.5 py-0.5 rounded text-xs font-mono">adresse</code></p>
          <p>Un compte administrateur sera automatiquement créé pour chaque faculté.</p>
        </div>
        <a
          href="#"
          onClick={(e) => {
            e.preventDefault();
            // Download template CSV
            const csvContent = 'code,nom,email,telephone,adresse\nFAST,Faculté des Sciences et Techniques,contact@fast.uac.bj,+229 01 23 45 67,Abomey-Calavi\nFDS,Faculté des Sciences,contact@fds.uac.bj,+229 01 23 45 68,Abomey-Calavi\nEPAC,École Polytechnique d\'Abomey-Calavi,contact@epac.uac.bj,+229 01 23 45 69,Abomey-Calavi';
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'facultes_template.csv';
            a.click();
            URL.revokeObjectURL(url);
          }}
          className="inline-flex items-center gap-1.5 text-sm text-blue-700 font-medium hover:underline"
        >
          <MdDownload size={14} />
          Télécharger le modèle CSV
        </a>
      </div>

      {/* Error */}
      {error && (
        <div className="flex items-start gap-3 p-4 bg-red-50 text-red-700 rounded-xl border border-red-200">
          <MdWarning className="text-lg shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-medium">Erreur</p>
            <p className="text-sm mt-0.5">{error}</p>
          </div>
        </div>
      )}

      {/* Result */}
      {result && (
        <div className="space-y-4">
          <div className={`flex items-start gap-3 p-4 rounded-xl border ${
            result.errors.length === 0
              ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
              : 'bg-amber-50 text-amber-700 border-amber-200'
          }`}>
            <MdCheck className="text-lg shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-medium">
                {result.created} / {result.total} faculté(s) créée(s) avec succès.
              </p>
              {result.errors.length > 0 && (
                <p className="text-xs mt-1">{result.errors.length} ligne(s) en erreur.</p>
              )}
            </div>
          </div>

          {result.errors.length > 0 && (
            <div className="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
              <h3 className="text-sm font-semibold text-red-600 mb-3">Erreurs d'import</h3>
              <div className="space-y-2">
                {result.errors.map((err, i) => (
                  <div key={i} className="flex items-start gap-2 text-sm text-slate-600">
                    <span className="text-red-400 font-mono text-xs mt-0.5">Ligne {err.row || '?'}</span>
                    <span>{err.message || err.error || 'Erreur inconnue'}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          <button
            onClick={() => { resetForm(); navigate('/super-admin/etablissements'); }}
            className="px-4 py-2.5 bg-[#011549] text-white rounded-xl text-sm font-semibold hover:bg-[#011549]/90 transition-colors"
          >
            Voir la liste des facultés
          </button>
        </div>
      )}

      {/* Zone d'upload */}
      {!result && (
        <div
          onDragOver={(e) => e.preventDefault()}
          onDrop={handleDrop}
          onClick={() => fileInputRef.current?.click()}
          className={`bg-white rounded-2xl shadow-sm border-2 border-dashed p-12 text-center cursor-pointer transition-all ${
            file ? 'border-emerald-300 bg-emerald-50/30' : 'border-slate-200 hover:border-[#011549]/30 hover:bg-slate-50'
          }`}
        >
          <input
            ref={fileInputRef}
            type="file"
            accept=".csv"
            onChange={handleFileSelect}
            className="hidden"
          />
          {file ? (
            <div className="space-y-3">
              <MdInsertDriveFile size={40} className="mx-auto text-emerald-500" />
              <p className="text-sm font-medium text-[#011549]">{file.name}</p>
              <p className="text-xs text-slate-400">{(file.size / 1024).toFixed(1)} Ko</p>
              <button
                onClick={(e) => { e.stopPropagation(); resetForm(); }}
                className="text-xs text-red-500 hover:underline"
              >
                Supprimer
              </button>
            </div>
          ) : (
            <div className="space-y-3">
              <MdCloudUpload size={48} className="mx-auto text-slate-300" />
              <p className="text-sm text-slate-500">
                Glissez-déposez votre fichier CSV ici, ou <span className="text-blue-600 font-medium">cliquez pour parcourir</span>
              </p>
              <p className="text-xs text-slate-400">Taille max : 2 Mo</p>
            </div>
          )}
        </div>
      )}

      {/* Upload button */}
      {file && !result && (
        <div className="flex items-center gap-3">
          <button
            onClick={handleUpload}
            disabled={loading}
            className="px-6 py-3 bg-[#011549] text-white rounded-xl font-semibold text-sm hover:bg-[#011549]/90 transition-colors disabled:opacity-50 flex items-center gap-2"
          >
            {loading ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                Import en cours...
              </>
            ) : (
              <>
                <MdCloudUpload size={16} />
                Importer le fichier
              </>
            )}
          </button>
          <button
            onClick={resetForm}
            className="px-6 py-3 border border-slate-200 text-slate-600 rounded-xl font-medium text-sm hover:bg-slate-50 transition-colors"
            disabled={loading}
          >
            Annuler
          </button>
        </div>
      )}
    </div>
  );
}
