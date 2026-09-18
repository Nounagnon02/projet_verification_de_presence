import { Platform } from 'react-native';
import * as Application from 'expo-application';
import * as Crypto from 'expo-crypto';
import * as SecureStore from 'expo-secure-store';
import {
  getDeviceFingerprint,
  resetFingerprintCache,
} from '../fingerprint';

/**
 * MO-U-01 / MO-U-02 du plan de tests.
 *
 * L'empreinte d'appareil est la seule base de la detection d'appareil partage
 * cote serveur : deux etudiants scannant depuis le meme telephone doivent
 * produire la MEME valeur, sinon la fraude passe inapercue. Et deux telephones
 * differents doivent produire des valeurs DIFFERENTES, sinon toute une classe
 * equipee du meme modele est marquee suspecte et partage un seul quota de
 * scans. Les deux proprietes sont des exigences, pas des details.
 */
const coffre: Map<string, string> = (SecureStore as unknown as { __coffre: Map<string, string> }).__coffre;
const osInitial = Platform.OS;

function surPlateforme(os: 'android' | 'ios'): void {
  (Platform as unknown as { OS: string }).OS = os;
}

/** Un autre telephone : caches memoire ET stockage securise vides. */
function autreTelephone(): void {
  resetFingerprintCache();
  coffre.clear();
}

describe('getDeviceFingerprint', () => {
  beforeEach(() => {
    autreTelephone();
    jest.clearAllMocks();
  });

  afterEach(() => {
    (Platform as unknown as { OS: string }).OS = osInitial;
    jest.restoreAllMocks();
  });

  it('produit 32 caracteres hexadecimaux', async () => {
    const empreinte = await getDeviceFingerprint();

    expect(empreinte).toHaveLength(32);
    expect(empreinte).toMatch(/^[a-f0-9]{32}$/);
  });

  it('reste identique entre deux appels', async () => {
    // Sans stabilite, chaque scan du meme appareil apparaitrait comme un
    // appareil different : la detection d'appareil partage serait aveugle.
    expect(await getDeviceFingerprint()).toBe(await getDeviceFingerprint());
  });

  it('reste identique apres vidage du cache', async () => {
    // Le cache memoire accelere ; ce qui garantit la stabilite d'un lancement
    // a l'autre est l'identifiant conserve dans SecureStore.
    const premiere = await getDeviceFingerprint();
    resetFingerprintCache();

    expect(await getDeviceFingerprint()).toBe(premiere);
  });

  it('differe entre deux telephones Android du meme modele', async () => {
    // Regression : l'empreinte reposait sur Constants.installationId, supprime
    // d'expo-constants. Il ne restait que marque/modele/OS/build, identiques
    // sur tous les telephones d'un meme modele.
    surPlateforme('android');

    (Application.getAndroidId as jest.Mock).mockReturnValueOnce('a1a1a1a1a1a1a1a1');
    const premier = await getDeviceFingerprint();

    autreTelephone();
    (Application.getAndroidId as jest.Mock).mockReturnValueOnce('b2b2b2b2b2b2b2b2');
    const second = await getDeviceFingerprint();

    expect(second).not.toBe(premier);
  });

  it('differe entre deux iPhone du meme modele', async () => {
    surPlateforme('ios');

    (Application.getIosIdForVendorAsync as jest.Mock).mockResolvedValueOnce('IDFV-PREMIER');
    const premier = await getDeviceFingerprint();

    autreTelephone();
    (Application.getIosIdForVendorAsync as jest.Mock).mockResolvedValueOnce('IDFV-SECOND');
    const second = await getDeviceFingerprint();

    expect(second).not.toBe(premier);
  });

  it('derive de l\'identifiant de l\'appareil, selon la formule documentee', async () => {
    // Assertion sur l'algorithme lui-meme plutot que sur un espion : elle
    // prouve que l'empreinte depend REELLEMENT de l'appareil, et non d'une
    // constante ou d'un aleatoire memoise.
    surPlateforme('android');
    const crypto = require('crypto');
    const entree = [
      'bj.uac.presence.test',        // Application.applicationId
      'android:android-id-de-test',  // identifiant de l'appareil
      'TestBrand',                   // Device.brand
      'TestModel',                   // Device.modelName
    ].join(':');

    const attendue = crypto.createHash('sha256').update(entree).digest('hex').substring(0, 32);

    expect(await getDeviceFingerprint()).toBe(attendue);
  });

  it('reste stable quand l\'IDFV devient momentanement indisponible', async () => {
    // iOS peut renvoyer null juste apres un redemarrage. L'identifiant retenu
    // la premiere fois prime : sans cela, le meme iPhone changerait d'empreinte.
    surPlateforme('ios');
    const premiere = await getDeviceFingerprint();

    resetFingerprintCache();
    (Application.getIosIdForVendorAsync as jest.Mock).mockResolvedValueOnce(null);

    expect(await getDeviceFingerprint()).toBe(premiere);
  });

  it('se replie sur un identifiant d\'installation tire une fois et conserve', async () => {
    surPlateforme('ios');
    (Application.getIosIdForVendorAsync as jest.Mock).mockResolvedValueOnce(null);

    const premiere = await getDeviceFingerprint();
    expect(coffre.get('device_id')).toMatch(/^installation:/);

    resetFingerprintCache();
    expect(await getDeviceFingerprint()).toBe(premiere);
    expect(Crypto.randomUUID).toHaveBeenCalledTimes(1);
  });

  it('reste calculable quand le stockage securise echoue', async () => {
    // Un scan qui echoue faute d'empreinte est une presence perdue.
    jest.spyOn(SecureStore, 'getItemAsync').mockRejectedValue(new Error('trousseau verrouille'));
    jest.spyOn(SecureStore, 'setItemAsync').mockRejectedValue(new Error('trousseau verrouille'));

    expect(await getDeviceFingerprint()).toMatch(/^[a-f0-9]{32}$/);
  });

  it('reste calculable quand les composants materiels sont absents', async () => {
    // Sur un appareil ou expo-device ne renvoie rien, l'empreinte doit tomber
    // sur des valeurs de repli plutot que de lever.
    const device = require('expo-device');
    const anciens = { brand: device.brand, modelName: device.modelName };
    device.brand = null;
    device.modelName = null;

    const empreinte = await getDeviceFingerprint();
    expect(empreinte).toMatch(/^[a-f0-9]{32}$/);

    Object.assign(device, anciens);
  });
});

