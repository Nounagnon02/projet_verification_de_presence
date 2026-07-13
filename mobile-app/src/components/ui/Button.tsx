import { Pressable, Text, ActivityIndicator, type PressableProps } from 'react-native';

type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonProps extends PressableProps {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  children: string;
}

const variantBg: Record<ButtonVariant, string> = {
  primary: 'bg-primary active:bg-primary-container',
  secondary: 'bg-secondary active:bg-secondary-container',
  outline: 'border border-primary bg-transparent active:bg-surface-container',
  ghost: 'bg-transparent active:bg-surface-container',
  danger: 'bg-error active:bg-on-error-container',
};

const variantText: Record<ButtonVariant, string> = {
  primary: 'text-on-primary',
  secondary: 'text-on-secondary',
  outline: 'text-primary',
  ghost: 'text-primary',
  danger: 'text-on-error',
};

const sizeClasses: Record<ButtonSize, string> = {
  sm: 'px-3 py-1.5',
  md: 'px-5 py-2.5',
  lg: 'px-6 py-3.5',
};

const textSize: Record<ButtonSize, string> = {
  sm: 'text-sm',
  md: 'text-base',
  lg: 'text-lg',
};

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled = false,
  children,
  className,
  ...props
}: ButtonProps) {
  const isDisabled = disabled || loading;

  return (
    <Pressable
      disabled={isDisabled}
      className={`items-center justify-center rounded-lg ${variantBg[variant]} ${sizeClasses[size]} ${isDisabled ? 'opacity-50' : ''} ${className ?? ''}`}
      {...props}
    >
      {loading ? (
        <ActivityIndicator
          size="small"
          color={variant === 'outline' || variant === 'ghost' ? '#011549' : '#ffffff'}
        />
      ) : (
        <Text
          className={`font-label font-semibold ${variantText[variant]} ${textSize[size]}`}
        >
          {children}
        </Text>
      )}
    </Pressable>
  );
}