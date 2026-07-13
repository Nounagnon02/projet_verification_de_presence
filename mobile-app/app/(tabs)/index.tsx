import { useCallback } from 'react';
import { useRouter } from 'expo-router';
import { useScan } from '../../src/hooks/useScan';
import { QRScanner } from '../../src/components/scanner/QRScanner';
import { useAuth } from '../../src/auth/AuthContext';

export default function ScannerTab() {
  const { submitScan, scanning } = useScan();
  const { isAuthenticated } = useAuth();
  const router = useRouter();

  const handleScan = useCallback(
    async (data: string) => {
      try {
        const result = await submitScan(data);
        // Navigation automatique vers l'historique si succès
        if (result.success) {
          setTimeout(() => router.push('/(tabs)/history'), 500);
        }
      } catch {
        // useScan affiche déjà le toast d'erreur
      }
    },
    [submitScan, router],
  );

  // Si l'utilisateur n'est pas connecté, le redirect de app/index.tsx
  // devrait déjà l'avoir envoyé vers /login. Ce garde est une sécurité.
  if (!isAuthenticated) return null;

  return <QRScanner onScan={handleScan} scanning={scanning} />;
}