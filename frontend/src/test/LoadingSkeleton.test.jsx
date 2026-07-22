import { describe, it, expect } from 'vitest'
import { render } from '@testing-library/react'
import LoadingSkeleton from '../components/ui/LoadingSkeleton'

describe('LoadingSkeleton', () => {
  it('renders default table skeleton', () => {
    const { container } = render(<LoadingSkeleton />)
    const rows = container.querySelectorAll('.flex.gap-4')
    expect(rows.length).toBeGreaterThanOrEqual(1)
  })

  it('renders card skeleton with correct columns', () => {
    const { container } = render(<LoadingSkeleton type="card" cols={2} />)
    const grid = container.querySelector('.grid')
    expect(grid.className).toContain('grid-cols-1')
    const cards = container.querySelectorAll('.animate-pulse')
    expect(cards.length).toBeGreaterThanOrEqual(1)
  })

  it('renders specified number of rows', () => {
    const { container } = render(<LoadingSkeleton rows={5} cols={3} />)
    const rows = container.querySelectorAll('.flex.gap-4')
    expect(rows).toHaveLength(5)
  })
})
