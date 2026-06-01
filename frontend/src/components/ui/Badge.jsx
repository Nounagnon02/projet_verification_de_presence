import cn from '../../utils/cn';

const variants = {
  success: 'bg-[#E8F5E9] text-[#2E7D32]',
  error: 'bg-[#FFEBEE] text-[#C62828]',
  warning: 'bg-[#FFF8E1] text-[#F57F17]',
  info: 'bg-[#E3F2FD] text-[#1565C0]',
  neutral: 'bg-surface-container-high text-on-surface-variant',
};

export default function Badge({ children, variant = 'neutral', className = '' }) {
  return (
    <span className={cn(
      'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold tracking-wide',
      variants[variant],
      className,
    )}>
      {children}
    </span>
  );
}
