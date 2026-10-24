import { Outlet } from 'react-router-dom';
import SuperAdminSidebar, { SuperAdminSidebarMobile } from './navigation/SuperAdminSidebar';

const SuperAdminLayout = () => {
  return (
    <>
      <SuperAdminSidebar />
      <SuperAdminSidebarMobile />
      <main
        id="main-content"
        className="md:ml-64 pt-6 pb-10 md:pb-8 px-4 sm:px-8 min-h-screen min-w-0 flex-1"
        tabIndex={-1}
      >
        <Outlet />
      </main>
    </>
  );
};

export default SuperAdminLayout;
