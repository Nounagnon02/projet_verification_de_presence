import { useState, useRef } from 'react';
import { FiUploadCloud, FiFile, FiCheck, FiX } from 'react-icons/fi';
import cn from '../../utils/cn';

export default function FileDropZone({ onFileSelect, accept = '.csv,.pdf', maxSize = 5, label = 'CSV' }) {
  const [dragOver, setDragOver] = useState(false);
  const [file, setFile] = useState(null);
  const inputRef = useRef();

  const handleDrop = (e) => {
    e.preventDefault();
    setDragOver(false);
    const f = e.dataTransfer.files[0];
    validateAndSet(f);
  };

  const handleChange = (e) => {
    const f = e.target.files[0];
    validateAndSet(f);
  };

  const validateAndSet = (f) => {
    if (!f) return;
    if (f.size > maxSize * 1024 * 1024) {
      alert(`Le fichier ne doit pas dépasser ${maxSize} Mo`);
      return;
    }
    setFile(f);
    onFileSelect?.(f);
  };

  const remove = () => {
    setFile(null);
    onFileSelect?.(null);
    if (inputRef.current) inputRef.current.value = '';
  };

  return (
    <div
      onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
      onDragLeave={() => setDragOver(false)}
      onDrop={handleDrop}
      onClick={() => !file && inputRef.current?.click()}
      className={cn(
        'border-2 border-dashed rounded-xxl p-8 text-center cursor-pointer transition-all',
        dragOver ? 'border-primary bg-primary/5' : file ? 'border-primary bg-primary/5' : 'border-outline-variant/30 hover:border-outline-variant/50',
      )}
    >
      <input ref={inputRef} type="file" accept={accept} onChange={handleChange} className="hidden" />
      {file ? (
        <div className="flex items-center justify-center gap-3">
          <div className="p-2 bg-primary/10 rounded-xl">
            <FiCheck className="text-primary" size={20} />
          </div>
          <div className="text-left">
            <p className="text-sm font-medium text-on-surface flex items-center gap-2">
              <FiFile size={16} /> {file.name}
            </p>
            <p className="text-xs text-on-surface-variant">{(file.size / 1024 / 1024).toFixed(2)} Mo</p>
          </div>
          <button onClick={(e) => { e.stopPropagation(); remove(); }} className="p-1.5 hover:bg-surface-container-high rounded-lg">
            <FiX size={16} className="text-on-surface-variant" />
          </button>
        </div>
      ) : (
        <>
          <div className="p-3 bg-surface-container-high rounded-2xl inline-flex mb-3">
            <FiUploadCloud size={28} className="text-on-surface-variant" />
          </div>
          <p className="text-sm text-on-surface-variant mb-1">
            <span className="text-primary font-medium">Cliquez</span> ou glissez-déposez
          </p>
          <p className="text-xs text-on-surface-variant/60">Fichier {label} ({accept}) — Max {maxSize} Mo</p>
        </>
      )}
    </div>
  );
}
