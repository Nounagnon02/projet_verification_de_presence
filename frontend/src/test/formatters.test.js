import { describe, it, expect } from 'vitest'
import { formatDate, formatDateTime, formatTime, formatPercentage, formatNumber } from '../utils/formatters'

describe('formatDate', () => {
  it('returns empty string for null/undefined', () => {
    expect(formatDate(null)).toBe('')
    expect(formatDate(undefined)).toBe('')
  })

  it('formats a valid date in French locale', () => {
    const result = formatDate('2026-07-22')
    expect(result).toContain('22')
    expect(result).toContain('juil')
    expect(result).toContain('2026')
  })
})

describe('formatPercentage', () => {
  it('returns em dash for null/undefined', () => {
    expect(formatPercentage(null)).toBe('—')
    expect(formatPercentage(undefined)).toBe('—')
  })

  it('rounds and appends %', () => {
    expect(formatPercentage(75.3)).toBe('75%')
    expect(formatPercentage(99.9)).toBe('100%')
    expect(formatPercentage(0)).toBe('0%')
  })
})

describe('formatNumber', () => {
  it('returns em dash for null/undefined', () => {
    expect(formatNumber(null)).toBe('—')
    expect(formatNumber(undefined)).toBe('—')
  })

  it('formats number in French locale', () => {
    const result = formatNumber(1234)
    expect(result).toContain('1')
    expect(result).toContain('234')
    expect(formatNumber(0)).toBe('0')
  })
})

describe('formatTime', () => {
  it('returns empty string for null/undefined', () => {
    expect(formatTime(null)).toBe('')
    expect(formatTime(undefined)).toBe('')
  })
})

describe('formatDateTime', () => {
  it('returns empty string for null/undefined', () => {
    expect(formatDateTime(null)).toBe('')
    expect(formatDateTime(undefined)).toBe('')
  })
})
