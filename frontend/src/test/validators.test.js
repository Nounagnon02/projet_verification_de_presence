import { describe, it, expect } from 'vitest'
import { required, email, matricule, minLength } from '../utils/validators'

describe('required', () => {
  it('returns error for empty string', () => {
    expect(required('')).toBe('Ce champ est requis')
  })

  it('returns error for whitespace only', () => {
    expect(required('   ')).toBe('Ce champ est requis')
  })

  it('returns error for null/undefined', () => {
    expect(required(null)).toBe('Ce champ est requis')
    expect(required(undefined)).toBe('Ce champ est requis')
  })

  it('returns null for valid value', () => {
    expect(required('hello')).toBeNull()
  })
})

describe('email', () => {
  it('returns null for empty', () => {
    expect(email('')).toBeNull()
  })

  it('validates correct emails', () => {
    expect(email('test@example.com')).toBeNull()
    expect(email('user@domain.co')).toBeNull()
  })

  it('rejects invalid emails', () => {
    expect(email('not-an-email')).toBe('Email invalide')
    expect(email('@domain.com')).toBe('Email invalide')
    expect(email('test@')).toBe('Email invalide')
  })
})

describe('matricule', () => {
  it('returns null for empty', () => {
    expect(matricule('')).toBeNull()
  })

  it('validates 8-12 digit matricules', () => {
    expect(matricule('12345678')).toBeNull()
    expect(matricule('123456789012')).toBeNull()
  })

  it('rejects invalid matricules', () => {
    expect(matricule('abc')).toBe('Matricule invalide (8 à 12 chiffres)')
    expect(matricule('1234')).toBe('Matricule invalide (8 à 12 chiffres)')
  })
})

describe('minLength', () => {
  it('returns null for empty', () => {
    expect(minLength(3)('')).toBeNull()
  })

  it('returns null when length meets minimum', () => {
    expect(minLength(3)('abc')).toBeNull()
    expect(minLength(3)('abcd')).toBeNull()
  })

  it('returns error when too short', () => {
    expect(minLength(5)('abc')).toBe('Minimum 5 caractères')
  })
})
