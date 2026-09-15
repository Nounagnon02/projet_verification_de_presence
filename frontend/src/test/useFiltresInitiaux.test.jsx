import { describe, it, expect, beforeAll, afterAll, afterEach, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { server } from './msw/server';
import { succes } from './msw/handlers';
import useFiltresAcademiques from '../hooks/useFiltresAcademiques';

const API = '*/api';

const ANNEES = [
  { id: 1, libelle: '2023-2024' },
  { id: 3, libelle: '2025-2026', active: true },
];

const TOUTES = [
  { id: 10, code: 'IM-L1', niveau: 'L1', semestres: [1, 2] },
  { id: 11, code: 'GL-M2', niveau: 'M2', semestres: [9, 10] },
];

const PAR_ANNEE = { 1: [TOUTES[0]], 3: TOUTES };

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterAll(() => server.close());

beforeEach(() => {
  server.use(
    http.get(`${API}/admin/annees-academiques`, () => succes(ANNEES)),
    http.get(`${API}/admin/filieres`, ({ request }) => {
      const annee = new URL(request.url).searchParams.get('annee_id');
      return succes(annee ? (PAR_ANNEE[annee] ?? []) : TOUTES);
    }),
  );
});

afterEach(() => server.resetHandlers());

/**
 * La grille des filières mène aux UE et aux étudiants d'une filière
 * (?filiere=…&annee=…) : les écrans doivent s'ouvrir sur ces filtres.
 */
describe('useFiltresAcademiques — valeurs initiales', () => {
  it("ouvre sur l'année et la filière données, plutôt que sur l'année active", async () => {
    const { result } = renderHook(() => useFiltresAcademiques({
      preselectionnerAnneeActive: true,
      initial: { annee: '1', filiere: '10' },
    }));

    await waitFor(() => expect(result.current.chargement).toBe(false));

    expect(result.current.annee).toBe('1');
    expect(result.current.filiere).toBe('10');
  });

  it("écarte une filière initiale absente de l'année", async () => {
    const { result } = renderHook(() => useFiltresAcademiques({ initial: { annee: '1', filiere: '11' } }));

    await waitFor(() => expect(result.current.chargement).toBe(false));
    await waitFor(() => expect(result.current.filiere).toBe(''));

    expect(result.current.annee).toBe('1');
  });
});
