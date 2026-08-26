import { Outlet } from 'react-router-dom';
import SideNavBar from './navigation/SideNavBar';
import BottomNavBar from './navigation/BottomNavBar';
const MainLayout = () => {
  return (
    <>
      <SideNavBar />
      {/* pt-6, comme SuperAdminLayout. La valeur etait pt-24, soit 96 px, censes
          degager une barre superieure fixe — or TopNavBar n'est monte nulle part.
          Ces 96 px ne compensaient donc rien et laissaient un vide en haut de
          chaque ecran d'administration, alors que la section super admin, elle,
          etait deja serree. */}
      <main id="main-content" className="md:ml-64 pt-6 pb-20 md:pb-8 px-4 sm:px-8 min-h-screen flex-1" tabIndex={-1}>
        <Outlet />
      </main>
      <BottomNavBar />
    </>
  );
};

export default MainLayout;
