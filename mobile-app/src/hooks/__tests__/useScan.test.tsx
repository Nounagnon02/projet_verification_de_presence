import type { ReactNode } from 'react';
import { renderHook, act, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';

import { useScan } from '../useScan';
import { AuthProvider } from '../../auth/AuthContext';
import apiClient from '../../api/client';
import { showToast } from '../../utils/toast-config';
import { getToken, setToken } from '../../utils/token-storage';

/**
 * Contrat du scan authentifié (POST /api/presence/scan).
 *
 * Ce qui est prouvé ici, et que rien ne prouvait avant : le corps envoyé ne
 * contient NI « identifiant_unique » NI « scan_challenge ». Le premier laissait
 * scanner au nom d'un autre étudiant en saisissant son identifiant ; le second
 * imposait un aller-retour préalable vers course-by-token avant chaque scan.
 * L'étudiant est désormais celui du jeton Bearer, et le serveur seul le sait.
 */

jest.mock('../../api/client', () => ({
  __esModule: true,
  default: { post: jest.fn(), get: jest.fn() },
}));

jest.mock('../../utils/toast-config', () => ({
  showToast: jest.fn(),
  toastConfig: {},
}));

// Capteurs figés : le payload attendu doit être déterministe. Leur
// comportement réel est vérifié par leurs propres tests.
jest.mock('../useFingerprint', () => ({
  useFingerprint: () => ({ fingerprint: 'empreinte-de-test', loading: false }),
}));
jest.mock('../useLocation', () => ({
  useLocation: () => ({
    permissionGranted: true,
    loading: false,
    getPosition: () => Promise.resolve({ latitude: 6.3608, longitude: 2.4354 }),
  }),
}));
jest.mock('../useWifi', () => ({
  useWifi: () => ({
    getWifiInfo: () => Promise.resolve({ ssid: 'UAC-WIFI', bssid: '00:11:22:33:44:55' }),
  }),
}));

const clientMock = apiClient as unknown as { post: jest.Mock; get: jest.Mock };
const toastMock = showToast as unknown as jest.Mock;

const JETON_QR = '11111111-2222-3333-4444-555555555555';

const enveloppe = ({ children }: { children: ReactNode }) => (
  <AuthProvider>{children}</AuthProvider>
);

// renderHook est asynchrone depuis @testing-library/react-native 14.
const monter = () => renderHook(() => useScan(), { wrapper: enveloppe });

/** Erreur axios minimale : seule la forme `response` est lue par le hook. */
const erreurHttp = (status: number, message: string) =>
  Object.assign(new Error(`Request failed with status code ${status}`), {
    response: { status, data: { success: false, message } },
  });

beforeEach(() => {
  jest.clearAllMocks();
  clientMock.get.mockResolvedValue({ data: {} });
});

describe('useScan — corps de la requête', () => {
  it('envoie_le_jeton_et_les_facteurs_sans_identifiant_ni_defi', async () => {
    clientMock.post.mockResolvedValue({
      data: { success: true, message: 'Présence validée avec succès !' },
    });

    const { result } = await monter();
    await act(async () => {
      await result.current.submitScan(JETON_QR);
    });

    expect(clientMock.post).toHaveBeenCalledTimes(1);
    const [chemin, corps] = clientMock.post.mock.calls[0];

    expect(chemin).toBe('/presence/scan');
    expect(corps).toEqual({
      token: JETON_QR,
      device_fingerprint: 'empreinte-de-test',
      latitude: 6.3608,
      longitude: 2.4354,
      ssid: 'UAC-WIFI',
      bssid: '00:11:22:33:44:55',
    });
    expect(corps).not.toHaveProperty('identifiant_unique');
    expect(corps).not.toHaveProperty('scan_challenge');
  });

  it('n_interroge_plus_course_by_token_avant_le_scan', async () => {
    clientMock.post.mockResolvedValue({ data: { success: true, message: 'ok' } });

    const { result } = await monter();
    await act(async () => {
      await result.current.submitScan(JETON_QR);
    });

    // Cet appel ne servait qu'à récupérer le défi anti-fraude, disparu du
    // contrat : le garder retarderait chaque scan pour rien.
    expect(clientMock.get).not.toHaveBeenCalledWith(
      expect.stringContaining('/presence/course-by-token'),
    );
  });
});

describe('useScan — retours du serveur', () => {
  it('succes_expose_le_resultat_et_annonce_la_validation', async () => {
    const reponse = {
      success: true,
      message: 'Présence validée avec succès !',
      data: { etudiant: 'DOE JOHN', heure: '08:12:00' },
    };
    clientMock.post.mockResolvedValue({ data: reponse });

    const { result } = await monter();
    await act(async () => {
      await result.current.submitScan(JETON_QR);
    });

    expect(result.current.lastResult).toEqual(reponse);
    expect(toastMock).toHaveBeenCalledWith(
      'success',
      'Présence validée !',
      'Présence validée avec succès !',
    );
    expect(result.current.scanning).toBe(false);
  });

  it('401_ferme_la_session_et_renvoie_a_l_ecran_de_connexion', async () => {
    await setToken('jeton-revoque');
    clientMock.post.mockRejectedValue(erreurHttp(401, 'Unauthenticated.'));

    const { result } = await monter();
    await act(async () => {
      await expect(result.current.submitScan(JETON_QR)).rejects.toBeDefined();
    });

    // Sans ce traitement, l'étudiant restait sur le scanner et rescannait un QR
    // Code que le serveur refusera systématiquement.
    await waitFor(() => expect(router.replace).toHaveBeenCalledWith('/login'));
    expect(await getToken()).toBeNull();
  });

  it('409_annonce_une_presence_deja_enregistree_sans_crier_a_l_echec', async () => {
    clientMock.post.mockRejectedValue(
      erreurHttp(409, 'Votre présence est déjà enregistrée pour ce cours.'),
    );

    const { result } = await monter();
    await act(async () => {
      await expect(result.current.submitScan(JETON_QR)).rejects.toBeDefined();
    });

    expect(toastMock).toHaveBeenCalledWith(
      'warning',
      'Déjà enregistré',
      'Votre présence est déjà enregistrée pour ce cours.',
    );
    expect(router.replace).not.toHaveBeenCalled();
  });

  it('403_restitue_le_refus_generique_du_serveur', async () => {
    // Message générique voulu : le serveur ne doit livrer ni la distance, ni le
    // rayon de la salle, ni le SSID attendu — ils indiqueraient quoi falsifier.
    const refus = 'Vous ne semblez pas être dans la salle du cours.';
    clientMock.post.mockRejectedValue(erreurHttp(403, refus));

    const { result } = await monter();
    await act(async () => {
      await expect(result.current.submitScan(JETON_QR)).rejects.toBeDefined();
    });

    expect(toastMock).toHaveBeenCalledWith('error', 'Scan refusé', refus);
  });
});
