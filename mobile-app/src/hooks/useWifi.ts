import { useState, useCallback } from 'react';
import { Platform } from 'react-native';
import * as Network from 'expo-network';
import WifiManager from 'react-native-wifi-reborn';

interface WifiInfo {
  ssid: string | null;
  bssid: string | null;
}

export function useWifi() {
  const [ssid, setSsid] = useState<string | null>(null);
  const [bssid, setBssid] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const getWifiInfo = useCallback(async (): Promise<WifiInfo | null> => {
    setLoading(true);
    try {
      // 1. Vérifier la connectivité réseau
      const state = await Network.getNetworkStateAsync();
      if (!state.isConnected || state.type !== Network.NetworkStateType.WIFI) {
        setSsid(null);
        setBssid(null);
        return null;
      }

      // 2. Sur Android, lire SSID et BSSID via react-native-wifi-reborn
      if (Platform.OS === 'android') {
        try {
          const [ssidStr, bssidStr] = await Promise.all([
            WifiManager.getCurrentWifiSSID(),
            WifiManager.getBSSID(),
          ]);
          const result: WifiInfo = {
            ssid: ssidStr || null,
            bssid: bssidStr || null,
          };
          setSsid(result.ssid);
          setBssid(result.bssid);
          return result;
        } catch {
          // Permission de localisation non accordée (Android 9+)
          setSsid(null);
          setBssid(null);
          return null;
        }
      }

      // 3. Sur iOS, le SSID n'est pas accessible sans entitlement spécial
      // On signale simplement la présence WiFi via un marqueur
      const iosResult: WifiInfo = { ssid: '__ios_wifi__', bssid: null };
      setSsid(iosResult.ssid);
      setBssid(null);
      return iosResult;
    } catch {
      setSsid(null);
      setBssid(null);
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  return { ssid, bssid, loading, getWifiInfo };
}