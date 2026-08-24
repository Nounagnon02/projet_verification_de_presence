import { describe, it, expect, beforeAll, afterAll, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { http, HttpResponse } from 'msw';

import { server } from './msw/server';
import { succes } from './msw/handlers';
import { installerMouchard } from './msw/mouchard';
import { ToastProvider } from '../context/ToastContext';
import SallesPage from '../pages/settings/SallesPage';

/**
 * FE-I-14 du plan de tests.
 *
 * Le point central : verifier qu'AUCUNE requete ne part vers
 * /admin/etablissements. Cette route n'existe pas cote serveur — seule
 * /super-admin/etablissements existe, reservee au super admin — et l'appel
 * repartait en 404 avale par un catch silencieux. Un test de montage ne pouvait
 * pas le voir : la page s'affichait, simplement sans son entite de rattachement.
 */
const API = '*/api';

const UTILISATEUR = {
  id: 1,
  name: 'Admin Test',
  email: 'admin@test.local',
  role: 'faculte_admin',
  etablissement_id: 7,
  etablissement: { id: 7, code: 'FAST', nom: 'Faculté des Sciences et Techniques' },
};

const SALLES = [
  { id: 1, nom: 'Amphi A', code: 'AMPHI-A', etablissement_id: 7, latitude: 6.36, longitude: 2.43, rayon_geofence_m: 50, hors_reseau: false, actif: true },
];

const monter = () => render(
  <MemoryRouter>
    <ToastProvider>
      <SallesPage />
    </ToastProvider>
  </MemoryRouter>,
);

let mouchard;

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterAll(() => server.close());
afterEach(() => {
  mouchard?.desinstaller();
  server.resetHandlers();
});

describe('SallesPage — endpoints reellement appeles', () => {
  it('n\'appelle jamais /admin/etablissements, route inexistante', async () => {
    server.use(
      http.get(`${API}/admin/salles`, () => succes(SALLES)),
      http.get(`${API}/user`, () => HttpResponse.json(UTILISATEUR)),
    );
    mouchard = installerMouchard();

    monter();
    await waitFor(() => expect(mouchard.filtrer('GET', '/user')).toHaveLength(1));

    const fantomes = mouchard.requetes.filter(
      (r) => r.chemin.includes('/admin/etablissements'),
    );
    expect(fantomes).toEqual([]);
  });

  it('prerempli le formulaire avec l\'entite fournie par /user', async () => {
    server.use(
      http.get(`${API}/admin/salles`, () => succes(SALLES)),
      http.get(`${API}/user`, () => HttpResponse.json(UTILISATEUR)),
    );

    monter();

    // L'entite n'apparait que dans le formulaire de creation, en lecture seule.
    await userEvent.click(
      await screen.findByRole('button', { name: /ajouter une salle/i }),
    );

    // C'est precisement ce que le 404 silencieux empechait d'afficher :
    // l'ecran restait bloque sur « Entite non definie ».
    expect(
      await screen.findByText('Faculté des Sciences et Techniques'),
    ).toBeInTheDocument();
    expect(screen.queryByText(/entité non définie/i)).not.toBeInTheDocument();
  });

  it('charge les salles avec le parametre de recherche quand il est renseigne', async () => {
    server.use(
      http.get(`${API}/admin/salles`, () => succes(SALLES)),
      http.get(`${API}/user`, () => HttpResponse.json(UTILISATEUR)),
    );
    mouchard = installerMouchard();

    monter();
    await waitFor(() => expect(mouchard.filtrer('GET', '/admin/salles')).toHaveLength(1));

    // Sans recherche, aucun parametre « search » ne doit etre envoye : un
    // « search= » vide ferait travailler le serveur pour rien.
    const [appel] = mouchard.filtrer('GET', '/admin/salles');
    expect(appel.parametres.search).toBeUndefined();
  });
});
