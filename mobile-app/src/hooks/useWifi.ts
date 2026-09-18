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

      // iOS ne laisse pas lire le nom du réseau sans droit particulier. On
      // renvoyait ici le SSID fictif « __ios_wifi__ » : le serveur le comparait
      // au SSID configuré sur la salle, n'y trouvait aucune correspondance, et
      // REFUSAIT donc TOUT scan iOS dans une salle où un SSID est renseigné.
      // On ne transmet plus rien : le facteur réseau retombe alors sur le
      // contrôle de l'IP du client (salles.ip_range), qui, lui, fonctionne
      // depuis un iPhone.
      return null;
    } catch {
      return null;
    }
  }, []);

  return { getWifiInfo };
}
