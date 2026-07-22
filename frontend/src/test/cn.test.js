import { describe, it, expect } from 'vitest'
import cn from '../utils/cn'

describe('cn', () => {
  it('joins class names with space', () => {
    expect(cn('a', 'b', 'c')).toBe('a b c')
  })

  it('filters out falsy values', () => {
    expect(cn('a', false, 'b', null, undefined, 0, 'c')).toBe('a b c')
  })

  it('returns empty string for no args', () => {
    expect(cn()).toBe('')
  })

  it('returns empty string for all falsy', () => {
    expect(cn(false, null, undefined)).toBe('')
  })
})
