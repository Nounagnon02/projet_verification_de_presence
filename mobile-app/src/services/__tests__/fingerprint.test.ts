import {
  getDeviceFingerprint,
  resetFingerprintCache,
} from '../fingerprint';

/**
 * MO-U-01 / MO-U-02 du plan de tests.
 *
 * L'empreinte d'appareil est la seule base de la detection d'appareil partage
 * cote serveur : deux etudiants scannant depuis le meme telephone doivent
 * produire la MEME valeur, sinon la fraude passe inapercue. Sa stabilite n'est
 * donc pas un detail d'implementation, c'est une exigence de securite.
 */
describe('getDeviceFingerprint', () => {
  beforeEach(() => resetFingerprintCache());

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
    // Le cache accelere ; il ne doit pas etre ce qui garantit la stabilite.
    // Sans ce test, une empreinte reposant sur un aleatoire memoise passerait.
    const premiere = await getDeviceFingerprint();
    resetFingerprintCache();

    expect(await getDeviceFingerprint()).toBe(premiere);
  });

  it('derive bien des composants de l\'appareil, selon la formule documentee', async () => {
    // Assertion sur l'algorithme lui-meme plutot que sur un espion : elle
    // prouve que l'empreinte depend REELLEMENT du materiel, et non d'une
    // constante ou d'un aleatoire memoise — ce qu'un simple test de stabilite
    // laisserait passer.
    const crypto = require('crypto');
    const entree = [
      'installation-de-test',   // Constants.installationId
      'bj.uac.presence.test',   // Application.applicationId
      'TestBrand',              // Device.brand
      'TestModel',              // Device.modelName
      '14',                     // Device.osVersion
      '1',                      // Application.nativeBuildVersion
    ].join(':');

    const attendue = crypto.createHash('sha256').update(entree).digest('hex').substring(0, 32);

    expect(await getDeviceFingerprint()).toBe(attendue);
  });

  it('reste calculable quand les composants materiels sont absents', async () => {
    // Sur un appareil ou expo-device ne renvoie rien, l'empreinte doit tomber
    // sur des valeurs de repli plutot que de lever : un scan qui echoue faute
    // d'empreinte est une presence perdue.
    const device = require('expo-device');
    const anciens = { brand: device.brand, modelName: device.modelName, osVersion: device.osVersion };
    device.brand = null;
    device.modelName = null;
    device.osVersion = null;

    resetFingerprintCache();
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
});
