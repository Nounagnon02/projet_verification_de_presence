import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor, fireEvent } from '@testing-library/react'
import { useState } from 'react'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))

import SelecteurGroupe from '../components/ui/SelecteurGroupe'
import GroupesPromotion from '../components/students/GroupesPromotion'

const ok = (data, message) => Promise.resolve({ data: { success: true, data, message } })

const GROUPES_TD = [
  { id: 5, libelle: 'G1', type: 'td', etudiants_count: 20, filiere: { id: 32, code: 'IM-L2' } },
  { id: 6, libelle: 'G2', type: 'td', etudiants_count: 19, filiere: { id: 32, code: 'IM-L2' } },
]

/** Un formulaire de séance réduit au type et au groupe. */
function Seance({ type: typeInitial, groupe: groupeInitial = '' }) {
  const [type, setType] = useState(typeInitial)
  const [groupe, setGroupe] = useState(groupeInitial)
  return (
    <>
      <label htmlFor="type">Type</label>
      <select id="type" value={type} onChange={(e) => setType(e.target.value)}>
        <option value="cm">CM</option>
        <option value="td">TD</option>
      </select>
      <SelecteurGroupe id="groupe" ecId={12} type={type} value={groupe} onChange={setGroupe} />
      <output data-testid="groupe-envoye">{groupe}</output>
    </>
  )
}

describe('Séance — groupe de TD ou de TP', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.get.mockImplementation(() => ok(GROUPES_TD))
  })

  it("ne propose aucun groupe pour un cours magistral : il réunit toute la promotion", () => {
    render(<Seance type="cm" />)
    expect(screen.queryByLabelText('Groupe')).not.toBeInTheDocument()
    expect(api.get).not.toHaveBeenCalled()
  })

  it('propose les groupes de TD du cours, toute la promotion par défaut', async () => {
    render(<Seance type="td" />)
    expect(await screen.findByRole('option', { name: 'G1 — IM-L2 (20 étudiants)' })).toBeInTheDocument()
    expect(screen.getByLabelText('Groupe')).toHaveValue('')
    expect(api.get).toHaveBeenCalledWith('/admin/groupes', { params: { ec_id: '12', type: 'td' } })
  })

  // Un groupe resté caché partait avec la séance et le serveur la refusait.
  it('efface le groupe quand la séance redevient un cours magistral', async () => {
    render(<Seance type="td" groupe="5" />)
    await screen.findByRole('option', { name: /G1/ })
    expect(screen.getByTestId('groupe-envoye')).toHaveTextContent('5')

    fireEvent.change(screen.getByLabelText('Type'), { target: { value: 'cm' } })
    await waitFor(() => expect(screen.getByTestId('groupe-envoye')).toHaveTextContent(''))
  })
})

describe('Étudiants — groupes d\'une promotion', () => {
  const ANNEE = { id: 3, libelle: '2025-2026', active: true, close: false }
  const FILIERES = [{ id: 32, code: 'IM-L2', intitule: 'Informatique (L2)' }]

  beforeEach(() => {
    api.get.mockReset()
    api.post.mockReset()
    api.get.mockImplementation(() => ok(GROUPES_TD))
  })

  it('répartit la promotion et annonce les effectifs', async () => {
    const message = '39 étudiant(s) de IM-L2 répartis en 3 groupe(s) de TD : G1 (13), G2 (13), G3 (13).'
    api.post.mockImplementation(() => ok({ effectifs: { G1: 13, G2: 13, G3: 13 } }, message))
    const onModifie = vi.fn()

    render(<GroupesPromotion isOpen onClose={() => {}} annees={[ANNEE]} filieres={FILIERES} filiereInitiale="32" anneeInitiale="3" onModifie={onModifie} />)
    expect(await screen.findByText('G2')).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Nombre'), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: 'Répartir' }))

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/admin/groupes/repartir', { filiere_id: 32, annee_id: 3, type: 'td', nombre: 3 }))
    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(onModifie).toHaveBeenCalled()
  })

  it("se consulte seulement quand l'année est close", async () => {
    render(<GroupesPromotion isOpen onClose={() => {}} annees={[{ ...ANNEE, active: false, close: true }]} filieres={FILIERES} filiereInitiale="32" anneeInitiale="3" />)
    await screen.findByText('G1')

    expect(screen.getByRole('button', { name: 'Répartir' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Supprimer le groupe de TD G1' })).toBeDisabled()
  })
})
