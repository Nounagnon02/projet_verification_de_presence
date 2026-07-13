import {
  CameraView,
  useCameraPermissions,
  type BarcodeScanningResult,
} from 'expo-camera';
import { View, Text, Pressable } from 'react-native';
import { useCallback, useRef, useState } from 'react';
import { CameraOff } from 'lucide-react-native';
import { CONFIG } from '../../constants/config';

interface QRScannerProps {
  onScan: (data: string) => void;
  scanning: boolean;
}

export function QRScanner({ onScan, scanning }: QRScannerProps) {
  const [permission, requestPermission] = useCameraPermissions();
  const lastScanTime = useRef(0);
  const lastScanData = useRef<string | null>(null);
  const [cooldown, setCooldown] = useState(false);

  const handleBarcodeScanned = useCallback(
    (result: BarcodeScanningResult) => {
      const now = Date.now();

      // Anti-spam : cooldown entre deux scans
      if (now - lastScanTime.current < CONFIG.QR_SCAN_COOLDOWN) return;
      // Éviter les scans répétés du même contenu
      if (result.data === lastScanData.current) return;

      lastScanTime.current = now;
      lastScanData.current = result.data;
      setCooldown(true);

      // Déclencher le scan
      onScan(result.data);

      // Relâcher le cooldown après le délai configuré
      setTimeout(() => setCooldown(false), CONFIG.QR_SCAN_COOLDOWN);
    },
    [onScan],
  );

  // --- Permission non encore chargée ---
  if (!permission) {
    return (
      <View className="flex-1 items-center justify-center bg-background">
        <Text className="text-base text-on-surface-variant">
          Vérification des permissions...
        </Text>
      </View>
    );
  }

  // --- Permission refusée ---
  if (!permission.granted) {
    return (
      <View className="flex-1 items-center justify-center bg-background px-6">
        <CameraOff size={64} color="#757680" />
        <Text className="mt-4 text-center text-lg text-on-surface">
          Permission caméra requise
        </Text>
        <Text className="mt-2 text-center text-base text-on-surface-variant">
          Autorisez l'accès à la caméra pour scanner les QR codes de validation
          de présence sur le lieu du cours.
        </Text>
        <Pressable
          onPress={requestPermission}
          className="mt-6 rounded-lg bg-primary px-6 py-3 active:bg-primary-container"
        >
          <Text className="font-semibold text-on-primary">
            Autoriser la caméra
          </Text>
        </Pressable>
      </View>
    );
  }

  // --- Scanner actif ---
  const isActive = !scanning && !cooldown;

  return (
    <View className="flex-1 bg-black">
      <CameraView
        style={{ flex: 1 }}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={isActive ? handleBarcodeScanned : undefined}
      />

      {/* Cadre de scan — coins uniquement, pas d'overlay opaque */}
      <View
        className="absolute inset-0 items-center justify-center"
        pointerEvents="none"
      >
        <View className="h-64 w-64">
          {/* Coin supérieur gauche */}
          <View className="absolute left-0 top-0 h-10 w-10 rounded-tl-xl border-l-4 border-t-4 border-secondary" />
          {/* Coin supérieur droit */}
          <View className="absolute right-0 top-0 h-10 w-10 rounded-tr-xl border-r-4 border-t-4 border-secondary" />
          {/* Coin inférieur gauche */}
          <View className="absolute bottom-0 left-0 h-10 w-10 rounded-bl-xl border-b-4 border-l-4 border-secondary" />
          {/* Coin inférieur droit */}
          <View className="absolute bottom-0 right-0 h-10 w-10 rounded-br-xl border-b-4 border-r-4 border-secondary" />
        </View>
      </View>

      {/* Indicateur de scan en cours */}
      {scanning && (
        <View className="absolute bottom-12 left-0 right-0 items-center">
          <View className="rounded-lg bg-primary px-6 py-3">
            <Text className="font-semibold text-on-primary">
              Validation en cours...
            </Text>
          </View>
        </View>
      )}

      {/* Message d'aide */}
      {!scanning && (
        <View className="absolute bottom-12 left-0 right-0 items-center">
          <Text className="text-sm text-white/70">
            Placez le QR code dans le cadre
          </Text>
        </View>
      )}
    </View>
  );
}