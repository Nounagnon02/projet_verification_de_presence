import { useState, useEffect, useCallback } from 'react';
import * as Location from 'expo-location';

interface GeoPosition {
  latitude: number;
  longitude: number;
}

export function useLocation() {
  const [permissionGranted, setPermissionGranted] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const { status } = await Location.requestForegroundPermissionsAsync();
        if (!cancelled) setPermissionGranted(status === 'granted');
      } catch {
        if (!cancelled) setPermissionGranted(false);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, []);

  const getPosition = useCallback(async (): Promise<GeoPosition | null> => {
    if (!permissionGranted) return null;
    try {
      const loc = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.High,
      });
      return {
        latitude: loc.coords.latitude,
        longitude: loc.coords.longitude,
      };
    } catch {
      return null;
    }
  }, [permissionGranted]);

  return { permissionGranted, loading, getPosition };
}