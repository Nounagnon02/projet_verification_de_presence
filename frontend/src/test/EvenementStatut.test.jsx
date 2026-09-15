import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, fireEvent, within } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

// vi.mock est remonté en tête de module : le client factice doit être construit
// dans vi.hoisted.
const { api } = vi.hoisted(() => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'token' }))

import EvenementManagementPage from '../pages/events/EvenementManagementPage'

const SEANCE = {
  id: 7,
  date: '2030-01-15',
  heure_debut: '08:00:00',
  heure_fin: '10:00:00',
  statut: 'planifie',
  ec: { id: 3, code: 'ALG', intitule: 'Algorithmique' },
  filiere: { id: 2, code: 'IM-L1' },
  annee_id: 1,
  salle: null,
  salle_id: null,
}

// La barre de filtres a son propre choix de statut : on vise celui du formulaire.
const champStatut = () => document.getElementById('statut-evenement')

describe('Statut dans le formulaire des événements', () => {
  beforeEach(() => {
    api.get.mockImplementation((url) => Promise.resolve({
      data: { success: true, data: url === '/admin/evenements' ? [SEANCE] : [] },
    }))
  })

  it('ne laisse pas choisir le statut à la création', async () => {
    renderPage(<EvenementManagementPage />)
    await screen.findByTitle('Modifier')

    fireEvent.click(screen.getByRole('button', { name: /Nouvel événement/ }))

    expect(screen.getByRole('heading', { name: 'Nouvel événement' })).toBeInTheDocument()
    expect(champStatut()).toBeNull()
    expect(screen.queryByText(/Créé au statut/)).toBeNull()
  })

  it('propose tous les statuts en modification, annulation comprise', async () => {
    renderPage(<EvenementManagementPage />)
    fireEvent.click(await screen.findByTitle('Modifier'))

    expect(screen.getByRole('heading', { name: "Modifier l'événement" })).toBeInTheDocument()
    const options = within(champStatut()).getAllByRole('option').map((o) => o.textContent)
    expect(options).toEqual(['Planifié', 'En cours', 'Terminé', 'Annulé'])
    expect(champStatut()).toHaveValue('planifie')
  })
})
