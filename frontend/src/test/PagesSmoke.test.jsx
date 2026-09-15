import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderPage } from './utils/renderPage'

// vi.mock est remonté en tête de module : le client factice doit être construit
// dans vi.hoisted.
const { api } = vi.hoisted(() => {
  const vide = () => Promise.resolve({ data: { success: true, data: [] } })
  return {
    api: {
      get: vi.fn(vide),
      post: vi.fn(vide),
      put: vi.fn(vide),
      patch: vi.fn(vide),
      delete: vi.fn(vide),
    },
  }
})

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'token' }))
vi.mock('../context/ToastContext', () => ({
  useToastCtx: () => ({ addToast: vi.fn() }),
  ToastProvider: ({ children }) => children,
}))

import ScansRefusesPage from '../pages/attendance/ScansRefusesPage'
import SaisieManuellePage from '../pages/attendance/SaisieManuellePage'
import PresenceHistoryPage from '../pages/attendance/PresenceHistoryPage'
import PresenceValidationPage from '../pages/attendance/PresenceValidationPage'
import UEManagementPage from '../pages/courses/UEManagementPage'
import AIAnalysisProgressPage from '../pages/import/AIAnalysisProgressPage'
import CourseValidationPage from '../pages/import/CourseValidationPage'
import ScheduleValidationPage from '../pages/import/ScheduleValidationPage'
import NotificationsPage from '../pages/notifications/NotificationsPage'
import ProfilePage from '../pages/profile/ProfilePage'
import SemesterComparison from '../pages/reports/SemesterComparison'
import AcademicSlatePage from '../pages/settings/AcademicSlatePage'
import EtablissementManagementPage from '../pages/super-admin/EtablissementManagementPage'
import EvenementManagementPage from '../pages/events/EvenementManagementPage'
import StudentManagementPage from '../pages/students/StudentManagementPage'
import ReportsPage from '../pages/reports/ReportsPage'
import FilteredReportsPage from '../pages/reports/FilteredReportsPage'

const vide = () => Promise.resolve({ data: { success: true, data: [] } })

/**
 * Filet de montage pour les pages qui n'avaient aucun test.
 *
 * Ces pages échappaient entièrement à la suite, et c'est ce qui a laissé passer
 * une icône utilisée sans être importée — invisible à la compilation, fatale au
 * rendu. Un montage suffit à détecter cette classe d'erreur ainsi que les accès
 * à des données absentes, et il sert de garde-fou avant de retoucher leur
 * chargement de données.
 *
 * Ce n'est pas un test fonctionnel : il ne vérifie pas ce que la page affiche.
 * Les pages dont le comportement mérite d'être vérifié ont leur propre fichier.
 */
const PAGES = [
  ['ScansRefusesPage', ScansRefusesPage],
  ['SaisieManuellePage', SaisieManuellePage],
  ['PresenceHistoryPage', PresenceHistoryPage],
  ['PresenceValidationPage', PresenceValidationPage],
  ['UEManagementPage', UEManagementPage],
  ['AIAnalysisProgressPage', AIAnalysisProgressPage],
  ['CourseValidationPage', CourseValidationPage],
  ['ScheduleValidationPage', ScheduleValidationPage],
  ['NotificationsPage', NotificationsPage],
  ['ProfilePage', ProfilePage],
  ['SemesterComparison', SemesterComparison],
  ['AcademicSlatePage', AcademicSlatePage],
  ['EtablissementManagementPage', EtablissementManagementPage],
  ['EvenementManagementPage', EvenementManagementPage],
  ['StudentManagementPage', StudentManagementPage],
  ['ReportsPage', ReportsPage],
  ['FilteredReportsPage', FilteredReportsPage],
]

describe('Montage des pages', () => {
  beforeEach(() => {
    api.get.mockImplementation(vide)
    api.post.mockImplementation(vide)
  })

  it.each(PAGES)('%s se monte sans lever d\'erreur', (_nom, Page) => {
    expect(() => renderPage(<Page />)).not.toThrow()
  })

  it.each(PAGES)('%s se démonte proprement', (_nom, Page) => {
    const { unmount } = renderPage(<Page />)
    expect(() => unmount()).not.toThrow()
  })
})
