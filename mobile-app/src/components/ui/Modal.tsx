import { Modal as RNModal, View, Pressable, Text } from 'react-native';
import type { ReactNode } from 'react';
import { X } from 'lucide-react-native';

interface ModalProps {
  visible: boolean;
  onClose: () => void;
  title?: string;
  children: ReactNode;
}

export function Modal({ visible, onClose, title, children }: ModalProps) {
  return (
    <RNModal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
    >
      {/* Overlay */}
      <Pressable
        className="flex-1 items-center justify-center bg-black/50 px-4"
        onPress={(e) => {
          // Ferme seulement si on tape sur l'overlay, pas le contenu
          if (e.target === e.currentTarget) onClose();
        }}
      >
        {/* Contenu */}
        <View className="w-full max-w-sm rounded-xxl bg-surface p-6 shadow-lg">
          {/* Header */}
          {title && (
            <View className="mb-4 flex-row items-center justify-between">
              <Text className="font-headline text-lg font-semibold text-on-surface">
                {title}
              </Text>
              <Pressable
                onPress={onClose}
                className="rounded-full p-1 active:bg-surface-container"
                hitSlop={8}
              >
                <X size={20} color="#757680" />
              </Pressable>
            </View>
          )}
          {children}
        </View>
      </Pressable>
    </RNModal>
  );
}