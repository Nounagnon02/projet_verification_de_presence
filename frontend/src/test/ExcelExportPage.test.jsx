import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'

// vi.mock est remonté en tête de module : les doublures sont construites dans vi.hoisted.
const { api, enregistrer } = vi.hoisted(() => ({ api: { get: vi.fn() }, enregistrer: vi.fn() }))

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'token' }))
vi.mock('../utils/telechargement', async (importOriginal) => ({ ...(await importOriginal()), enregistrer }))

import ExcelExportPage from '../pages/reports/ExcelExportPage'

const NOM_SERVEUR = 'presences_du-2026-09-01_au-2026-09-14_export-2026-09-14.csv'
const dernierAppel = () => api.get.mock.calls.at(-1)[1].params

const exporter = async () => {
  const appels = api.get.mock.calls.length
  fireEvent.click(screen.getByRole('button', { name: /Générer l'export/ }))
  await waitFor(() => expect(api.get.mock.calls.length).toBe(appels + 1))
  return dernierAppel()
}

describe('Export CSV des rapports', () => {
  beforeEach(() => {
    // Lundi 14 septembre 2026 : seule la date est simulée.
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 8, 14, 10, 0))
    api.get.mockReset()
    api.get.mockResolvedValue({ data: 'csv', headers: { 'content-disposition': `attachment; filename=${NOM_SERVEUR}` } })
    enregistrer.mockReset()
  })

  afterEach(() => vi.useRealTimers())

  it('transmet la période choisie, et pas seulement la période personnalisée', async () => {
    render(<ExcelExportPage />)

    // « Ce mois », par défaut, n'envoyait aucune date : le fichier contenait tout.
    expect(await exporter()).toMatchObject({ date_debut: '2026-09-01', date_fin: '2026-09-14' })

    fireEvent.click(screen.getByRole('button', { name: 'Cette semaine' }))
    expect(await exporter()).toMatchObject({ date_debut: '2026-09-14', date_fin: '2026-09-14' })

    fireEvent.click(screen.getByRole('button', { name: 'Toute la période' }))
    const tout = await exporter()
    expect(tout.date_debut).toBeUndefined()
    expect(tout.date_fin).toBeUndefined()
  })

  it('ne propose plus de période sans définition sûre', () => {
    render(<ExcelExportPage />)

    expect(screen.queryByRole('button', { name: 'Ce semestre' })).toBeNull()
    expect(screen.queryByRole('button', { name: 'Cette année' })).toBeNull()
  })

  it('transmet les colonnes cochées', async () => {
    render(<ExcelExportPage />)
    fireEvent.click(screen.getByLabelText('Statut'))

    expect((await exporter()).colonnes).toBe('name,matricule,filiere,course,date,time')
  })

  it('enregistre le fichier sous le nom donné par le serveur', async () => {
    render(<ExcelExportPage />)
    await exporter()

    expect(enregistrer).toHaveBeenCalledWith('csv', NOM_SERVEUR)
  })
})
