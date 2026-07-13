import { View, ActivityIndicator, Text } from 'react-native';

interface LoadingSpinnerProps {
  message?: string;
  size?: 'small' | 'large';
}

export function LoadingSpinner({ message, size = 'large' }: LoadingSpinnerProps) {
  return (
    <View className="flex-1 items-center justify-center gap-y-3">
      <ActivityIndicator size={size} color="#011549" />
      {message && (
        <Text className="text-base text-on-surface-variant">{message}</Text>
      )}
    </View>
  );
}