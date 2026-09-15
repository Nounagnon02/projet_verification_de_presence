import { describe, it, expect } from 'vitest'
import { dateLocale, joursAvant, proposerAnneeSuivante, anneesApres, contenuAnnee } from '../utils/annees'

describe('Années académiques — utilitaires', () => {
  it('lit AAAA-MM-JJ comme un jour local, pas comme minuit UTC', () => {
    const d = dateLocale('2025-10-01')
    expect([d.getFullYear(), d.getMonth(), d.getDate()]).toEqual([2025, 9, 1])
    expect(dateLocale('')).toBeNull()
  })

  it('compte les jours restants, négatifs une fois la date passée', () => {
    const aujourdhui = new Date(2026, 8, 14, 23, 30)
    expect(joursAvant('2026-09-30', aujourdhui)).toBe(16)
    expect(joursAvant('2026-09-14', aujourdhui)).toBe(0)
    expect(joursAvant('2026-09-10', aujourdhui)).toBe(-4)
  })

  it("propose l'année qui suit la plus récente, dates décalées d'un an", () => {
    const annees = [
      { libelle: '2024-2025', date_debut: '2024-10-01', date_fin: '2025-09-30' },
      { libelle: '2025-2026', date_debut: '2025-10-01', date_fin: '2026-09-30' },
    ]
    expect(proposerAnneeSuivante(annees)).toEqual({ libelle: '2026-2027', date_debut: '2026-10-01', date_fin: '2027-09-30' })
    expect(proposerAnneeSuivante([{ libelle: '2027-2028', date_debut: '2027-11-02', date_fin: '2028-02-29' }]).date_fin).toBe('2029-02-28')
  })

  it("ne garde que les années postérieures, la plus proche d'abord", () => {
    const annees = [{ id: 1, date_debut: '2027-10-01' }, { id: 2, date_debut: '2024-10-01' }, { id: 3, date_debut: '2026-10-01' }]
    expect(anneesApres(annees, { date_debut: '2025-10-01' }).map((a) => a.id)).toEqual([3, 1])
  })

  it('résume le contenu en omettant ce qui est vide', () => {
    expect(contenuAnnee({ etudiants_count: 1, ues_count: 0, emplois_du_temps_count: 2, evenements_count: 0 }))
      .toBe("1 étudiant · 2 créneaux d'emploi du temps")
    expect(contenuAnnee({})).toBe('')
  })
})
