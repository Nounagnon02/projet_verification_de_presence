import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { screen, waitFor, within, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

// vi.mock est remonté en tête de module : le client factice doit donc être
// construit dans vi.hoisted, sans quoi la référence n'existe pas encore.
const { api } = vi.hoisted(() => {
  const vide = () => Promise.resolve({ data: { success: true, data: [] } })
  return {
    api: {
      get: vi.fn(vide),
      post: vi.fn(vide),
      put: vi.fn(vide),
      patch: vi.fn(vide),
      delete: vi.fn(vide),
    },
  }
})

vi.mock('../api/axios', () => ({ default: api }))
vi.mock('../context/ToastContext', () => ({
  useToastCtx: () => ({ addToast: vi.fn() }),
}))

import SallesPage from '../pages/settings/SallesPage'

const vide = () => Promise.resolve({ data: { success: true, data: [] } })

// Telles que GET /admin/salles les rend : ce que chaque salle vérifie au scan,
// et son usage.
const SALLES = [
  { id: 1, nom: 'Salle A101', code: 'DEMO-A101', etablissement_id: 1, latitude: 6.4149, longitude: 2.3417, rayon_geofence_m: 50, hors_reseau: true, actif: true, verifie_gps: true, verifie_wifi: false, seances_a_venir: 0, creneaux_count: 0 },
  { id: 2, nom: 'Amphi C', code: 'Amphi C', etablissement_id: 1, latitude: null, longitude: null, rayon_geofence_m: 50, hors_reseau: false, actif: true, verifie_gps: false, verifie_wifi: false, seances_a_venir: 3, creneaux_count: 1 },
  { id: 3, nom: 'Labo Info 1', code: 'LABO-INFO-1', etablissement_id: 1, latitude: null, longitude: null, rayon_geofence_m: 50, hors_reseau: false, actif: true, verifie_gps: false, verifie_wifi: false, seances_a_venir: 0, creneaux_count: 0 },
  { id: 4, nom: 'Ancienne salle', code: 'OLD', etablissement_id: 1, latitude: null, longitude: null, rayon_geofence_m: 50, hors_reseau: false, actif: false, verifie_gps: false, verifie_wifi: false, seances_a_venir: 0, creneaux_count: 0 },
]

const avecSalles = () => api.get.mockImplementation((url) => {
  if (url === '/admin/salles') return Promise.resolve({ data: { success: true, data: SALLES } })
  if (url === '/user') return Promise.resolve({ data: { id: 1, etablissement: { id: 1, nom: 'IFRI' } } })
  return vide()
})

const titres = () => screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent)
const attendreLaListe = () => screen.findByRole('region', { name: 'État des salles' })

