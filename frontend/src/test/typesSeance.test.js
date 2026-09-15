import { describe, it, expect } from 'vitest'
import { restantesPour, libelleVolumes } from '../utils/typesSeance'

describe('Types de séance et volumes', () => {
  it('donne le reste du type choisi, le total pour un EC à ventiler, rien pour une évaluation', () => {
    const ventile = { heures_restantes: 9, heures_restantes_par_type: { cm: 4, td: 3, tp: 2 } }
    expect(restantesPour(ventile, 'td')).toBe(3)
    expect(restantesPour({ heures_restantes: 9, heures_restantes_par_type: null }, 'tp')).toBe(9)
    expect(restantesPour(ventile, 'evaluation')).toBeNull()
  })

  it('résume les volumes par type, ou signale un EC à ventiler', () => {
    expect(libelleVolumes({ volume_cm: 10, volume_td: 0, volume_tp: 0, volume_td_tp: 15, volume_horaire: 25 })).toBe('CM 10h · TP/TD 15h = 25h')
    expect(libelleVolumes({ volume_a_ventiler: true, volume_horaire: 40 })).toBe('40h — à ventiler entre CM, TD et TP')
  })
})
