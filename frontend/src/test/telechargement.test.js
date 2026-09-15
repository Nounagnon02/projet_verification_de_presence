import { describe, it, expect } from 'vitest'
import { nomFichierServeur } from '../utils/telechargement'

describe('nomFichierServeur', () => {
  it('lit le nom donné par le serveur', () => {
    expect(nomFichierServeur(
      { 'content-disposition': 'attachment; filename=historique_IM-L1_S1_export-2026-09-14.csv' },
      'repli.csv',
    )).toBe('historique_IM-L1_S1_export-2026-09-14.csv')
  })

  it('accepte un nom entre guillemets', () => {
    expect(nomFichierServeur({ 'content-disposition': 'attachment; filename="rapport.pdf"' }, 'repli.pdf')).toBe('rapport.pdf')
  })

  it('préfère la forme encodée, seule à garder les accents', () => {
    expect(nomFichierServeur(
      { 'content-disposition': "attachment; filename=presences.pdf; filename*=UTF-8''pr%C3%A9sences.pdf" },
      'repli.pdf',
    )).toBe('présences.pdf')
  })

  it('retombe sur le repli sans en-tête', () => {
    expect(nomFichierServeur({}, 'repli.csv')).toBe('repli.csv')
    expect(nomFichierServeur(undefined, 'repli.csv')).toBe('repli.csv')
  })
})
