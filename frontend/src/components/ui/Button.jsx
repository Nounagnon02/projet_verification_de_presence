import { Fragment, forwardRef } from 'react';

const Button = forwardRef(({
  children,
  variant = 'primary',
  size = 'md',
  className = '',
  asChild = false,
  'aria-label': ariaLabel,
  'aria-expanded': ariaExpanded,
  'aria-controls': ariaControls,
  'aria-pressed': ariaPressed,
  ...props
}, ref) => {
  const Component = asChild ? Fragment : 'button';

  const baseClasses = 'flex items-center justify-center gap-2 font-medium rounded-lg transition-all disabled:opacity-50 disabled:pointer-events-none focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface';

  const variantClasses = {
    primary: 'bg-primary text-primary-font hover:bg-primary/90 active:bg-primary focus-visible:ring-primary/30',
    secondary: 'bg-secondary text-on-secondary hover:bg-secondary/90 active:bg-secondary focus-visible:ring-secondary/30',
    destructive: 'bg-error text-on-error hover:bg-error/90 active:bg-error focus-visible:ring-error/30',
    outline: 'border border-outline-variant/20 hover:bg-surface-container-low active:bg-surface-container focus-visible:ring-outline-variant/30',
    ghost: 'hover:bg-surface-container-low active:bg-surface-container focus-visible:ring-surface-variant/30',
    link: 'text-primary underline-offset-4 hover:underline focus-visible:ring-primary/30',
  };

  const sizeClasses = {
    sm: 'text-sm px-3 py-2',
    md: 'text-base px-4 py-3',
    lg: 'text-lg px-5 py-4',
    icon: 'p-2',
  };

  return (
    <Component
      ref={ref}
      className={`${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}
      aria-label={ariaLabel}
      aria-expanded={ariaExpanded}
      aria-controls={ariaControls}
      aria-pressed={ariaPressed}
      {...props}
    >
      {children}
    </Component>
  );
});

Button.displayName = 'Button';

export default Button;