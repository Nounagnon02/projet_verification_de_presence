import { useState, useCallback } from 'react';
import { useAuth } from '../auth/AuthContext';
import { useFingerprint } from './useFingerprint';
import { useLocation } from './useLocation';
import { useWifi } from './useWifi';
import apiClient from '../api/client';
import { showToast } from '../utils/toast-config';
import type { ScanPayload, ScanResponse } from '../types';
import { CONFIG } from '../constants/config';

export function useScan() {
  const { user } = useAuth();
  const { fingerprint, generateChallenge } = useFingerprint();
  const { getPosition } = useLocation();
  const { getWifiInfo } = useWifi();

  const [scanning, setScanning] = useState(false);
  const [lastResult, setLastResult] = useState<ScanResponse | null>(null);

  const submitScan = useCallback(
    async (qrToken: string): Promise<ScanResponse> => {
      if (!user?.identifiant_unique) {
        throw new Error('Identifiant étudiant introuvable. Reconnectez-vous.');
      }
      if (!fingerprint) {
        throw new Error('Empreinte appareil non disponible.');
      }

      setScanning(true);
      try {
        // 1. Générer le challenge anti-fraude
        const challenge = await generateChallenge();

        // 2. GPS + WiFi en parallèle (indépendants)
        const [position, wifi] = await Promise.all([
          getPosition(),
          getWifiInfo(),
        ]);

        // 3. Construire le payload
        const payload: ScanPayload = {
          identifiant_unique: user.identifiant_unique,
          token: qrToken,
          device_fingerprint: fingerprint,
          scan_challenge: challenge,
          latitude: position?.latitude,
          longitude: position?.longitude,
          ssid: wifi?.ssid ?? undefined,
          bssid: wifi?.bssid ?? undefined,
        };

        // 4. Envoyer au backend
        const { data } = await apiClient.post<ScanResponse>(
          '/presence/scan',
          payload,
          { timeout: CONFIG.SCAN_TIMEOUT },
        );

        const result: ScanResponse = data;
        setLastResult(result);

        // 5. Notifier l'utilisateur
        if (result.success) {
          showToast('success', 'Présence validée !', result.message);
        } else if (result.double_scan_detected) {
          showToast('warning', 'Double scan détecté', result.message);
        } else {
          showToast('error', 'Échec de validation', result.message);
        }

        return result;
      } catch (err: unknown) {
        if (err instanceof Error && (err as any).response?.status === 403) {
          showToast(
            'error',
            'Scan refusé',
            'Votre appareil ne correspond pas. Contactez votre administrateur.',
          );
        } else {
          const message =
            err instanceof Error ? err.message : 'Erreur réseau lors du scan.';
          showToast('error', 'Erreur', message);
        }
        throw err;
      } finally {
        setScanning(false);
      }
    },
    [user, fingerprint, generateChallenge, getPosition, getWifiInfo],
  );

  return { submitScan, scanning, lastResult };
}