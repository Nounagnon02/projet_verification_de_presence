import cn from '../../utils/cn';

const variants = {
  success: 'bg-statut-succes-fond text-statut-succes',
  error: 'bg-statut-erreur-fond text-statut-erreur',
  warning: 'bg-statut-alerte-fond text-statut-alerte',
  info: 'bg-statut-info-fond text-statut-info',
  neutral: 'bg-surface-container-high text-on-surface-variant',
};

export default function Badge({ children, variant = 'neutral', className = '' }) {
  return (
    <span
      className={cn(
        'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold tracking-wide',
        variants[variant],
        className,
      )}
    >
      {children}
    </span>
  );
}
