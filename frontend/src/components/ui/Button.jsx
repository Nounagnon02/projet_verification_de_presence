import { Fragment } from 'react';

const Button = ({
  children,
  variant = 'primary',
  size = 'md',
  className = '',
  asChild = false,
  ...props
}) => {
  const Component = asChild ? Fragment : 'button';

  const baseClasses = 'flex items-center justify-center gap-2 font-medium rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none';

  const variantClasses = {
    primary: 'bg-primary text-primary-font hover:bg-primary/90 focus:ring-primary/20',
    secondary: 'bg-secondary text-on-secondary hover:bg-secondary/90 focus:ring-secondary/20',
    destructive: 'bg-error text-on-error hover:bg-error/90 focus:ring-error/20',
    outline: 'border border-outline-variant/20 hover:bg-surface-container-low focus:ring-outline-variant/20',
    ghost: 'hover:bg-surface-container-low focus:ring-surface-variant/20',
    link: 'text-primary underline-offset-4 hover:underline focus:ring-primary/20',
  };

  const sizeClasses = {
    sm: 'text-sm px-3 py-2',
    md: 'text-base px-4 py-3',
    lg: 'text-lg px-5 py-4',
    icon: 'p-2',
  };

  return (
    <Component
      className={`${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}
      {...props}
    >
      {children}
    </Component>
  );
};

export default Button;