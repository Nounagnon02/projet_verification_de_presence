import { useState, useRef, useCallback } from 'react';
import { FiUploadCloud, FiFile, FiCheck, FiX } from 'react-icons/fi';
import cn from '../../utils/cn';

export default function FileDropZone({ onFileSelect, accept = '.csv,.pdf', maxSize = 5, label = 'CSV', 'aria-label': ariaLabel, 'aria-describedby': ariaDescribedBy }) {
  const [dragOver, setDragOver] = useState(false);
  const [file, setFile] = useState(null);
  const [error, setError] = useState('');
  const inputRef = useRef(null);
  const dropZoneRef = useRef(null);

  const validateAndSet = useCallback((f) => {
    if (!f) return;
    setError('');
    if (f.size > maxSize * 1024 * 1024) {
      setError(`Le fichier ne doit pas dépasser ${maxSize} Mo`);
      return;
    }
    setFile(f);
    onFileSelect?.(f);
  }, [maxSize, onFileSelect]);

  const handleDrop = useCallback((e) => {
    e.preventDefault();
    setDragOver(false);
    const f = e.dataTransfer.files[0];
    validateAndSet(f);
  }, [validateAndSet]);

  const handleChange = useCallback((e) => {
    const f = e.target.files[0];
    validateAndSet(f);
  }, [validateAndSet]);

  const remove = useCallback(() => {
    setFile(null);
    setError('');
    onFileSelect?.(null);
    if (inputRef.current) inputRef.current.value = '';
  }, [onFileSelect]);

  const handleKeyDown = useCallback((e) => {
    if ((e.key === 'Enter' || e.key === ' ') && !file) {
      e.preventDefault();
      inputRef.current?.click();
    }
  }, [file]);

  return (
    <div
      ref={dropZoneRef}
      onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
      onDragLeave={() => setDragOver(false)}
      onDrop={handleDrop}
      onClick={() => !file && inputRef.current?.click()}
      onKeyDown={handleKeyDown}
      tabIndex={!file ? 0 : undefined}
      role={!file ? 'button' : undefined}
      aria-label={ariaLabel || `Zone de dépôt de fichier ${label}`}
      aria-describedby={ariaDescribedBy || (error ? 'dropzone-error' : undefined)}
      className={cn(
        'border-2 border-dashed rounded-xxl p-8 text-center cursor-pointer transition-all',
        dragOver ? 'border-primary bg-primary/5' : file ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-outline-variant/50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2',
      )}
    >
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        onChange={handleChange}
        className="hidden"
        aria-hidden="true"
        tabIndex={-1}
      />
      {file ? (
        <div className="flex items-center justify-center gap-3" role="status" aria-live="polite">
          <div className="p-2 bg-primary/10 rounded-xl" aria-hidden="true">
            <FiCheck className="text-primary" size={20} />
          </div>
          <div className="text-left">
            <p className="text-sm font-medium text-on-surface flex items-center gap-2">
              <FiFile size={16} aria-hidden="true" /> {file.name}
            </p>
            <p className="text-xs text-on-surface-variant">{(file.size / 1024 / 1024).toFixed(2)} Mo</p>
          </div>
          <button
            onClick={(e) => { e.stopPropagation(); remove(); }}
            className="p-1.5 hover:bg-surface-container-high rounded-lg"
            aria-label={`Supprimer ${file.name}`}
          >
            <FiX size={16} className="text-on-surface-variant" aria-hidden="true" />
          </button>
        </div>
      ) : (
        <>
          <div className="p-3 bg-surface-container-high rounded-2xl inline-flex mb-3" aria-hidden="true">
            <FiUploadCloud size={28} className="text-on-surface-variant" />
          </div>
          <p className="text-sm text-on-surface-variant mb-1">
            <span className="text-primary font-medium">Cliquez</span> ou glissez-déposez
          </p>
          <p id="dropzone-error" className="text-xs text-on-surface-variant/60" aria-live={error ? 'assertive' : 'off'}>
            Fichier {label} ({accept}) — Max {maxSize} Mo
            {error && <span className="text-error"> — {error}</span>}
          </p>
        </>
      )}
    </div>
  );
}
