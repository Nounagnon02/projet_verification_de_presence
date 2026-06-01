export default function LoadingSkeleton({ rows = 3, cols = 4, type = 'table' }) {
  if (type === 'card') {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {[...Array(cols)].map((_, i) => (
          <div key={i} className="bg-surface-container-lowest p-6 rounded-xxl animate-pulse">
            <div className="w-10 h-10 bg-surface-container-high rounded-xl mb-4" />
            <div className="h-3 bg-surface-container-high rounded w-20 mb-2" />
            <div className="h-6 bg-surface-container-high rounded w-16" />
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="animate-pulse">
      {[...Array(rows)].map((_, i) => (
        <div key={i} className="flex gap-4 py-3 border-b border-outline-variant/5">
          {[...Array(cols)].map((_, j) => (
            <div key={j} className="h-4 bg-surface-container-high rounded flex-1" />
          ))}
        </div>
      ))}
    </div>
  );
}