/**
 * Le defi anti-fraude n'est plus calcule cote client : il est emis par le
 * serveur dans la reponse de GET /presence/course-by-token/{token}.
 *
 * Ce test verrouille cette decision. Le service a un temps calcule
 * sha256(empreinte + ':' + APP_KEY), ce qui imposait d'embarquer la cle de
 * l'application dans l'APK — une cle decompilable n'authentifie rien — et
 * produisait de toute facon une valeur que le serveur refusait.
 */
describe('surface du service', () => {
  it('n\'expose aucune fabrique de defi', () => {
    const service = require('../fingerprint');

    expect(service.createScanChallenge).toBeUndefined();
    expect(service.verifyScanChallenge).toBeUndefined();
  });

  it('n\'embarque aucune cle d\'application', () => {
    const source = require('fs').readFileSync(
      require('path').join(__dirname, '..', 'fingerprint.ts'),
      'utf8',
    );

    expect(source).not.toMatch(/uac-presence-secret/);
    expect(source).not.toMatch(/APP_KEY\s*=/);
  });

  it('ne tire plus l\'identite de l\'appareil d\'expo-constants', () => {
    // installationId a disparu d'expo-constants sans que tsc ne le signale :
    // le service ne doit plus en dependre.
    const source = require('fs').readFileSync(
      require('path').join(__dirname, '..', 'fingerprint.ts'),
      'utf8',
    );

    expect(source).not.toMatch(/from ['"]expo-constants['"]/);
  });
});
