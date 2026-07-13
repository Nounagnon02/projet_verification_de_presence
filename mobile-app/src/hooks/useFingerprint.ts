import { useState, useEffect } from 'react';
import {
  getDeviceFingerprint,
  createScanChallenge,
  verifyScanChallenge,
} from '../services/fingerprint';

export function useFingerprint() {
  const [fingerprint, setFingerprint] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const fp = await getDeviceFingerprint();
        if (!cancelled) setFingerprint(fp);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, []);

  return {
    fingerprint,
    loading,
    generateChallenge: createScanChallenge,
    verifyChallenge: verifyScanChallenge,
  };
}