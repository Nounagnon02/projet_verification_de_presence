import { describe, it, expect, beforeAll, afterAll, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { http } from 'msw';
import { server } from './msw/server';
import { succes } from './msw/handlers';
import AcademicYearComparison from '../pages/reports/AcademicYearComparison';

const API = '*/api';

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }));
afterAll(() => server.close());
afterEach(() => server.resetHandlers());

// L'API rend les années de la plus récente à la plus ancienne.
const annees = (stats) => {
  server.use(
    http.get(`${API}/admin/annees-academiques`, () =>
      succes(Object.keys(stats).map((libelle, i) => ({ id: i + 1, libelle }))),
    ),
    http.get(`${API}/admin/reports/semester/:id`, ({ params }) => {
      const libelle = Object.keys(stats)[Number(params.id) - 1];
      return succes(stats[libelle]);
    }),
  );
};

const afficher = () =>
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter>
        <AcademicYearComparison />
      </MemoryRouter>
    </QueryClientProvider>,
  );

const vide = { taux_presence: 0, total_etudiants: 0, total_presences: 0, total_evenements: 0 };

describe('Comparaison des années académiques', () => {
  // Regression : avec une seule année ayant des séances, l'écran affichait
  // « +0.0 % — Augmentation du taux de présence », et une moyenne qui comptait
  // les années vides pour 0 %.
  it("n'invente pas de progression avec une seule année de séances", async () => {
    annees({
      '2026-2027': vide,
      '2025-2026': { taux_presence: 27, total_etudiants: 59, total_presences: 125, total_evenements: 93 },
    });
    afficher();

    expect(await screen.findByText(/au moins deux années avec des séances/)).toBeInTheDocument();
    expect(screen.queryByText(/Augmentation/)).not.toBeInTheDocument();
    // Moyenne : la seule année avec séances, pas (27 + 0) / 2.
    expect(screen.getByText('Moyenne').nextElementSibling).toHaveTextContent('27%');
  });

  // Regression : l'API liste les années de la plus récente à la plus ancienne ;
  // la progression lisait la plus récente comme « première », d'où un signe
  // inversé (une baisse de 40 a 20 s'affichait en hausse).
  it('mesure la progression dans le sens chronologique', async () => {
    annees({
      '2025-2026': { taux_presence: 20, total_etudiants: 50, total_presences: 100, total_evenements: 40 },
      '2024-2025': { taux_presence: 40, total_etudiants: 50, total_presences: 200, total_evenements: 40 },
    });
    afficher();

    expect(await screen.findByText('-50.0%')).toBeInTheDocument();
    expect(screen.getByText(/Diminution du taux de présence entre 2024-2025 et 2025-2026/)).toBeInTheDocument();
  });
});
