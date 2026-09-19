import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { http } from 'msw'
import { server } from './msw/server'
import { succes } from './msw/handlers'

const API = '*/api'

// Reponse REELLE de POST /admin/import/courses, telle que
// ImportController la construit : successResponse(['analysis_id' => ..., ...]).
const REPONSE_IMPORT = { analysis_id: 42, status: 'pending' }

const navigations = []
vi.mock('react-router-dom', async () => {
  const reel = await vi.importActual('react-router-dom')
  return { ...reel, useNavigate: () => (chemin) => navigations.push(chemin) }
})

vi.mock('../context/ToastContext', () => ({
  useToastCtx: () => ({ addToast: () => {} }),
}))

import UEManagementPage from '../pages/courses/UEManagementPage'

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }))
afterAll(() => server.close())

beforeEach(() => {
  navigations.length = 0
  sessionStorage.clear()
  server.use(
    http.get(`${API}/admin/annees-academiques`, () => succes([])),
    http.get(`${API}/admin/filieres`, () => succes([])),
    http.get(`${API}/admin/ues`, () => succes([])),
    http.post(`${API}/admin/import/courses`, () => succes(REPONSE_IMPORT, 'Analyse lancée.')),
  )
})

afterEach(() => server.resetHandlers())

async function lancerAnalyse() {
  const user = userEvent.setup()

  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: 0 } } })
  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter><UEManagementPage /></MemoryRouter>
    </QueryClientProvider>,
  )

  await user.click(await screen.findByRole('button', { name: /Import en masse/i }))

  const champ = document.querySelector('input[type="file"]')
  await user.upload(champ, new File(['%PDF-1.4'], 'maquette.pdf', { type: 'application/pdf' }))

  await user.click(screen.getByRole('button', { name: /Analyser avec l'IA/i }))

  return user
}

describe("Passage de relais de l'import IA", () => {
  // Regression : l'identifiant etait lu sur « data.data.id » puis
  // « data.analysis_id » a la racine. L'API le place sur « data.analysis_id ».
  // Il etait donc TOUJOURS indefini, et l'ecran de progression affichait
  // « Aucune analyse en cours » alors que le serveur avait bel et bien analyse
  // le document.
  it("depose l'identifiant renvoye par le serveur", async () => {
    await lancerAnalyse()

    await waitFor(() => {
      const depose = JSON.parse(sessionStorage.getItem('import_en_cours') ?? 'null')
      expect(depose).not.toBeNull()
      expect(depose.analysis_id).toBe(42)
      expect(depose.type).toBe('courses')
    })
  })

  it("mene a l'ecran de progression", async () => {
    await lancerAnalyse()

    await waitFor(() => expect(navigations).toContain('/import/ai-analysis'))
  })

  // La cle de passage de relais doit rester DISTINCTE de « import_analysis »,
  // qui porte le resultat d'une analyse d'emploi du temps : la meme cle pour
  // les deux se serait ecrasee d'un import a l'autre.
  it("n'ecrase pas la cle qui porte un resultat d'analyse", async () => {
    sessionStorage.setItem('import_analysis', JSON.stringify({ result: { courses: ['temoin'] } }))

    await lancerAnalyse()

    await waitFor(() => expect(sessionStorage.getItem('import_en_cours')).not.toBeNull())

    const temoin = JSON.parse(sessionStorage.getItem('import_analysis'))
    expect(temoin.result.courses).toEqual(['temoin'])
  })

  it("refuse de naviguer si le serveur n'a renvoye aucun identifiant", async () => {
    server.use(http.post(`${API}/admin/import/courses`, () => succes({ status: 'pending' })))

    await lancerAnalyse()

    await waitFor(() => expect(screen.getByText(/n'a pas renvoyé d'identifiant/i)).toBeInTheDocument())
    expect(navigations).not.toContain('/import/ai-analysis')
    expect(sessionStorage.getItem('import_en_cours')).toBeNull()
  })
})
