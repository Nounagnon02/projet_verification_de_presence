import { View, TextInput, Text, type TextInputProps } from 'react-native';
import type { ReactNode } from 'react';

interface InputProps extends TextInputProps {
  label?: string;
  error?: string;
  hint?: string;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
  containerClassName?: string;
}

export function Input({
  label,
  error,
  hint,
  leftIcon,
  rightIcon,
  containerClassName,
  className,
  ...props
}: InputProps) {
  return (
    <View className={`gap-y-1 ${containerClassName ?? ''}`}>
      {label && (
        <Text className="text-sm font-medium text-on-surface">{label}</Text>
      )}
      <View
        className={`flex-row items-center rounded-lg border bg-surface-container-lowest px-3 ${
          error ? 'border-error' : 'border-outline-variant'
        }`}
      >
        {leftIcon && <View className="mr-2">{leftIcon}</View>}
        <TextInput
          className={`flex-1 py-2.5 text-base text-on-surface ${className ?? ''}`}
          placeholderTextColor="#757680"
          {...props}
        />
        {rightIcon && <View className="ml-2">{rightIcon}</View>}
      </View>
      {error && <Text className="text-sm text-error">{error}</Text>}
      {hint && !error && (
        <Text className="text-sm text-on-surface-variant">{hint}</Text>
      )}
    </View>
  );
}