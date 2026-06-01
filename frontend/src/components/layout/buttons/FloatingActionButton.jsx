import { Link } from 'react-router-dom';
import { FiCamera } from 'react-icons/fi';

const FloatingActionButton = () => {
  return (
    <Link to="/attendance/validate"
      className="fixed bottom-20 right-6 md:bottom-8 md:right-8 z-50 w-14 h-14 bg-primary text-on-primary rounded-2xl shadow-lg shadow-primary/30 flex items-center justify-center hover:scale-105 active:scale-95 transition-all">
      <FiCamera className="text-2xl" />
    </Link>
  );
};

export default FloatingActionButton;
