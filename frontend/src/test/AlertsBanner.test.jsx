import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import AlertsBanner from '../components/ui/AlertsBanner'

/**
 * Le test precedent ne verifiait que la PRESENCE du texte « Voir la liste ».
 * C'est ainsi qu'un <button> sans gestionnaire — qui ne menait nulle part — a
 * pu vivre longtemps : le libelle etait bien la, l'action non.
 */
const monter = (alerts, props = {}) => render(
  <MemoryRouter>
    <AlertsBanner alerts={alerts} {...props} />
  </MemoryRouter>,
)

const UNE = [{ title: 'verification_echouee', message: 'GPS hors zone' }]

describe('AlertsBanner', () => {
  it('affiche le titre et le message de la premiere anomalie', () => {
    monter(UNE)
    expect(screen.getByText('verification_echouee')).toBeInTheDocument()
    expect(screen.getByText('GPS hors zone')).toBeInTheDocument()
  })

  it('« Voir la liste » est un lien qui mene a la file d\'attente', () => {
    monter(UNE)
    const lien = screen.getByRole('link', { name: /voir la liste/i })
    expect(lien).toHaveAttribute('href', '/attendance/queue')
  })

  it('la destination est surchargeable', () => {
    monter(UNE, { to: '/ailleurs' })
    expect(screen.getByRole('link', { name: /voir la liste/i }))
      .toHaveAttribute('href', '/ailleurs')
  })

  it('annonce le nombre d\'anomalies restantes', () => {
    // Le bandeau ne montre que la premiere : sans ce decompte, rien n'indique
    // qu'il en reste a traiter.
    monter([...UNE, { title: 'b', message: 'B' }, { title: 'c', message: 'C' }])
    expect(screen.getByText(/2 autres anomalies ouvertes/)).toBeInTheDocument()
  })

  it('accorde le singulier pour une seule anomalie restante', () => {
    monter([...UNE, { title: 'b', message: 'B' }])
    expect(screen.getByText(/1 autre anomalie ouverte/)).toBeInTheDocument()
  })

  it('n\'annonce aucun reste quand il n\'y a qu\'une anomalie', () => {
    monter(UNE)
    expect(screen.queryByText(/autre anomalie/)).not.toBeInTheDocument()
  })
})
