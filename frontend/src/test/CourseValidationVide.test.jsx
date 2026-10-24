import { describe, it, expect, beforeAll, afterAll, beforeEach, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { http } from 'msw';
import { server } from './msw/server';
import { succes } from './msw/handlers';

const API = '*/api';

vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast: () => {} }) }));

import CourseValidationPage from '../pages/import/CourseValidationPage';

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterAll(() => server.close());

beforeEach(() => {
  sessionStorage.clear();
  server.use(
    http.get(`${API}/admin/annees-academiques`, () => succes([{ id: 3, libelle: '2025-2026', active: true }])),
    http.get(`${API}/admin/filieres`, () => succes([])),
  );
});

describe('Validation des cours sans import en attente', () => {
  // Regression : sans rien en session, l'ecran affichait une UE vide etiquetee
  // « Derive de l'emploi du temps » avec une « Confiance IA : 0 % », alors
  // qu'aucun document ni aucune IA n'avait servi.
  it('annonce qu il n y a rien a valider', () => {
    render(
      <MemoryRouter>
        <CourseValidationPage />
      </MemoryRouter>,
    );

    expect(screen.getByRole('heading', { name: 'Rien à valider' })).toBeInTheDocument();
    expect(screen.queryByText(/Confiance IA/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Dérivé de l'emploi du temps/)).not.toBeInTheDocument();
  });

  // La saisie manuelle existait (ligne vierge) : elle reste accessible.
  it('ouvre la saisie manuelle sans pretendre a une analyse', async () => {
    const user = userEvent.setup();
    render(
      <MemoryRouter>
        <CourseValidationPage />
      </MemoryRouter>,
    );

    await user.click(screen.getByRole('button', { name: 'Saisir à la main' }));

    expect(screen.getByRole('heading', { name: 'Saisir des cours' })).toBeInTheDocument();
    expect(screen.getAllByText('Saisie manuelle').length).toBeGreaterThan(0);
    expect(screen.queryByText(/Confiance IA/)).not.toBeInTheDocument();
    expect(screen.queryByText('Document source')).not.toBeInTheDocument();
  });
});
