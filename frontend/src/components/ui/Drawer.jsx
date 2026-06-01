import { useEffect } from 'react';
import { FiX } from 'react-icons/fi';

export default function Drawer({ isOpen, onClose, title, children, side = 'right', size = 'md' }) {
  useEffect(() => {
    if (isOpen) document.body.style.overflow = 'hidden';
    else document.body.style.overflow = '';
    return () => { document.body.style.overflow = ''; };
  }, [isOpen]);

  useEffect(() => {
    const handleEsc = (e) => { if (e.key === 'Escape') onClose(); };
    if (isOpen) window.addEventListener('keydown', handleEsc);
    return () => window.removeEventListener('keydown', handleEsc);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const sizes = { sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl' };
  const sideClasses = side === 'right' ? 'right-0 translate-x-0' : 'left-0 -translate-x-0';

  return (
    <div className="fixed inset-0 z-50 flex">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className={`absolute top-0 h-full w-full ${sizes[size]} bg-surface shadow-[0_12px_32px_rgba(25,28,31,0.06)] animate-in ${side === 'right' ? 'slide-in-from-right' : 'slide-in-from-left'} duration-200 ${sideClasses}`}>
        <div className="flex items-center justify-between px-6 py-4 border-b border-outline-variant/10">
          <h2 className="text-lg font-bold text-primary font-headline">{title}</h2>
          <button onClick={onClose} className="p-1.5 hover:bg-surface-container-high rounded-xl transition-colors">
            <FiX className="text-on-surface-variant" size={18} />
          </button>
        </div>
        <div className="p-6 overflow-y-auto h-[calc(100%-60px)]">{children}</div>
      </div>
    </div>
  );
}
