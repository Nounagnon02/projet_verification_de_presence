import { Outlet } from 'react-router-dom';
import SideNavBar from './navigation/SideNavBar';
import TopNavBar from './navigation/TopNavBar';
import BottomNavBar from './navigation/BottomNavBar';
import FloatingActionButton from './buttons/FloatingActionButton';
import ChatWidget from '../chat/ChatWidget';

const MainLayout = () => {
  return (
    <>
      <SideNavBar />
      <main id="main-content" className="md:ml-64 pt-24 pb-20 md:pb-8 px-4 sm:px-8 min-h-screen flex-1" tabIndex={-1}>
        <Outlet />
      </main>
      <BottomNavBar />
      <FloatingActionButton />
      <ChatWidget />
    </>
  );
};

export default MainLayout;
