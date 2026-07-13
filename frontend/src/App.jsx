import { lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import MainLayout from './components/layout/MainLayout';
import SuperAdminLayout from './components/layout/SuperAdminLayout';
import AttendanceLayout from './components/layout/AttendanceLayout';
import SettingsLayout from './components/layout/SettingsLayout';
import ProtectedRoute from './components/auth/ProtectedRoute';
import SkipLink from './components/ui/SkipLink';
import { useAuth } from './context/AuthContext';

// Lazy-loaded pages (using direct imports to prevent React 19 + Suspense remount issues)
import LoginPage from './pages/auth/LoginPage';
import StudentManagementPage from './pages/students/StudentManagementPage';
const DashboardPage = lazy(() => import('./pages/dashboard/DashboardPage'));
const PresenceValidationPage = lazy(() => import('./pages/attendance/PresenceValidationPage'));
const PresenceHistoryPage = lazy(() => import('./pages/attendance/PresenceHistoryPage'));
const ReportsPage = lazy(() => import('./pages/reports/ReportsPage'));
const HelpCenterPage = lazy(() => import('./pages/help/HelpCenterPage'));
const FAQPage = lazy(() => import('./pages/faq/FAQPage'));
const NotFoundPage = lazy(() => import('./pages/NotFoundPage'));

// Phase 3 - New pages
const QRValidationPage = lazy(() => import('./pages/attendance/QRValidationPage'));
const StudentStatsPage = lazy(() => import('./pages/attendance/StudentStatsPage'));
const WeeklySchedulePage = lazy(() => import('./pages/schedules/WeeklySchedulePage'));
const AcademicSlatePage = lazy(() => import('./pages/settings/AcademicSlatePage'));

// Phase 4 - Error pages
const SessionExpiredPage = lazy(() => import('./pages/auth/SessionExpiredPage'));
const LoginErrorPage = lazy(() => import('./pages/auth/LoginErrorPage'));

// Phase 5 - Import pages
const AIAnalysisProgressPage = lazy(() => import('./pages/import/AIAnalysisProgressPage'));
const ScheduleValidationPage = lazy(() => import('./pages/import/ScheduleValidationPage'));
const CourseValidationPage = lazy(() => import('./pages/import/CourseValidationPage'));

// Phase 6 - Report pages
const ReportPrintPreview = lazy(() => import('./pages/reports/ReportPrintPreview'));
const DepartmentFilterReport1 = lazy(() => import('./pages/reports/DepartmentFilterReport1'));
const DepartmentFilterReport2 = lazy(() => import('./pages/reports/DepartmentFilterReport2'));
const SemesterComparison = lazy(() => import('./pages/reports/SemesterComparison'));
const ProgramComparison = lazy(() => import('./pages/reports/ProgramComparison'));
const AcademicYearComparison = lazy(() => import('./pages/reports/AcademicYearComparison'));
const ExcelExportPage = lazy(() => import('./pages/reports/ExcelExportPage'));
const ProgramReportDetail = lazy(() => import('./pages/reports/ProgramReportDetail'));
const FilteredReportsPage = lazy(() => import('./pages/reports/FilteredReportsPage'));

// Phase 7 - Settings pages
const AcademicYearsPage = lazy(() => import('./pages/settings/AcademicYearsPage'));
const FilieresPage = lazy(() => import('./pages/settings/FilieresPage'));
const SecurityPage = lazy(() => import('./pages/settings/SecurityPage'));
const SallesPage = lazy(() => import('./pages/settings/SallesPage'));

// Landing page
const LandingPage = lazy(() => import('./pages/LandingPage'));

// Auth pages
const ForgotPasswordPage = lazy(() => import('./pages/auth/ForgotPasswordPage'));
const ResetPasswordPage = lazy(() => import('./pages/auth/ResetPasswordPage'));
const TermsOfServicePage = lazy(() => import('./pages/auth/TermsOfServicePage'));
const PrivacyPolicyPage = lazy(() => import('./pages/auth/PrivacyPolicyPage'));
const LegalNoticePage = lazy(() => import('./pages/auth/LegalNoticePage'));

// Phase 8 - Support pages
const HelpCenterDetailPage = lazy(() => import('./pages/help/HelpCenterDetailPage'));
const FAQDetailPage = lazy(() => import('./pages/faq/FAQDetailPage'));
const KnowledgeBaseArticle = lazy(() => import('./pages/help/KnowledgeBaseArticle'));
const ContactFormPage = lazy(() => import('./pages/support/ContactFormPage'));
const TicketsListPage = lazy(() => import('./pages/support/TicketsListPage'));
const TicketDetailPage = lazy(() => import('./pages/support/TicketDetailPage'));
const LiveChatDashboard = lazy(() => import('./pages/support/LiveChatDashboard'));
const CreateFilierePage = lazy(() => import('./pages/settings/CreateFilierePage'));

// Phase 9 - Profile & Notifications
const ProfilePage = lazy(() => import('./pages/profile/ProfilePage'));
const NotificationsPage = lazy(() => import('./pages/notifications/NotificationsPage'));

// Phase 10 - UE/EC & Events & Alerts
const UEManagementPage = lazy(() => import('./pages/courses/UEManagementPage'));
const EvenementManagementPage = lazy(() => import('./pages/events/EvenementManagementPage'));
const AnomaliesListPage = lazy(() => import('./pages/alerts/AnomaliesListPage'));

// Super Admin pages
const SuperAdminDashboardPage = lazy(() => import('./pages/super-admin/SuperAdminDashboardPage'));
const EtablissementManagementPage = lazy(() => import('./pages/super-admin/EtablissementManagementPage'));
const CreateEtablissementPage = lazy(() => import('./pages/super-admin/CreateEtablissementPage'));
const EtablissementDetailPage = lazy(() => import('./pages/super-admin/EtablissementDetailPage'));
const BulkImportPage = lazy(() => import('./pages/super-admin/BulkImportPage'));

function LoadingFallback() {
  return (
    <div className="flex h-screen items-center justify-center bg-surface">
      <div className="text-center">
        <div className="w-10 h-10 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
        <p className="text-on-surface-variant">Chargement...</p>
      </div>
    </div>
  );
}

function App() {
  const { user, loading } = useAuth();

  if (loading) {
    return <LoadingFallback />;
  }

  return (
    <BrowserRouter>
      <SkipLink />
      <Suspense fallback={<LoadingFallback />}>
        {!user ? (
          <Routes>
            <Route path="/" element={<LandingPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/reset-password" element={<ResetPasswordPage />} />
            <Route path="/terms" element={<TermsOfServicePage />} />
            <Route path="/privacy" element={<PrivacyPolicyPage />} />
            <Route path="/legal" element={<LegalNoticePage />} />
            <Route path="/session-expired" element={<SessionExpiredPage />} />
            <Route path="/login-error" element={<LoginErrorPage />} />
            <Route path="/attendance/validate" element={<PresenceValidationPage />} />
            <Route path="/attendance/success" element={<Navigate to="/login" />} />
            <Route path="*" element={<Navigate to="/login" replace />} />
          </Routes>
        ) : (
          <Routes>
            {/* Super Admin routes */}
            <Route path="/super-admin" element={<ProtectedRoute role="super_admin"><SuperAdminLayout /></ProtectedRoute>}>
              <Route index element={<SuperAdminDashboardPage />} />
              <Route path="etablissements" element={<EtablissementManagementPage />} />
              <Route path="etablissements/create" element={<CreateEtablissementPage />} />
              <Route path="etablissements/:id" element={<EtablissementDetailPage />} />
              <Route path="import" element={<BulkImportPage />} />
              <Route path="settings" element={<div className="text-center py-16 text-slate-400">Paramètres globaux (à venir)</div>} />
            </Route>

            {/* Faculté Admin routes */}
            <Route path="/" element={<ProtectedRoute role="faculte_admin"><MainLayout /></ProtectedRoute>}>
              <Route index element={<DashboardPage />} />
              <Route path="dashboard" element={<DashboardPage />} />
              <Route path="students" element={<StudentManagementPage />} />
              <Route path="attendance" element={<AttendanceLayout />}>
                <Route index element={<Navigate to="validate" replace />} />
                <Route path="validate" element={<PresenceValidationPage />} />
                <Route path="alerts" element={<AnomaliesListPage />} />
                <Route path="history" element={<PresenceHistoryPage />} />
                <Route path="scan" element={<QRValidationPage />} />
              </Route>
              <Route path="attendance/student-stats/:studentId" element={<StudentStatsPage />} />
              <Route path="courses" element={<UEManagementPage />} />
              <Route path="courses/ues" element={<UEManagementPage />} />
              <Route path="schedules/weekly" element={<WeeklySchedulePage />} />
              <Route path="schedules/events" element={<EvenementManagementPage />} />
              <Route path="schedules/slate" element={<AcademicSlatePage />} />
              <Route path="reports" element={<ReportsPage />} />
              <Route path="reports/print/:id" element={<ReportPrintPreview />} />
              <Route path="reports/department" element={<DepartmentFilterReport1 />} />
              <Route path="reports/department/:id" element={<DepartmentFilterReport2 />} />
              <Route path="reports/comparison/semester" element={<SemesterComparison />} />
              <Route path="reports/comparison/filiere" element={<ProgramComparison />} />
              <Route path="reports/comparison/year" element={<AcademicYearComparison />} />
              <Route path="reports/filtered" element={<FilteredReportsPage />} />
              <Route path="reports/export/excel" element={<ExcelExportPage />} />
              <Route path="reports/filiere/:id" element={<ProgramReportDetail />} />
              <Route path="settings" element={<SettingsLayout />}>
                <Route index element={<Navigate to="academic-years" replace />} />
                <Route path="academic-years" element={<AcademicYearsPage />} />
                <Route path="filieres" element={<FilieresPage />} />
                <Route path="salles" element={<SallesPage />} />
                <Route path="security" element={<SecurityPage />} />
              </Route>
              <Route path="admin/filieres/create" element={<CreateFilierePage />} />
              <Route path="import/ai-analysis" element={<AIAnalysisProgressPage />} />
              <Route path="import/validate-schedule" element={<ScheduleValidationPage />} />
              <Route path="import/validate-courses" element={<CourseValidationPage />} />
              <Route path="help" element={<HelpCenterPage />} />
              <Route path="help/category/:id" element={<HelpCenterDetailPage />} />
              <Route path="help/article/:id" element={<KnowledgeBaseArticle />} />
              <Route path="faq" element={<FAQPage />} />
              <Route path="faq/category/:id" element={<FAQDetailPage />} />
              <Route path="support/contact" element={<ContactFormPage />} />
              <Route path="support/tickets" element={<TicketsListPage />} />
              <Route path="support/tickets/:id" element={<TicketDetailPage />} />
              <Route path="support/live-chat" element={<LiveChatDashboard />} />
              <Route path="profile" element={<ProfilePage />} />
              <Route path="notifications" element={<NotificationsPage />} />
              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Routes>
        )}
      </Suspense>
    </BrowserRouter>
  );
}

export default App;
