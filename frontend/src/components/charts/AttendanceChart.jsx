import { FiLoader } from 'react-icons/fi';

const AttendanceChart = ({ data }) => {
  if (!data || data.length === 0) {
    return (
      <div className="h-64 flex items-center justify-center">
        <FiLoader className="h-5 w-5 animate-spin text-primary/50 mr-2" />
        <span className="text-surface-variant">Chargement des données...</span>
      </div>
    );
  }

  return (
    <div className="h-64 flex items-end justify-between gap-1 mt-4">
      {data.map((item, index) => (
        <div
          key={index}
          className="w-full bg-primary/10 rounded-t-lg transition-all hover:bg-primary/30"
          style={{ height: `${item.value}%` }}
        />
      ))}
    </div>
  );
};

export default AttendanceChart;