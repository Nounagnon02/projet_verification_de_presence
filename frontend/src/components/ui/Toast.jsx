import { FiCheckCircle, FiAlertCircle, FiAlertTriangle, FiInfo, FiX } from 'react-icons/fi';
import cn from '../../utils/cn';

const icons = {
  success: FiCheckCircle,
  error: FiAlertCircle,
  warning: FiAlertTriangle,
  info: FiInfo,
};

const colors = {
  success: 'bg-[#E8F5E9] border-l-4 border-[#2E7D32] text-[#2E7D32]',
  error: 'bg-[#FFEBEE] border-l-4 border-[#C62828] text-[#C62828]',
  warning: 'bg-[#FFF8E1] border-l-4 border-[#F57F17] text-[#F57F17]',
  info: 'bg-[#E3F2FD] border-l-4 border-[#1565C0] text-[#1565C0]',
};

const liveRegions = {
  success: 'polite',
  error: 'assertive',
  warning: 'assertive',
  info: 'polite',
};

export default function Toaster({ toasts, onRemove }) {
  if (!toasts || toasts.length === 0) return null;

  return (
    <div className="fixed top-4 right-4 z-[100] flex flex-col gap-2 max-w-sm w-full pointer-events-none" aria-live="polite" aria-atomic="true">
      {toasts.map(toast => {
        const Icon = icons[toast.type] || FiInfo;
        const region = liveRegions[toast.type] || 'polite';
        return (
          <div
            key={toast.id}
            className={cn(
              'pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-lg animate-in slide-in-from-right-2 fade-in duration-200',
              colors[toast.type] || colors.info,
            )}
            role="alert"
            aria-live={region}
            aria-atomic="true"
          >
            <Icon size={18} className="mt-0.5 shrink-0" aria-hidden="true" />
            <p className="text-sm flex-1">{toast.message}</p>
            <button
              onClick={() => onRemove(toast.id)}
              className="shrink-0 p-0.5 hover:opacity-70 focus:outline-none focus:ring-2 focus:ring-current focus:ring-offset-2 rounded"
              aria-label={`Fermer la notification: ${toast.message}`}
            >
              <FiX size={14} aria-hidden="true" />
            </button>
          </div>
        );
      })}
    </div>
  );
}
