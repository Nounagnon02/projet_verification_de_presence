import { render, screen, fireEvent, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';

import LoginScreen from '../login';
import { AuthProvider } from '../../src/auth/AuthContext';
import apiClient from '../../src/api/client';
import { showToast } from '../../src/utils/toast-config';
import { getToken } from '../../src/utils/token-storage';
import * as SecureStore from 'expo-secure-store';

/**
 * Écran de connexion étudiant.
 *
 * L'email et l'identifiant unique figurent sur la carte d'étudiant : seuls, ils
 * n'authentifiaient personne. Le code d'accès à 6 chiffres est le secret que le
 * serveur exige désormais, et cet écran ne le demandait pas du tout.
 */

jest.mock('../../src/api/client', () => ({
  __esModule: true,
  default: { post: jest.fn(), get: jest.fn() },
}));

jest.mock('../../src/utils/toast-config', () => ({
  showToast: jest.fn(),
  toastConfig: {},
}));

const clientMock = apiClient as unknown as { post: jest.Mock; get: jest.Mock };
const toastMock = showToast as unknown as jest.Mock;

const EMAIL = 'jean.doe@uac.bj';
const IDENTIFIANT = 'DOE_JEAN_22A1234_GLT_L3';

const monter = () => render(
  <AuthProvider>
    <LoginScreen />
  </AuthProvider>,
);

const remplir = async ({ code }: { code: string }) => {
  await fireEvent.changeText(screen.getByPlaceholderText('votre.email@uac.bj'), EMAIL);
  await fireEvent.changeText(
    screen.getByPlaceholderText('NOM_PRENOM_MATRICULE_FILIERE_ANNEE'),
    IDENTIFIANT,
  );
  await fireEvent.changeText(screen.getByPlaceholderText('••••••'), code);
};

const soumettre = async () => {
  await fireEvent.press(screen.getByText('Se connecter'));
};

/** Erreur axios minimale : seule la forme `response` est lue. */
const erreurHttp = (status: number, corps: Record<string, unknown>) =>
  Object.assign(new Error(`Request failed with status code ${status}`), {
    response: { status, data: corps },
  });

beforeEach(() => {
  jest.clearAllMocks();
  clientMock.get.mockResolvedValue({ data: {} });
  // Le jeton écrit par un test de connexion réussie persistait dans le
  // SecureStore simulé (partagé entre tests, voir jest.setup.js) : le test
  // suivant restaurait cette session au montage et l'écran de connexion
  // rendait « null » au lieu du formulaire.
  (SecureStore as unknown as { __coffre: Map<string, string> }).__coffre.clear();
});

describe("Écran de connexion — champ code d'accès", () => {
  it('affiche_le_champ_code_masque_et_numerique', async () => {
    await monter();

    const champ = screen.getByPlaceholderText('••••••');
    expect(champ.props.secureTextEntry).toBe(true);
    expect(champ.props.keyboardType).toBe('number-pad');
    expect(champ.props.maxLength).toBe(6);
  });

  it('ne_conserve_que_les_chiffres_saisis', async () => {
    await monter();

    const champ = screen.getByPlaceholderText('••••••');
    // Un code collé depuis un e-mail arrive souvent avec des espaces : les
    // laisser passer faisait échouer la connexion sans rien afficher.
    await fireEvent.changeText(champ, '12 34-56789');

    expect(champ.props.value).toBe('123456');
  });

  it('refuse_localement_un_code_incomplet_sans_appeler_le_serveur', async () => {
    await monter();
    await remplir({ code: '123' });
    await soumettre();

    expect(await screen.findByText(/6 chiffres/)).toBeTruthy();
    expect(clientMock.post).not.toHaveBeenCalled();
  });
});

describe('Écran de connexion — échange avec le serveur', () => {
  it('transmet_email_identifiant_et_code_puis_ouvre_l_application', async () => {
    clientMock.post.mockResolvedValue({
      data: {
        success: true,
        message: 'Connexion réussie',
        data: {
          token: 'jeton-etudiant',
          user: { id: 7, email: EMAIL, role: 'etudiant', identifiant_unique: IDENTIFIANT },
        },
      },
    });

    await monter();
    await remplir({ code: '482913' });
    await soumettre();

    await waitFor(() => expect(clientMock.post).toHaveBeenCalledTimes(1));
    expect(clientMock.post).toHaveBeenCalledWith('/auth/student/login', {
      email: EMAIL,
      identifiant_unique: IDENTIFIANT,
      code: '482913',
    });

    await waitFor(() => expect(router.replace).toHaveBeenCalledWith('/(tabs)'));
    expect(await getToken()).toBe('jeton-etudiant');
  });

  it('422_affiche_le_message_generique_sans_designer_le_champ_fautif', async () => {
    // Message volontairement unique : dire lequel des trois champs est faux
    // permettrait d'énumérer les comptes existants.
    clientMock.post.mockRejectedValue(
      erreurHttp(422, { success: false, message: 'Identifiants invalides.' }),
    );

    await monter();
    await remplir({ code: '000000' });
    await soumettre();

    await waitFor(() =>
      expect(toastMock).toHaveBeenCalledWith(
        'error',
        'Échec de connexion',
        'Identifiants invalides.',
      ),
    );
    expect(router.replace).not.toHaveBeenCalled();
  });

  it('409_code_absent_explique_qu_il_faut_reclamer_le_code', async () => {
    clientMock.post.mockRejectedValue(
      erreurHttp(409, {
        success: false,
        code: 'code_absent',
        message: "Aucun code d'accès n'a encore été envoyé. Demandez-le à votre administration.",
      }),
    );

    await monter();
    await remplir({ code: '123456' });
    await soumettre();

    // Un message générique laisserait l'étudiant ressaisir indéfiniment un code
    // qu'il n'a jamais reçu : ce refus n'est pas de son fait.
    expect(await screen.findByText(/Demandez-le à votre administration/)).toBeTruthy();
    expect(toastMock).toHaveBeenCalledWith(
      'error',
      "Code d'accès manquant",
      "Aucun code d'accès n'a encore été envoyé. Demandez-le à votre administration.",
    );
  });
});
