import Toast, { type ToastConfigParams } from 'react-native-toast-message';
import { View, Text } from 'react-native';

export const toastConfig = {
  success: ({ text1, text2 }: ToastConfigParams<unknown>) => (
    <View className="mx-4 rounded-lg bg-secondary px-4 py-3">
      {text1 && <Text className="font-semibold text-on-secondary">{text1}</Text>}
      {text2 && (
        <Text className="mt-0.5 text-sm opacity-90 text-on-secondary">{text2}</Text>
      )}
    </View>
  ),
  error: ({ text1, text2 }: ToastConfigParams<unknown>) => (
    <View className="mx-4 rounded-lg bg-error px-4 py-3">
      {text1 && <Text className="font-semibold text-on-error">{text1}</Text>}
      {text2 && (
        <Text className="mt-0.5 text-sm opacity-90 text-on-error">{text2}</Text>
      )}
    </View>
  ),
  warning: ({ text1, text2 }: ToastConfigParams<unknown>) => (
    <View className="mx-4 rounded-lg bg-[#ffb700] px-4 py-3">
      {text1 && <Text className="font-semibold text-on-background">{text1}</Text>}
      {text2 && (
        <Text className="mt-0.5 text-sm text-on-surface-variant">{text2}</Text>
      )}
    </View>
  ),
};

/**
 * Affiche un toast rapidement sans avoir à importer Toast + le config.
 */
export function showToast(
  type: 'success' | 'error' | 'warning',
  title: string,
  message?: string,
) {
  Toast.show({
    type,
    text1: title,
    text2: message,
    position: 'bottom',
    visibilityTime: 3000,
  });
}