import { lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import MainLayout from './components/layout/MainLayout';
import { useAuth } from './context/AuthContext';

// Lazy-loaded pages
const LoginPage = lazy(() => import('./pages/auth/LoginPage'));
const DashboardPage = lazy(() => import('./pages/dashboard/DashboardPage'));
const StudentManagementPage = lazy(() => import('./pages/students/StudentManagementPage'));
const PresenceValidationPage = lazy(() => import('./pages/attendance/PresenceValidationPage'));
const AttendanceStatsPage = lazy(() => import('./pages/attendance/AttendanceStatsPage'));
const PresenceHistoryPage = lazy(() => import('./pages/attendance/PresenceHistoryPage'));
const ReportsPage = lazy(() => import('./pages/reports/ReportsPage'));
const SettingsPage = lazy(() => import('./pages/settings/SettingsPage'));
const HelpCenterPage = lazy(() => import('./pages/help/HelpCenterPage'));
const ImportPage = lazy(() => import('./pages/import/ImportPage'));
const FAQPage = lazy(() => import('./pages/faq/FAQPage'));
const NotFoundPage = lazy(() => import('./pages/NotFoundPage'));

// Phase 3 - New pages
const QRValidationPage = lazy(() => import('./pages/attendance/QRValidationPage'));
const StudentStatsPage = lazy(() => import('./pages/attendance/StudentStatsPage'));
const CourseListPage = lazy(() => import('./pages/courses/CourseListPage'));
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

// Phase 7 - Settings pages
const AcademicYearsPage = lazy(() => import('./pages/settings/AcademicYearsPage'));
const FilieresPage = lazy(() => import('./pages/settings/FilieresPage'));
const SecurityPage = lazy(() => import('./pages/settings/SecurityPage'));

// Landing page
const LandingPage = lazy(() => import('./pages/LandingPage'));

// Phase 8 - Support pages
const HelpCenterDetailPage = lazy(() => import('./pages/help/HelpCenterDetailPage'));
const FAQDetailPage = lazy(() => import('./pages/faq/FAQDetailPage'));
const KnowledgeBaseArticle = lazy(() => import('./pages/help/KnowledgeBaseArticle'));
const ContactFormPage = lazy(() => import('./pages/support/ContactFormPage'));
const TicketsListPage = lazy(() => import('./pages/support/TicketsListPage'));
const TicketDetailPage = lazy(() => import('./pages/support/TicketDetailPage'));
const LiveChatDashboard = lazy(() => import('./pages/support/LiveChatDashboard'));
const CreateFilierePage = lazy(() => import('./pages/settings/CreateFilierePage'));

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
      <Suspense fallback={<LoadingFallback />}>
        {!user ? (
          <Routes>
            <Route path="/" element={<LandingPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/session-expired" element={<SessionExpiredPage />} />
            <Route path="/login-error" element={<LoginErrorPage />} />
            <Route path="/attendance/validate" element={<PresenceValidationPage />} />
            <Route path="/attendance/success" element={<Navigate to="/login" />} />
            <Route path="*" element={<Navigate to="/login" replace />} />
          </Routes>
        ) : (
          <Routes>
            <Route path="/" element={<MainLayout />}>
              <Route index element={<DashboardPage />} />
              <Route path="dashboard" element={<DashboardPage />} />
              <Route path="students" element={<StudentManagementPage />} />
              <Route path="attendance/validate" element={<PresenceValidationPage />} />
              <Route path="attendance/stats" element={<AttendanceStatsPage />} />
              <Route path="attendance/history" element={<PresenceHistoryPage />} />
              <Route path="attendance/scan" element={<QRValidationPage />} />
              <Route path="attendance/student-stats/:studentId" element={<StudentStatsPage />} />
              <Route path="courses" element={<CourseListPage />} />
              <Route path="schedules/weekly" element={<WeeklySchedulePage />} />
              <Route path="schedules/slate" element={<AcademicSlatePage />} />
              <Route path="reports" element={<ReportsPage />} />
              <Route path="reports/print/:id" element={<ReportPrintPreview />} />
              <Route path="reports/department" element={<DepartmentFilterReport1 />} />
              <Route path="reports/department/:id" element={<DepartmentFilterReport2 />} />
              <Route path="reports/comparison/semester" element={<SemesterComparison />} />
              <Route path="reports/comparison/filiere" element={<ProgramComparison />} />
              <Route path="reports/comparison/year" element={<AcademicYearComparison />} />
              <Route path="reports/export/excel" element={<ExcelExportPage />} />
              <Route path="reports/filiere/:id" element={<ProgramReportDetail />} />
              <Route path="settings" element={<SettingsPage />} />
              <Route path="settings/academic-years" element={<AcademicYearsPage />} />
              <Route path="settings/filieres" element={<FilieresPage />} />
              <Route path="settings/security" element={<SecurityPage />} />
              <Route path="admin/filieres/create" element={<CreateFilierePage />} />
              <Route path="import" element={<ImportPage />} />
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
              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Routes>
        )}
      </Suspense>
    </BrowserRouter>
  );
}

export default App;
