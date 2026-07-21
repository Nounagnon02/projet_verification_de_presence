import { useCallback } from 'react';
import { Platform } from 'react-native';
import * as Network from 'expo-network';
import WifiManager from 'react-native-wifi-reborn';

interface WifiInfo {
  ssid: string | null;
  bssid: string | null;
}

export function useWifi() {
  const getWifiInfo = useCallback(async (): Promise<WifiInfo | null> => {
    try {
      const state = await Network.getNetworkStateAsync();
      if (!state.isConnected || state.type !== Network.NetworkStateType.WIFI) return null;

      if (Platform.OS === 'android') {
        try {
          const [ssidStr, bssidStr] = await Promise.all([
            WifiManager.getCurrentWifiSSID(),
            WifiManager.getBSSID(),
          ]);
          return { ssid: ssidStr || null, bssid: bssidStr || null };
        } catch {
          return null;
        }
      }

      return { ssid: '__ios_wifi__', bssid: null };
    } catch {
      return null;
    }
  }, []);

  return { getWifiInfo };
}