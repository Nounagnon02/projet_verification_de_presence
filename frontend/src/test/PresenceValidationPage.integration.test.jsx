import { describe, it, expect, beforeAll, afterAll, afterEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { http } from 'msw';

import { server } from './msw/server';
import { handlersParDefaut, coursParJeton, succes, echec, DEFI_SERVEUR } from './msw/handlers';
import { installerMouchard } from './msw/mouchard';
import PresenceValidationPage from '../pages/attendance/PresenceValidationPage';

/**
 * FE-I-01 / FE-I-02 du plan de tests — integration de page avec MSW.
 *
 * Ces tests observent le CORPS des requetes sortantes, ce qu'un double du
 * module axios ne permet pas. C'est le seul niveau ou l'on peut prouver que le
 * client transmet bien le defi anti-fraude EMIS PAR LE SERVEUR, et non une
 * valeur qu'il aurait calculee lui-meme.
 *
 * FingerprintJS est double : il interroge le materiel du navigateur, ce que
 * jsdom ne fournit pas. Seule l'empreinte est simulee — le defi, lui, vient
 * bien de la reponse HTTP interceptee.
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

const monter = () => render(
  <MemoryRouter initialEntries={[`/attendance/validate?token=${TOKEN}`]}>
    <PresenceValidationPage />
  </MemoryRouter>,
);

const saisirEtValider = async (identifiant = 'DOE_JOHN_22A1234_GLT_L3') => {
  const champ = await screen.findByLabelText(/identifiant unique/i);
  await userEvent.type(champ, identifiant);
  await userEvent.click(screen.getByRole('button', { name: /valider ma présence/i }));
};

let mouchard;

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterAll(() => server.close());

afterEach(() => {
  mouchard?.desinstaller();
  server.resetHandlers();
});

describe('PresenceValidationPage — contrat des requetes sortantes', () => {
  it('transmet le defi emis par le serveur, et non une valeur calculee localement', async () => {
    server.use(
      ...handlersParDefaut,
      http.post(`${API}/presence/scan`, () =>
        succes({ etudiant: 'DOE JOHN', matricule: '22A1234', cours: 'Algorithmique' },
          'Présence validée avec succès !'),
      ),
    );
    mouchard = installerMouchard();

    monter();
    await saisirEtValider();

    await waitFor(() => expect(mouchard.filtrer('POST', '/presence/scan')).toHaveLength(1));

    const [{ corps }] = mouchard.filtrer('POST', '/presence/scan');

    // Le defaut B2 : le champ etait purement absent, d'ou un 422 systematique.
    expect(corps).toHaveProperty('scan_challenge');
    // Le defaut B1 : le defi etait derive d'un secret cote client, donc
    // toujours refuse. Il doit valoir exactement ce que le serveur a emis.
    expect(corps.scan_challenge).toBe(DEFI_SERVEUR);

    expect(corps.token).toBe(TOKEN);
    expect(corps.identifiant_unique).toBe('DOE_JOHN_22A1234_GLT_L3');
    expect(corps.device_fingerprint).toBe('empreinte-de-test');
  });

  it('interroge course-by-token une seule fois, avec le jeton de l\'URL', async () => {
    server.use(
      ...handlersParDefaut,
      http.post(`${API}/presence/scan`, () => succes({}, 'ok')),
    );
    mouchard = installerMouchard();

    monter();
    await screen.findByLabelText(/identifiant unique/i);

    const appels = mouchard.filtrer('GET', '/presence/course-by-token');
    expect(appels).toHaveLength(1);
    expect(appels[0].chemin).toContain(TOKEN);
  });

  it('n\'emet aucune requete de scan quand le defi manque dans la reponse', async () => {
    // Un serveur qui ne fournit pas de defi ne doit pas conduire le client a en
    // inventer un : la soumission est refusee avant tout appel reseau.
    server.use(
      coursParJeton({ scan_challenge: undefined }),
      http.post(`${API}/presence/scan`, () => succes({}, 'ne devrait pas etre appele')),
    );
    mouchard = installerMouchard();

    monter();
    await saisirEtValider();

    await screen.findByText(/QR Code invalide ou expiré/i);
    expect(mouchard.filtrer('POST', '/presence/scan')).toHaveLength(0);
  });

  it('n\'emet aucune requete quand l\'identifiant est vide', async () => {
    server.use(...handlersParDefaut);
    mouchard = installerMouchard();

    monter();
    const bouton = await screen.findByRole('button', { name: /valider ma présence/i });
    await userEvent.click(bouton);

    expect(await screen.findByText(/veuillez saisir votre identifiant unique/i)).toBeInTheDocument();
    expect(mouchard.filtrer('POST', '/presence/scan')).toHaveLength(0);
  });
});

describe('PresenceValidationPage — restitution des refus du serveur', () => {
  const cas = [
    [410, 'Session expirée', /session expirée/i],
    [409, 'Présence déjà enregistrée', /déjà validée/i],
    [403, 'Vous êtes à 240 m de la salle (rayon 50 m).', /240 m/i],
    [404, 'Identifiant étudiant inconnu.', /identifiant/i],
  ];

  it.each(cas)('un %i affiche un message exploitable', async (statut, messageServeur, attendu) => {
    server.use(
      ...handlersParDefaut,
      http.post(`${API}/presence/scan`, () => echec(messageServeur, statut)),
    );

    monter();
    await saisirEtValider();

    expect(await screen.findByText(attendu)).toBeInTheDocument();
  });

  it('preserve le message du serveur sur un 403, plutot que de le remplacer', async () => {
    // Sans cela, l'etudiant lit « Request failed with status code 403 » au lieu
    // de la distance qui le separe de la salle.
    server.use(
      ...handlersParDefaut,
      http.post(`${API}/presence/scan`, () =>
        echec('La prise de présence est terminée depuis 10:10.', 403),
      ),
    );

    monter();
    await saisirEtValider();

    expect(await screen.findByText(/terminée depuis 10:10/i)).toBeInTheDocument();
  });
});
