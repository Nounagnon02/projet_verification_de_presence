import { useState, useCallback } from 'react';
import { router } from 'expo-router';
import { useAuth } from '../auth/AuthContext';
import { useFingerprint } from './useFingerprint';
import { useLocation } from './useLocation';
import { useWifi } from './useWifi';
import apiClient from '../api/client';
import { showToast } from '../utils/toast-config';
import type { ScanPayload, ScanResponse } from '../types';
import { CONFIG } from '../constants/config';

export function useScan() {
  const { sessionExpiree } = useAuth();
  const { fingerprint } = useFingerprint();
  const { getPosition } = useLocation();
  const { getWifiInfo } = useWifi();

  const [scanning, setScanning] = useState(false);
  const [lastResult, setLastResult] = useState<ScanResponse | null>(null);

  const submitScan = useCallback(
    async (qrToken: string): Promise<ScanResponse> => {
      if (!fingerprint) {
        throw new Error('Empreinte appareil non disponible.');
      }

      setScanning(true);
      try {
        // 1. Relever les facteurs de vérification, en parallèle.
        //    Plus aucun appel préalable à course-by-token : il ne servait qu'à
        //    récupérer le défi anti-fraude, que le scan authentifié ne demande
        //    plus. Cet aller-retour retardait chaque scan sans rien apporter.
        const [position, wifi] = await Promise.all([getPosition(), getWifiInfo()]);

        // 2. Construire le payload.
        //    L'étudiant n'est plus désigné par « identifiant_unique » : le
        //    serveur le lit dans le jeton Bearer (injecté par src/api/client.ts).
        //    Envoyer son identifiant permettait de scanner au nom d'un autre.
        const payload: ScanPayload = {
          token: qrToken,
          device_fingerprint: fingerprint,
          latitude: position?.latitude,
          longitude: position?.longitude,
          ssid: wifi?.ssid ?? undefined,
          bssid: wifi?.bssid ?? undefined,
        };

        // 3. Envoyer au backend
        const { data } = await apiClient.post<ScanResponse>(
          '/presence/scan',
          payload,
          { timeout: CONFIG.SCAN_TIMEOUT },
        );

        const result: ScanResponse = data;
        setLastResult(result);

        // 4. Notifier l'utilisateur
        if (result.success) {
          showToast('success', 'Présence validée !', result.message);
        } else if (result.double_scan_detected) {
          showToast('warning', 'Double scan détecté', result.message);
        } else {
          showToast('error', 'Échec de validation', result.message);
        }

        return result;
      } catch (err: unknown) {
        // Le serveur formule des refus précis — fenêtre de scan non encore
        // ouverte, présence déjà enregistrée, appareil non reconnu. Sans cette
        // lecture, axios les remplaçait par « Request failed with status code
        // 422 » : le message utile n'atteignait jamais l'étudiant.
        const reponse = (err as { response?: { status?: number; data?: { message?: string } } })?.response;
        const messageServeur: string | undefined = reponse?.data?.message;

        if (reponse?.status === 401) {
          // Le scan est désormais authentifié : un jeton expiré ou révoqué se
          // traduit par un 401. Laisser l'étudiant sur le scanner lui ferait
          // rescanner indéfiniment un QR Code qui ne sera jamais accepté.
          await sessionExpiree();
          showToast(
            'error',
            'Session expirée',
            messageServeur ?? 'Reconnectez-vous pour valider votre présence.',
          );
          router.replace('/login');
        } else if (reponse?.status === 409) {
          // Déjà enregistré : l'objectif de l'étudiant est atteint. Le signaler
          // en rouge comme un échec serait trompeur.
          showToast(
            'warning',
            'Déjà enregistré',
            messageServeur ?? 'Votre présence est déjà enregistrée pour ce cours.',
          );
        } else if (reponse?.status === 403) {
          showToast(
            'error',
            'Scan refusé',
            messageServeur ?? 'Votre appareil ne correspond pas. Contactez votre administrateur.',
          );
        } else {
          showToast(
            'error',
            'Erreur',
            messageServeur ?? (err instanceof Error ? err.message : 'Erreur réseau lors du scan.'),
          );
        }
        throw err;
      } finally {
        setScanning(false);
      }
    },
    [fingerprint, getPosition, getWifiInfo, sessionExpiree],
  );

  return { submitScan, scanning, lastResult };
}