describe('SallesPage', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.post.mockReset()
    api.get.mockImplementation(vide)
    api.post.mockImplementation(vide)
  })

  afterEach(() => {
    delete window.navigator.geolocation
  })

  it('se monte et sort de son état de chargement', async () => {
    renderPage(<SallesPage />)

    await waitFor(() => {
      expect(screen.queryByText(/chargement/i)).not.toBeInTheDocument()
    })
  })

  it("ne lève pas d'erreur au montage", () => {
    expect(() => renderPage(<SallesPage />)).not.toThrow()
  })

  /**
   * Régression : la page n'annulait pas sa requête au démontage. Une réponse
   * arrivant après coup écrivait dans un composant démonté.
   */
  it('ignore une réponse arrivée après le démontage', async () => {
    let resoudre
    const lente = new Promise((r) => { resoudre = r })
    api.get.mockImplementation((url) => (url === '/admin/salles' ? lente : vide()))

    const { unmount } = renderPage(<SallesPage />)
    unmount()

    resoudre({ data: { success: true, data: [{ id: 1, nom: 'Amphi', code: 'A1' }] } })
    await lente

    expect(api.get).toHaveBeenCalledWith('/admin/salles', expect.anything())
  })

  // Seize cartes « GPS non configuré » d'affilée ne disaient pas que la
  // protection au scan était presque absente.
  it('dit ce que les salles vérifient réellement au scan', async () => {
    avecSalles()
    renderPage(<SallesPage />)

    const etat = await attendreLaListe()
    for (const [libelle, nombre] of [
      ['contrôlent la position (GPS)', '1'],
      ['contrôlent le réseau (Wi-Fi)', '0'],
      ['ne vérifient que le QR code', '2'],
      ['désactivées', '1'],
    ]) {
      expect(within(etat).getByText(libelle).closest('div')).toHaveTextContent(nombre)
    }
  })

  it('met en tête les salles utilisées, et signale celles qui ne vérifient que le QR code', async () => {
    avecSalles()
    renderPage(<SallesPage />)
    await attendreLaListe()

    expect(titres()[0]).toBe('Amphi C')
    expect(titres().at(-1)).toBe('Ancienne salle')

    const amphi = screen.getByRole('heading', { name: 'Amphi C' }).closest('article')
    expect(within(amphi).getByText('QR seul')).toBeInTheDocument()
    expect(within(amphi).getByText("3 séances à venir · 1 créneau à l'emploi du temps")).toBeInTheDocument()

    const protegee = screen.getByRole('heading', { name: 'Salle A101' }).closest('article')
    expect(within(protegee).getByText('QR + GPS')).toBeInTheDocument()
    expect(within(protegee).getByText('Hors réseau : pas de contrôle Wi-Fi')).toBeInTheDocument()
  })

  // Les imports renvoient sur /settings/salles?filtre=a-configurer.
  it('ouvre sur le filtre « À configurer » quand l’adresse le demande', async () => {
    avecSalles()
    renderPage(<SallesPage />, { route: '/settings/salles?filtre=a-configurer' })
    await attendreLaListe()

    expect(titres()).toEqual(['Amphi C', 'Labo Info 1'])
    expect(screen.getByRole('button', { name: /À configurer/ })).toHaveAttribute('aria-pressed', 'true')
  })

  // Sous PostgreSQL, « amphi » ne trouvait pas « Amphi C ».
  it('cherche sans tenir compte de la casse ni des accents', async () => {
    avecSalles()
    renderPage(<SallesPage />)
    await attendreLaListe()

    const recherche = screen.getByRole('searchbox', { name: 'Rechercher une salle' })
    fireEvent.change(recherche, { target: { value: 'amphi' } })
    expect(titres()).toEqual(['Amphi C'])

    fireEvent.change(recherche, { target: { value: 'ANCIENNE' } })
    expect(titres()).toEqual(['Ancienne salle'])
  })

  it('relève la position de l’appareil, sans plage IP ni code obligatoire', async () => {
    avecSalles()
    const getCurrentPosition = vi.fn((succes) => succes({ coords: { latitude: 6.36512345, longitude: 2.41898765, accuracy: 12 } }))
    Object.defineProperty(window.navigator, 'geolocation', { value: { getCurrentPosition }, configurable: true })

    renderPage(<SallesPage />)
    fireEvent.click(await screen.findByRole('button', { name: /Ajouter une salle/ }))

    // Enregistrée et comparée au scan, la plage IP n'y refusait jamais rien.
    expect(screen.queryByText(/Plage IP/)).toBeNull()
    expect(screen.getByLabelText('Code unique')).not.toBeRequired()

    fireEvent.change(screen.getByLabelText('Nom de la salle *'), { target: { value: 'Labo 3' } })
    fireEvent.click(screen.getByRole('button', { name: /Utiliser ma position actuelle/ }))

    expect(screen.getByLabelText('Latitude')).toHaveValue(6.365123)
    expect(screen.getByLabelText('Longitude')).toHaveValue(2.418988)
    expect(screen.getByText(/Position relevée à ±12 m/)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => expect(api.post).toHaveBeenCalled())
    const [url, charge] = api.post.mock.calls[0]
    expect(url).toBe('/admin/salles')
    expect(charge.code).toBeUndefined()
    expect(charge).not.toHaveProperty('ip_range')
    expect(charge).toMatchObject({ nom: 'Labo 3', latitude: 6.365123, longitude: 2.418988, etablissement_id: 1 })
  })
})
