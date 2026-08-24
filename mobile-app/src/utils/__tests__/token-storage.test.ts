import * as SecureStore from 'expo-secure-store';
import AsyncStorage from '@react-native-async-storage/async-storage';

import {
  getToken, setToken, deleteToken,
  getCachedUser, setCachedUser, clearAuth,
} from '../token-storage';
import { CONFIG } from '../../constants/config';

/**
 * MO-U-09 du plan de tests.
 *
 * Le partage des responsabilites compte autant que le cycle de vie : le JETON
 * va dans SecureStore (chiffre par le systeme), le CACHE UTILISATEUR dans
 * AsyncStorage (non sensible, en clair). Inverser les deux exposerait le jeton
 * en clair sur l'appareil — c'est ce que ces tests verrouillent.
 */
describe('stockage du jeton', () => {
  beforeEach(async () => {
    await clearAuth();
  });

  it('renvoie null quand aucun jeton n\'est stocke', async () => {
    expect(await getToken()).toBeNull();
  });

  it('effectue le cycle ecriture, lecture, effacement', async () => {
    await setToken('jeton-abc-123');
    expect(await getToken()).toBe('jeton-abc-123');

    await deleteToken();
    expect(await getToken()).toBeNull();
  });

  it('ecrit le jeton dans SecureStore, jamais dans AsyncStorage', async () => {
    await setToken('jeton-secret');

    // Le jeton donne acces a l'API : le laisser en clair dans AsyncStorage le
    // rendrait lisible par une sauvegarde de l'appareil.
    expect(await SecureStore.getItemAsync(CONFIG.TOKEN_KEY)).toBe('jeton-secret');
    expect(await AsyncStorage.getItem(CONFIG.TOKEN_KEY)).toBeNull();
  });

  it('ne leve pas quand la lecture du coffre echoue', async () => {
    // Un coffre indisponible — appareil sans verrou d'ecran, permission
    // revoquee — doit conduire a une deconnexion propre, pas a un plantage au
    // demarrage de l'application.
    const espion = jest
      .spyOn(SecureStore, 'getItemAsync')
      .mockRejectedValueOnce(new Error('coffre indisponible'));

    await expect(getToken()).resolves.toBeNull();
    espion.mockRestore();
  });
});

describe('cache utilisateur', () => {
  const UTILISATEUR = {
    id: 1,
    name: 'Etudiant Test',
    email: 'etudiant@uac.test',
    identifiant_unique: 'NOM_PRENOM_MAT_FIL_2025-2026',
    est_responsable: false,
  };

  beforeEach(async () => {
    await clearAuth();
  });

  it('renvoie null quand rien n\'est en cache', async () => {
    expect(await getCachedUser()).toBeNull();
  });

  it('restitue l\'utilisateur enregistre', async () => {
    await setCachedUser(UTILISATEUR as never);
    expect(await getCachedUser()).toEqual(UTILISATEUR);
  });

  it('renvoie null plutot que de lever sur un cache illisible', async () => {
    // Un JSON corrompu ne doit pas empecher l'application de demarrer : elle
    // doit repartir sur un etat deconnecte.
    await AsyncStorage.setItem(CONFIG.USER_KEY, '{ceci n\'est pas du JSON');

    await expect(getCachedUser()).resolves.toBeNull();
  });

  it('conserve est_responsable, qui pilote l\'affichage du QR du cours', async () => {
    // C'est ce drapeau qui decide si l'onglet « QR du cours » est visible.
    // Une perte a la serialisation priverait le delegue de sa fonction.
    await setCachedUser({ ...UTILISATEUR, est_responsable: true } as never);

    expect((await getCachedUser())?.est_responsable).toBe(true);
  });
});

describe('clearAuth', () => {
  it('purge le jeton ET le cache utilisateur', async () => {
    await setToken('jeton-a-purger');
    await setCachedUser({ id: 1, name: 'X', email: 'x@y.z' } as never);

    await clearAuth();

    // Une purge partielle laisserait l'application afficher un utilisateur
    // sans jeton valide : ecrans peuples, requetes en 401.
    expect(await getToken()).toBeNull();
    expect(await getCachedUser()).toBeNull();
  });
});
