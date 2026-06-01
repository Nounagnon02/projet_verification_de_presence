import { FiAlertTriangle } from 'react-icons/fi';

const AlertsBanner = ({ alerts }) => {
  return (
    <div className="mb-8 bg-error-container/40 rounded-xl p-4 flex items-center justify-between border-l-4 border-error">
      <div className="flex items-center gap-4">
        <div className="bg-error text-white p-2 rounded-lg">
          <FiAlertTriangle />
        </div>
        <div>
          <h3 className="text-sm font-bold text-on-error-container">{alerts[0]?.title}</h3>
          <p className="text-xs text-on-error-container/80">{alerts[0]?.message}</p>
        </div>
      </div>
      <button className="text-xs font-bold text-error hover:underline px-4 py-2">
        Voir la liste
      </button>
    </div>
  );
};

export default AlertsBanner;