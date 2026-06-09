import { Outlet } from 'react-router-dom';
import SuperAdminSidebar from './navigation/SuperAdminSidebar';

const SuperAdminLayout = () => {
  return (
    <>
      <SuperAdminSidebar />
      <main className="md:ml-64 pt-6 pb-20 md:pb-8 px-4 sm:px-8 min-h-screen flex-1">
        <Outlet />
      </main>
    </>
  );
};

export default SuperAdminLayout;
