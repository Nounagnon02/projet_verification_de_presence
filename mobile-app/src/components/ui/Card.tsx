import { View, Text, type ViewProps } from 'react-native';
import type { ReactNode } from 'react';

interface CardProps extends ViewProps {
  title?: string;
  subtitle?: string;
  children: ReactNode;
}

export function Card({
  title,
  subtitle,
  children,
  className,
  ...props
}: CardProps) {
  return (
    <View
      className={`rounded-xl border border-outline-variant bg-surface p-4 shadow-sm ${className ?? ''}`}
      {...props}
    >
      {title && (
        <Text className="font-headline text-lg font-semibold text-on-surface">
          {title}
        </Text>
      )}
      {subtitle && (
        <Text className="mt-1 text-sm text-on-surface-variant">{subtitle}</Text>
      )}
      {(title || subtitle) && <View className="mt-3" />}
      {children}
    </View>
  );
}