import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import FileDropZone from '../components/ui/FileDropZone'

describe('FileDropZone', () => {
  it('renders with default label', () => {
    render(<FileDropZone onFileSelect={() => {}} />)
    expect(screen.getByText(/Cliquez/)).toBeInTheDocument()
  })

  it('renders custom label', () => {
    render(<FileDropZone onFileSelect={() => {}} label="PDF" accept=".pdf" />)
    expect(screen.getByText(/Fichier PDF/)).toBeInTheDocument()
  })

  it('has button role when no file selected', () => {
    render(<FileDropZone onFileSelect={() => {}} />)
    expect(screen.getByRole('button')).toBeInTheDocument()
  })

  it('sets aria-label correctly', () => {
    render(<FileDropZone onFileSelect={() => {}} aria-label="Drop zone custom" />)
    expect(screen.getByLabelText('Drop zone custom')).toBeInTheDocument()
  })
})
