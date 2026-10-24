import { useState, useRef } from 'react';
import { MdCloudUpload, MdCheck, MdWarning, MdDownload, MdInsertDriveFile } from 'react-icons/md';
import Modal from '../ui/Modal';
import { importerEtablissementsCsv } from '../../api/resources/etablissements';
import cn from '../../utils/cn';

const MODELE_CSV =
  'code,nom,email,telephone,adresse\n' +
  'FAST,Faculté des Sciences et Techniques,contact@fast.uac.bj,+229 01 23 45 67,Abomey-Calavi\n' +
  'FDS,Faculté des Sciences,contact@fds.uac.bj,+229 01 23 45 68,Abomey-Calavi\n' +
  "EPAC,École Polytechnique d'Abomey-Calavi,contact@epac.uac.bj,+229 01 23 45 69,Abomey-Calavi";

export default function ImporterFacultesModal({ isOpen, onClose, onImported }) {
  const fileInputRef = useRef(null);
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');

  const reinitialiser = () => {
    setFile(null);
    setResult(null);
    setError('');
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const fermer = () => {
    onClose();
    // Après la fermeture, pas avant : sinon la zone de dépôt se vide sous les
    // yeux de l'utilisateur pendant que le panneau glisse encore.
    setTimeout(reinitialiser, 200);
  };

  const handleDrop = (e) => {
    e.preventDefault();
    const dropped = e.dataTransfer.files[0];
    if (dropped && (dropped.type === 'text/csv' || dropped.name.endsWith('.csv'))) {
      setFile(dropped);
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
      const data = await importerEtablissementsCsv(formData);
      if (data.success) {
        setResult({
          created: data.data?.created ?? 0,
          total: data.data?.total ?? 0,
          errors: data.data?.errors ?? [],
        });
        onImported?.();
      }
    } catch (err) {
      setError(err.response?.data?.message || "Erreur lors de l'import.");
    } finally {
      setLoading(false);
    }
  };

  const telechargerModele = () => {
    const blob = new Blob([MODELE_CSV], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'facultes_template.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <Modal isOpen={isOpen} onClose={fermer} title="Importer des facultés" size="md">
      <div className="space-y-5">
        <div className="space-y-2 rounded-xl border border-primary/10 bg-primary/5 p-4">
          <p className="text-sm font-semibold text-primary">Format du fichier CSV</p>
          <div className="space-y-1 text-sm text-on-surface-variant">
            <p>
              Colonnes requises :{' '}
              <code className="rounded bg-surface-container-high px-1.5 py-0.5 font-mono text-xs text-primary">
                code
              </code>
              ,{' '}
              <code className="rounded bg-surface-container-high px-1.5 py-0.5 font-mono text-xs text-primary">
                nom
              </code>
              ,{' '}
              <code className="rounded bg-surface-container-high px-1.5 py-0.5 font-mono text-xs text-primary">
                email
              </code>
            </p>
            <p>
              Colonnes optionnelles :{' '}
              <code className="rounded bg-surface-container-high px-1.5 py-0.5 font-mono text-xs text-primary">
                telephone
              </code>
              ,{' '}
              <code className="rounded bg-surface-container-high px-1.5 py-0.5 font-mono text-xs text-primary">
                adresse
              </code>
            </p>
            <p>Un compte administrateur sera automatiquement créé pour chaque faculté.</p>
          </div>
          <button
            type="button"
            onClick={telechargerModele}
            className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
          >
            <MdDownload size={14} />
            Télécharger le modèle CSV
          </button>
        </div>

        {error && (
          <div className="flex items-start gap-2.5 rounded-xl border border-error/10 bg-statut-erreur-fond px-4 py-3 text-sm text-statut-erreur">
            <MdWarning className="mt-0.5 shrink-0" size={16} />
            {error}
          </div>
        )}

        {result ? (
          <div className="space-y-4">
            <div
              className={cn(
                'flex items-start gap-3 rounded-xl border p-4',
                result.errors.length === 0
                  ? 'border-success/20 bg-statut-succes-fond text-statut-succes'
                  : 'border-warning/20 bg-statut-alerte-fond text-statut-alerte',
              )}
            >
              <MdCheck className="mt-0.5 shrink-0 text-lg" />
              <div>
                <p className="text-sm font-medium">
                  {result.created} / {result.total} faculté(s) créée(s) avec succès.
                </p>
                {result.errors.length > 0 && <p className="mt-1 text-xs">{result.errors.length} ligne(s) en erreur.</p>}
              </div>
            </div>

            {result.errors.length > 0 && (
              <div className="space-y-2 rounded-xl border border-outline-variant/20 bg-surface-container-high p-4">
                {result.errors.map((err, i) => (
                  <div key={i} className="flex items-start gap-2 text-sm text-on-surface-variant">
                    <span className="mt-0.5 shrink-0 font-mono text-xs text-error/70">Ligne {err.row || '?'}</span>
                    <span>{err.message || err.error || 'Erreur inconnue'}</span>
                  </div>
                ))}
              </div>
            )}

            <button
              type="button"
              onClick={fermer}
              className="w-full rounded-xl bg-gradient-to-br from-marque to-marque-fonce py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-transform hover:scale-[0.99] active:scale-[0.98]"
            >
              Fermer
            </button>
          </div>
        ) : (
          <>
            <div
              onDragOver={(e) => e.preventDefault()}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
              className={cn(
                'cursor-pointer rounded-xl border-2 border-dashed p-10 text-center transition-colors',
                file
                  ? 'border-success/40 bg-success/5'
                  : 'border-outline-variant/30 hover:border-primary/40 hover:bg-primary/5',
              )}
            >
              <input
                ref={fileInputRef}
                type="file"
                accept=".csv"
                aria-label="Choisir le fichier CSV des facultés à importer"
                onChange={handleFileSelect}
                className="hidden"
              />
              {file ? (
                <div className="space-y-2">
                  <MdInsertDriveFile size={36} className="mx-auto text-success" />
                  <p className="text-sm font-medium text-primary">{file.name}</p>
                  <p className="text-xs text-on-surface-variant">{(file.size / 1024).toFixed(1)} Ko</p>
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      reinitialiser();
                    }}
                    className="text-xs text-statut-erreur hover:underline"
                  >
                    Supprimer
                  </button>
                </div>
              ) : (
                <div className="space-y-2">
                  <MdCloudUpload size={40} className="mx-auto text-on-surface-variant/40" />
                  <p className="text-sm text-on-surface-variant">
                    Glissez-déposez votre fichier CSV ici, ou{' '}
                    <span className="font-medium text-primary">cliquez pour parcourir</span>
                  </p>
                  <p className="text-xs text-on-surface-variant">Taille max : 2 Mo</p>
                </div>
              )}
            </div>

            {file && (
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={handleUpload}
                  disabled={loading}
                  className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-marque to-marque-fonce py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-transform hover:scale-[0.99] active:scale-[0.98] disabled:opacity-60"
                >
                  {loading ? (
                    <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  ) : (
                    <MdCloudUpload size={16} />
                  )}
                  Importer le fichier
                </button>
                <button
                  type="button"
                  onClick={reinitialiser}
                  disabled={loading}
                  className="rounded-xl px-5 py-3 text-sm font-semibold text-on-surface-variant transition-colors hover:bg-surface-container-high disabled:opacity-50"
                >
                  Annuler
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </Modal>
  );
}
