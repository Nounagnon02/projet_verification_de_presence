import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import DataTable from '../components/ui/DataTable'

describe('DataTable', () => {
  const columns = [
    { key: 'name', label: 'Nom', sortable: true },
    { key: 'email', label: 'Email' },
  ]

  const data = [
    { id: 1, name: 'Alice', email: 'alice@test.com' },
    { id: 2, name: 'Bob', email: 'bob@test.com' },
  ]

  it('renders loading skeleton when loading', () => {
    const { container } = render(
      <DataTable columns={columns} data={[]} loading={true} />
    )
    expect(container.querySelector('.animate-pulse')).toBeInTheDocument()
  })

  it('renders empty state when no data', () => {
    render(<DataTable columns={columns} data={[]} />)
    expect(screen.getByText('Aucune donnée')).toBeInTheDocument()
  })

  it('renders empty state with custom message', () => {
    render(<DataTable columns={columns} data={[]} emptyMessage="Vide" />)
    expect(screen.getByText('Vide')).toBeInTheDocument()
  })

  it('renders table with data', () => {
    render(<DataTable columns={columns} data={data} />)
    expect(screen.getByText('Alice')).toBeInTheDocument()
    expect(screen.getByText('Bob')).toBeInTheDocument()
  })

  it('renders column headers', () => {
    render(<DataTable columns={columns} data={data} />)
    expect(screen.getByText('Nom')).toBeInTheDocument()
    expect(screen.getByText('Email')).toBeInTheDocument()
  })

  it('calls onSort when clicking sortable header', async () => {
    const user = userEvent.setup()
    const onSort = vi.fn()
    render(<DataTable columns={columns} data={data} onSort={onSort} />)
    await user.click(screen.getByText('Nom'))
    expect(onSort).toHaveBeenCalledWith('name')
  })

  it('calls onRowClick when clicking a row', async () => {
    const user = userEvent.setup()
    const onRowClick = vi.fn()
    render(<DataTable columns={columns} data={data} onRowClick={onRowClick} />)
    await user.click(screen.getByText('Alice'))
    expect(onRowClick).toHaveBeenCalledWith(data[0])
  })

  it('sets aria-sort attribute on sorted column', () => {
    render(
      <DataTable columns={columns} data={data} sortField="name" sortDirection="asc" />
    )
    const nameHeader = screen.getByText('Nom').closest('th')
    expect(nameHeader).toHaveAttribute('aria-sort', 'ascending')
  })
})
