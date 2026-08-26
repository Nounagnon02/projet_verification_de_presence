import { describe, it, expect, beforeAll, afterAll, afterEach, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { http } from 'msw';
import { server } from './msw/server';
import { succes } from './msw/handlers';
import { installerMouchard } from './msw/mouchard';
import useFiltresAcademiques from '../hooks/useFiltresAcademiques';

const API = '*/api';

const ANNEES = [
  { id: 1, libelle: '2023-2024' },
  { id: 3, libelle: '2025-2026', active: true },
];

// Filiere presente dans aucune annee : le cas reel de DEMO-IM-L1, qui compte un
// etudiant mais aucune ligne de pivot. Elle doit rester visible sans filtre.
const TOUTES = [
  { id: 10, code: 'IM-L1', intitule: 'Informatique', niveau: 'L1', semestres: [1, 2] },
  { id: 11, code: 'GL-M2', intitule: 'Genie Logiciel', niveau: 'M2', semestres: [9, 10] },
  { id: 12, code: 'DEMO', intitule: 'Demonstration', niveau: 'L1', semestres: [] },
];

const PAR_ANNEE = {
  1: [],
  3: [TOUTES[0], TOUTES[1]],
};

let mouchard;

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
  mouchard = installerMouchard();
});

afterEach(() => {
  mouchard.desinstaller();
  server.resetHandlers();
});

/** Monte le hook et attend la fin du chargement initial. */
async function monter(options) {
  const rendu = renderHook(() => useFiltresAcademiques(options));
  await waitFor(() => expect(rendu.result.current.filieres.length).toBeGreaterThan(0));
  return rendu;
}

