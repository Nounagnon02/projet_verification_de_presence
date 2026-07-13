import { useState, useEffect, useCallback } from 'react';
import {
  initFingerprint,
  getVisitorId,
  getFullFingerprint,
  getAntiFraudFingerprint,
  generateScanChallenge,
  verifyScanChallenge,
  FingerprintComponents,
} from '../services/fingerprint';

/**
 * Hook React pour le device fingerprinting
 * Fournit un visitorId stable et des méthodes pour l'anti-fraude
 */
export function useFingerprint() {
  const [visitorId, setVisitorId] = useState<string | null>(null);
  const [fingerprint, setFingerprint] = useState<FingerprintComponents | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Initialiser le fingerprint au montage
  useEffect(() => {
    let mounted = true;

    const loadFingerprint = async () => {
      try {
        setLoading(true);
        await initFingerprint();

        const [vid, fp] = await Promise.all([
          getVisitorId(),
          getAntiFraudFingerprint(),
        ]);

        if (mounted) {
          setVisitorId(vid);
          setFingerprint(fp);
          setError(null);
        }
      } catch (err) {
        if (mounted) {
          setError(err instanceof Error ? err.message : 'Erreur fingerprint');
          console.error('Fingerprint error:', err);
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    };

    loadFingerprint();

    return () => {
      mounted = false;
    };
  }, []);

  // Générer un challenge pour le scan QR
  const createScanChallenge = useCallback(async () => {
    try {
      return await generateScanChallenge();
    } catch (err) {
      console.error('Challenge generation error:', err);
      throw err;
    }
  }, []);

  // Vérifier un challenge
  const checkScanChallenge = useCallback(
    (challenge: string, expectedVisitorId?: string) => {
      const vid = expectedVisitorId || visitorId;
      if (!vid) {
        return { valid: false, reason: 'No visitor ID available' };
      }
      return verifyScanChallenge(challenge, vid);
    },
    [visitorId]
  );

  // Rafraîchir le fingerprint (ex: après changement de config navigateur)
  const refresh = useCallback(async () => {
    try {
      setLoading(true);
      const [vid, fp] = await Promise.all([
        getVisitorId(),
        getAntiFraudFingerprint(),
      ]);
      setVisitorId(vid);
      setFingerprint(fp);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur refresh');
    } finally {
      setLoading(false);
    }
  }, []);

  return {
    visitorId,
    fingerprint,
    loading,
    error,
    createScanChallenge,
    checkScanChallenge,
    refresh,
    // Helpers pour l'anti-fraude
    isReady: !loading && !!visitorId,
    confidence: fingerprint?.confidence || 0,
  };
}

/**
 * Hook simplifié pour juste le visitorId (utilisé dans les formulaires de scan)
 */
export function useVisitorId() {
  const [visitorId, setVisitorId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let mounted = true;
    getVisitorId().then((vid) => {
      if (mounted) {
        setVisitorId(vid);
        setLoading(false);
      }
    });
    return () => {
      mounted = false;
    };
  }, []);

  return { visitorId, loading };
}

export default useFingerprint;