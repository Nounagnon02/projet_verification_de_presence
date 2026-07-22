import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Tabs from '../components/ui/Tabs'

describe('Tabs', () => {
  const tabs = [
    { key: 'tab1', label: 'Premier' },
    { key: 'tab2', label: 'Deuxième' },
    { key: 'tab3', label: 'Troisième' },
  ]

  it('renders all tabs', () => {
    render(<Tabs tabs={tabs} activeTab="tab1" onChange={() => {}} />)
    expect(screen.getByText('Premier')).toBeInTheDocument()
    expect(screen.getByText('Deuxième')).toBeInTheDocument()
    expect(screen.getByText('Troisième')).toBeInTheDocument()
  })

  it('highlights the active tab', () => {
    render(<Tabs tabs={tabs} activeTab="tab2" onChange={() => {}} />)
    const activeBtn = screen.getByText('Deuxième')
    expect(activeBtn.className).toContain('bg-white')
    expect(activeBtn.className).toContain('text-primary')
  })

  it('calls onChange when clicking a tab', async () => {
    const user = userEvent.setup()
    let active = ''
    render(<Tabs tabs={tabs} activeTab="tab1" onChange={(k) => { active = k }} />)
    await user.click(screen.getByText('Deuxième'))
    expect(active).toBe('tab2')
  })

  it('works with simple string tabs', () => {
    const strTabs = ['A', 'B', 'C']
    render(<Tabs tabs={strTabs} activeTab="B" onChange={() => {}} />)
    expect(screen.getByText('A')).toBeInTheDocument()
    expect(screen.getByText('B')).toBeInTheDocument()
    expect(screen.getByText('C')).toBeInTheDocument()
  })
})
