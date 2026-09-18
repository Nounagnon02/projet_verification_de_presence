/**
 * Doubles des modules natifs.
 *
 * Chacun de ces modules interroge le materiel ou le systeme : sans double, il
 * n'existe pas dans l'environnement Node de Jest et l'import echoue avant meme
 * le premier test. Les valeurs retournees sont choisies pour etre DETERMINISTES
 * — une empreinte d'appareil ou une position qui varie d'une execution a l'autre
 * rendrait les assertions instables.
 */

// ─── Identite de l'appareil ────────────────────────────────────────────────────
jest.mock('expo-device', () => ({
  brand: 'TestBrand',
  modelName: 'TestModel',
  osVersion: '14',
  isDevice: true,
}));

// Signatures calquees sur node_modules/expo-application/build/Application.d.ts.
// Un double qui invente une API la rend invisible aux tests : c'est ainsi que
// la disparition de Constants.installationId est passee inapercue.
jest.mock('expo-application', () => ({
  applicationId: 'bj.uac.presence.test',
  nativeBuildVersion: '1',
  getAndroidId: jest.fn(() => 'android-id-de-test'),
  getIosIdForVendorAsync: jest.fn(() => Promise.resolve('IDFV-DE-TEST')),
}));

// Pas d'installationId : expo-constants l'a supprime (voir son CHANGELOG).
jest.mock('expo-constants', () => ({
  __esModule: true,
  default: {
    expoConfig: { extra: {} },
  },
}));

// SHA256 reel : le format de l'empreinte (32 caracteres hexadecimaux) fait
// partie du contrat teste. Un double renvoyant une constante le masquerait.
jest.mock('expo-crypto', () => {
  const crypto = require('crypto');
  return {
    CryptoDigestAlgorithm: { SHA256: 'SHA-256' },
    digestStringAsync: (_algo, valeur) =>
      Promise.resolve(crypto.createHash('sha256').update(valeur).digest('hex')),
    randomUUID: jest.fn(() => crypto.randomUUID()),
  };
});

// ─── Stockage sécurisé ─────────────────────────────────────────────────────────
// Implementation en memoire plutot qu'un jest.fn() nu : les tests de
// token-storage verifient un cycle ecriture → lecture → effacement, qui exige un
// stockage qui se souvienne.
jest.mock('expo-secure-store', () => {
  const coffre = new Map();
  return {
    __coffre: coffre,
    getItemAsync: (cle) => Promise.resolve(coffre.has(cle) ? coffre.get(cle) : null),
    setItemAsync: (cle, valeur) => { coffre.set(cle, valeur); return Promise.resolve(); },
    deleteItemAsync: (cle) => { coffre.delete(cle); return Promise.resolve(); },
  };
});

// AsyncStorage sert au cache utilisateur (donnee non sensible), la ou
// SecureStore garde le jeton. Meme raison de le doubler en memoire : les tests
// verifient un cycle complet, pas un appel isole.
jest.mock('@react-native-async-storage/async-storage', () => {
  const magasin = new Map();
  return {
    __esModule: true,
    default: {
      __magasin: magasin,
      getItem: (cle) => Promise.resolve(magasin.has(cle) ? magasin.get(cle) : null),
      setItem: (cle, valeur) => { magasin.set(cle, valeur); return Promise.resolve(); },
      removeItem: (cle) => { magasin.delete(cle); return Promise.resolve(); },
      clear: () => { magasin.clear(); return Promise.resolve(); },
    },
  };
});

// ─── Capteurs ──────────────────────────────────────────────────────────────────
jest.mock('expo-location', () => ({
  requestForegroundPermissionsAsync: jest.fn(() => Promise.resolve({ status: 'granted' })),
  getCurrentPositionAsync: jest.fn(() => Promise.resolve({
    coords: { latitude: 6.3608, longitude: 2.4354, accuracy: 5 },
  })),
  Accuracy: { High: 4, Highest: 6 },
}));

jest.mock('expo-network', () => ({
  getNetworkStateAsync: jest.fn(() => Promise.resolve({
    isConnected: true,
    isInternetReachable: true,
    type: 'WIFI',
  })),
  NetworkStateType: { WIFI: 'WIFI', CELLULAR: 'CELLULAR', NONE: 'NONE' },
}));

// Le SSID n'est pas lisible sur iOS sans droit particulier : c'est une limite du
// produit, pas du harnais, et elle doit apparaitre dans le memoire.
jest.mock('react-native-wifi-reborn', () => ({
  __esModule: true,
  default: {
    getCurrentWifiSSID: jest.fn(() => Promise.resolve('UAC-WIFI')),
    getBSSID: jest.fn(() => Promise.resolve('00:11:22:33:44:55')),
  },
}));

jest.mock('expo-camera', () => ({
  CameraView: 'CameraView',
  useCameraPermissions: jest.fn(() => [
    { granted: true, canAskAgain: true },
    jest.fn(() => Promise.resolve({ granted: true })),
  ]),
}));

// ─── Navigation et retours visuels ─────────────────────────────────────────────
jest.mock('expo-router', () => ({
  router: { replace: jest.fn(), push: jest.fn(), back: jest.fn() },
  useRouter: () => ({ replace: jest.fn(), push: jest.fn(), back: jest.fn() }),
  useLocalSearchParams: () => ({}),
  Link: 'Link',
  Stack: { Screen: 'Stack.Screen' },
  Tabs: { Screen: 'Tabs.Screen' },
}));

jest.mock('react-native-toast-message', () => ({
  __esModule: true,
  default: { show: jest.fn(), hide: jest.fn() },
}));

// btoa / atob : disponibles dans Hermes, absents de Node avant la v16 selon la
// configuration. On les fournit pour que le comportement soit identique partout.
if (typeof global.btoa === 'undefined') {
  global.btoa = (chaine) => Buffer.from(chaine, 'binary').toString('base64');
}
if (typeof global.atob === 'undefined') {
  global.atob = (base64) => Buffer.from(base64, 'base64').toString('binary');
}
