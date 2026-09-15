import { describe, it, expect } from 'vitest'
import { libelleRole } from '../utils/roles'

describe('Rôles en clair', () => {
  it('traduit les rôles connus, garde un rôle inconnu tel quel', () => {
    expect(libelleRole('super_admin')).toBe('Super administrateur')
    expect(libelleRole('faculte_admin')).toBe("Administrateur d'établissement")
    expect(libelleRole('inspecteur')).toBe('inspecteur')
    expect(libelleRole(undefined)).toBe('Administrateur')
  })
})
