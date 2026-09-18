import { describe, it, expect, beforeAll, afterAll, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { http } from 'msw';

import { server } from './msw/server';
import { handlersParDefaut, coursParJeton, JETON_ETUDIANT_TEST, succes, echec } from './msw/handlers';
import { installerMouchard } from './msw/mouchard';
import { CLE_JETON_ETUDIANT } from '../api/etudiant';
import PresenceValidationPage from '../pages/attendance/PresenceValidationPage';

/**
 * FE-I-01 / FE-I-02 du plan de tests — integration de page avec MSW.
 *
 * Regression : le scan postait « identifiant_unique » + « scan_challenge »
 * dans le corps, sans aucune authentification — n'importe quel camarade de
 * promotion pouvait reconstituer l'identifiant et pointer a la place d'un
 * autre. La page authentifie desormais l'etudiant (POST /auth/student/login,
 * email + identifiant unique + code d'acces) et porte SON jeton, sous une cle
 * de stockage DISTINCTE de celle de l'administrateur (src/api/etudiant.js).
 *
 * FingerprintJS est double : il interroge le materiel du navigateur, ce que
 * jsdom ne fournit pas.
 */
vi.mock('@fingerprintjs/fingerprintjs', () => ({
  default: {
    load: () => Promise.resolve({
      get: () => Promise.resolve({
        visitorId: 'empreinte-de-test',
        confidence: { score: 0.99 },
        components: {},
      }),
    }),
  },
}));

const API = '*/api';
const TOKEN = '11111111-2222-3333-4444-555555555555';
const EMAIL = 'jean.doe@uac.bj';
const IDENTIFIANT = 'DOE_JOHN_22A1234_GLT_L3';
const CODE = '482913';

const monter = () => render(
  <MemoryRouter initialEntries={[`/attendance/validate?token=${TOKEN}`]}>
    <PresenceValidationPage />
  </MemoryRouter>,
);

const seConnecter = async ({ email = EMAIL, identifiant = IDENTIFIANT, code = CODE } = {}) => {
  await userEvent.type(await screen.findByLabelText(/^email$/i), email);
  await userEvent.type(screen.getByLabelText(/identifiant unique/i), identifiant);
  await userEvent.type(screen.getByLabelText(/code d'accès/i), code);
  await userEvent.click(screen.getByRole('button', { name: /se connecter/i }));
};

const valider = async () => {
  await userEvent.click(await screen.findByRole('button', { name: /valider ma présence/i }));
};

let mouchard;

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterAll(() => server.close());

beforeEach(() => localStorage.removeItem(CLE_JETON_ETUDIANT));

afterEach(() => {
  mouchard?.desinstaller();
  server.resetHandlers();
  localStorage.removeItem(CLE_JETON_ETUDIANT);
});

describe('PresenceValidationPage — connexion étudiante', () => {
  it('transmet email, identifiant unique et code, puis conserve le jeton sous sa propre clé', async () => {
    server.use(...handlersParDefaut);
    mouchard = installerMouchard();

    monter();
    await seConnecter();

    await waitFor(() => expect(mouchard.filtrer('POST', '/auth/student/login')).toHaveLength(1));
    const [{ corps }] = mouchard.filtrer('POST', '/auth/student/login');

    expect(corps).toEqual({ email: EMAIL, identifiant_unique: IDENTIFIANT, code: CODE });

    // Le jeton est écrit sous la clé ÉTUDIANTE, jamais celle de l'admin.
    await waitFor(() => expect(localStorage.getItem(CLE_JETON_ETUDIANT)).toBe(JETON_ETUDIANT_TEST));
    expect(localStorage.getItem('auth_token')).toBeNull();

    // Une fois connecté, le formulaire de connexion disparaît.
    expect(await screen.findByRole('button', { name: /valider ma présence/i })).toBeInTheDocument();
  });

  it("refuse localement un code incomplet, sans appeler le serveur", async () => {
    server.use(...handlersParDefaut);
    mouchard = installerMouchard();

    monter();
    await userEvent.type(await screen.findByLabelText(/^email$/i), EMAIL);
    await userEvent.type(screen.getByLabelText(/identifiant unique/i), IDENTIFIANT);
    await userEvent.type(screen.getByLabelText(/code d'accès/i), '123');
    await userEvent.click(screen.getByRole('button', { name: /se connecter/i }));

    expect(await screen.findByText(/6 chiffres/i)).toBeInTheDocument();
    expect(mouchard.filtrer('POST', '/auth/student/login')).toHaveLength(0);
  });

  it('un 409 « code_absent » explique qu\'il faut réclamer le code', async () => {
    server.use(
      coursParJeton(),
      http.post(`${API}/auth/student/login`, () =>
        echec("Aucun code d'accès n'a encore été envoyé. Demandez-le à votre administration.", 409, { code: 'code_absent' }),
      ),
    );

    monter();
    await seConnecter();

    expect(await screen.findByText(/demandez-le à votre administration/i)).toBeInTheDocument();
    expect(localStorage.getItem(CLE_JETON_ETUDIANT)).toBeNull();
  });

  it('un 422 affiche le message générique, sans désigner le champ fautif', async () => {
    server.use(
      coursParJeton(),
      http.post(`${API}/auth/student/login`, () => echec('Identifiants invalides.', 422)),
    );

    monter();
    await seConnecter();

    expect(await screen.findByText(/identifiants invalides/i)).toBeInTheDocument();
    expect(localStorage.getItem(CLE_JETON_ETUDIANT)).toBeNull();
  });
});

describe('PresenceValidationPage — scan authentifié', () => {
  it("porte le jeton étudiant en Authorization, sans identifiant ni défi dans le corps", async () => {
    localStorage.setItem(CLE_JETON_ETUDIANT, JETON_ETUDIANT_TEST);
    server.use(
      ...handlersParDefaut,
      http.post(`${API}/presence/scan`, () =>
        succes({ etudiant: 'Doe John', cours: 'Algorithmique' }, 'Présence validée avec succès !'),
      ),
    );
    mouchard = installerMouchard();

    monter();
    await valider();

    await waitFor(() => expect(mouchard.filtrer('POST', '/presence/scan')).toHaveLength(1));
    const [{ corps, autorisation }] = mouchard.filtrer('POST', '/presence/scan');

    // Régression : ni « identifiant_unique » ni « scan_challenge » — l'étudiant
    // est désigné par le jeton, pas par le corps de la requête.
    expect(corps).not.toHaveProperty('identifiant_unique');
    expect(corps).not.toHaveProperty('scan_challenge');
    expect(corps.token).toBe(TOKEN);
    expect(corps.device_fingerprint).toBe('empreinte-de-test');
    expect(autorisation).toBe(`Bearer ${JETON_ETUDIANT_TEST}`);

    expect(await screen.findByText(/présence validée/i)).toBeInTheDocument();
  });

  it('un 401 renvoie à la connexion et efface le jeton étudiant, pas celui de l\'admin', async () => {
    localStorage.setItem(CLE_JETON_ETUDIANT, JETON_ETUDIANT_TEST);
    localStorage.setItem('auth_token', 'jeton-admin-intact');
    server.use(
      ...handlersParDefaut,
      http.post(`${API}/presence/scan`, () => echec('Non authentifié.', 401)),
    );

    monter();
    await valider();

    await screen.findByText(/session a expiré/i);
    expect(localStorage.getItem(CLE_JETON_ETUDIANT)).toBeNull();
    // La session ADMIN, elle, n'a pas été touchée : deux jetons distincts.
    expect(localStorage.getItem('auth_token')).toBe('jeton-admin-intact');

    // « Réessayer » retourne à l'écran principal : le jeton étant effacé, il
    // affiche le formulaire de connexion étudiant, jamais une redirection admin.
    await userEvent.click(screen.getByRole('button', { name: /réessayer/i }));
    expect(await screen.findByLabelText(/^email$/i)).toBeInTheDocument();
  });

  it("n'affiche pas le formulaire de connexion une fois le jeton présent", async () => {
    localStorage.setItem(CLE_JETON_ETUDIANT, JETON_ETUDIANT_TEST);
    server.use(...handlersParDefaut);

    monter();

    expect(await screen.findByRole('button', { name: /valider ma présence/i })).toBeInTheDocument();
    expect(screen.queryByLabelText(/^email$/i)).not.toBeInTheDocument();
  });
});

describe('PresenceValidationPage — restitution des refus du serveur', () => {
  const cas = [
    [410, 'Session expirée', /session expirée/i],
    [409, 'Présence déjà enregistrée', /déjà validée/i],
    [403, 'Vous ne semblez pas être dans la salle du cours.', /salle du cours/i],
  ];

  it.each(cas)('un %i affiche un message exploitable', async (statut, messageServeur, attendu) => {
    localStorage.setItem(CLE_JETON_ETUDIANT, JETON_ETUDIANT_TEST);
    server.use(
      ...handlersParDefaut,
      http.post(`${API}/presence/scan`, () => echec(messageServeur, statut)),
    );

    monter();
    await valider();

    expect(await screen.findByText(attendu)).toBeInTheDocument();
  });

  it('preserve le message du serveur sur un 403, plutot que de le remplacer', async () => {
    localStorage.setItem(CLE_JETON_ETUDIANT, JETON_ETUDIANT_TEST);
    server.use(
      ...handlersParDefaut,
      http.post(`${API}/presence/scan`, () =>
        echec('La prise de présence est terminée depuis 10:10.', 403),
      ),
    );

    monter();
    await valider();

    expect(await screen.findByText(/terminée depuis 10:10/i)).toBeInTheDocument();
  });
});