describe('useFiltresAcademiques', () => {
  it('charge la liste complete au montage, sans parametre d annee', async () => {
    const { result } = await monter();

    expect(result.current.filieres).toHaveLength(3);

    const appels = mouchard.filtrer('GET', '/admin/filieres');
    expect(appels).toHaveLength(1);
    expect(appels[0].parametres).toEqual({});
  });

  it('transmet annee_id au serveur quand une annee est choisie', async () => {
    const { result } = await monter();
    mouchard.vider();

    act(() => result.current.setAnnee('3'));

    await waitFor(() => {
      const appels = mouchard.filtrer('GET', '/admin/filieres');
      expect(appels).toHaveLength(1);
      expect(appels[0].parametres).toEqual({ annee_id: '3' });
    });
  });

  it('restreint les filieres a celles de l annee', async () => {
    const { result } = await monter();

    act(() => result.current.setAnnee('3'));

    await waitFor(() => expect(result.current.filieres).toHaveLength(2));
    expect(result.current.filieres.map((f) => f.code)).toEqual(['IM-L1', 'GL-M2']);
  });

  // Les listes deroulantes codaient « Semestre 1 a 6 » en dur alors que la
  // colonne ues.semestre va jusqu'a 10 : aucun semestre de Master n'etait
  // atteignable. Les options doivent venir des donnees.
  it('expose les semestres de Master, hors de la plage 1 a 6 codee en dur', async () => {
    const { result } = await monter();

    act(() => result.current.setAnnee('3'));
    await waitFor(() => expect(result.current.filieres).toHaveLength(2));

    expect(result.current.semestres).toEqual([1, 2, 9, 10]);
  });

  it('restreint les semestres a ceux de la filiere choisie', async () => {
    const { result } = await monter();

    act(() => result.current.setAnnee('3'));
    await waitFor(() => expect(result.current.filieres).toHaveLength(2));

    act(() => result.current.setFiliere('11'));
    await waitFor(() => expect(result.current.semestres).toEqual([9, 10]));
  });

  it('deduit les niveaux des filieres presentes, sans liste figee', async () => {
    const { result } = await monter();

    act(() => result.current.setAnnee('3'));
    await waitFor(() => expect(result.current.niveaux).toEqual(['L1', 'M2']));
  });

  it('remet filiere, niveau et semestre a zero quand l annee change', async () => {
    const { result } = await monter();

    act(() => result.current.setFiliere('10'));
    act(() => result.current.setNiveau('L1'));
    act(() => result.current.setSemestre('1'));
    expect(result.current.filiere).toBe('10');

    act(() => result.current.setAnnee('3'));

    await waitFor(() => {
      expect(result.current.filiere).toBe('');
      expect(result.current.niveau).toBe('');
      expect(result.current.semestre).toBe('');
    });
  });

  it('remet niveau et semestre a zero quand la filiere change', async () => {
    const { result } = await monter();

    act(() => result.current.setNiveau('L1'));
    act(() => result.current.setSemestre('1'));

    act(() => result.current.setFiliere('11'));

    await waitFor(() => {
      expect(result.current.niveau).toBe('');
      expect(result.current.semestre).toBe('');
    });
  });

  // Sans cela, l'ecran interrogerait le serveur avec un identifiant que la
  // liste ne propose plus, et n'afficherait rien sans raison visible.
  it('abandonne une filiere absente de la nouvelle annee', async () => {
    const { result } = await monter();

    act(() => result.current.setAnnee('3'));
    await waitFor(() => expect(result.current.filieres).toHaveLength(2));

    act(() => result.current.setFiliere('12'));

    await waitFor(() => expect(result.current.filiere).toBe(''));
  });

  it('signale une annee sans aucune filiere', async () => {
    const { result } = await monter();
    expect(result.current.anneeVide).toBe(false);

    act(() => result.current.setAnnee('1'));

    await waitFor(() => {
      expect(result.current.filieres).toHaveLength(0);
      expect(result.current.anneeVide).toBe(true);
    });
  });

  // Restreindre le formulaire de creation selon la barre de filtres serait un
  // contresens : on doit pouvoir creer un etudiant dans une filiere que l'on
  // n'est pas en train de consulter.
  it('conserve la liste complete pour les formulaires malgre le filtre', async () => {
    const { result } = await monter();

    act(() => result.current.setAnnee('3'));

    await waitFor(() => expect(result.current.filieres).toHaveLength(2));
    expect(result.current.filieresToutes).toHaveLength(3);
  });

  it('previent l ecran a chaque changement, pour remettre la pagination a un', async () => {
    let appels = 0;
    const { result } = await monter({ onChangement: () => { appels += 1; } });

    act(() => result.current.setAnnee('3'));
    act(() => result.current.setFiliere('10'));

    expect(appels).toBe(2);
  });

  // Le niveau est porté par la filière : proposer « L1 » alors que GL-M2 est
  // choisie invitait à demander au serveur une combinaison vide par
  // construction. Sur onze filières et cinq niveaux, cinquante-cinq
  // combinaisons étaient offertes, onze seulement pouvaient donner un résultat.
  it('restreint les niveaux a celui de la filiere choisie', async () => {
    const { result } = await monter();

    act(() => result.current.setAnnee('3'));
    await waitFor(() => expect(result.current.niveaux).toEqual(['L1', 'M2']));

    act(() => result.current.setFiliere('11'));

    await waitFor(() => expect(result.current.niveaux).toEqual(['M2']));
  });

  it('abandonne un niveau rendu impossible par la filiere', async () => {
    const { result } = await monter();

    act(() => result.current.setNiveau('L1'));
    expect(result.current.niveau).toBe('L1');

    // setFiliere remet déjà le niveau à zéro ; on vérifie ici le garde-fou qui
    // suit, celui du niveau absent des options.
    act(() => result.current.setFiliere('11'));
    await waitFor(() => expect(result.current.niveaux).toEqual(['M2']));

    act(() => result.current.setNiveau('L1'));

    await waitFor(() => expect(result.current.niveau).toBe(''));
  });

  // Un tableau de bord doit s'ouvrir sur l'exercice courant, sans qu'on ait à
  // le désigner. L'option n'est lue qu'au premier chargement.
  it('peut s ouvrir sur l annee active', async () => {
    const rendu = renderHook(() => useFiltresAcademiques({ preselectionnerAnneeActive: true }));

    await waitFor(() => expect(rendu.result.current.annee).toBe('3'));
    await waitFor(() => expect(rendu.result.current.filieres).toHaveLength(2));

    const appels = mouchard.filtrer('GET', '/admin/filieres');
    expect(appels.at(-1).parametres).toEqual({ annee_id: '3' });
  });

  it('n ouvre sur aucune annee par defaut', async () => {
    const { result } = await monter();

    expect(result.current.annee).toBe('');
    expect(result.current.filieres).toHaveLength(3);
  });
});
