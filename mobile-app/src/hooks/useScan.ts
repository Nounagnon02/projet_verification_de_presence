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
  const { fingerprint } = useFingerprint();
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
        // 1. Recuperer le defi anti-fraude aupres du serveur, en meme temps que
        //    le GPS et le Wi-Fi. Le defi est signe par le serveur et lie a ce
        //    jeton de QR Code : il ne peut pas etre calcule ici, et une cle
        //    embarquee dans l'APK n'aurait rien authentifie.
        const [infosCours, position, wifi] = await Promise.all([
          apiClient.get<{ data?: { scan_challenge?: string } }>(
            `/presence/course-by-token/${qrToken}`,
          ),
          getPosition(),
          getWifiInfo(),
        ]);

        const challenge = infosCours.data?.data?.scan_challenge;
        if (!challenge) {
          throw new Error('QR Code invalide ou expire. Rescannez un nouveau code.');
        }

        // 2. Construire le payload
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
        // ouverte, GPS hors du rayon de la salle, présence déjà enregistrée.
        // Sans cette lecture, axios les remplaçait par « Request failed with
        // status code 422 » : le message utile n'atteignait jamais l'étudiant.
        const reponse = (err as any)?.response;
        const messageServeur: string | undefined = reponse?.data?.message;

        if (reponse?.status === 409) {
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
    [user, fingerprint, getPosition, getWifiInfo],
  );

  return { submitScan, scanning, lastResult };
}