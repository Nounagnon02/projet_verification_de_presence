import { FiChevronLeft, FiChevronRight } from 'react-icons/fi';

export default function Pagination({ pagination, onPageChange }) {
  if (!pagination || pagination.last_page <= 1) return null;

  const { current_page, last_page, from, to, total } = pagination;

  const pages = [];
  const maxVisible = 5;
  let start = Math.max(1, current_page - Math.floor(maxVisible / 2));
  let end = Math.min(last_page, start + maxVisible - 1);
  if (end - start < maxVisible - 1) {
    start = Math.max(1, end - maxVisible + 1);
  }

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }

  const btnBase = 'px-3 py-1.5 text-sm rounded-xl transition-colors min-w-[32px]';

  return (
    <div className="flex items-center justify-between pt-4">
      <p className="text-xs text-on-surface-variant">
        {from}–{to} sur {total}
      </p>
      <div className="flex items-center gap-1">
        <button
          onClick={() => onPageChange(current_page - 1)}
          disabled={current_page <= 1}
          className={`${btnBase} hover:bg-surface-container-high disabled:opacity-30 disabled:cursor-not-allowed`}
        >
          <FiChevronLeft size={16} />
        </button>
        {start > 1 && (
          <>
            <button onClick={() => onPageChange(1)} className={btnBase}>1</button>
            {start > 2 && <span className="px-1 text-on-surface-variant">...</span>}
          </>
        )}
        {pages.map(page => (
          <button
            key={page}
            onClick={() => onPageChange(page)}
            className={`${btnBase} ${
              page === current_page
                ? 'bg-primary text-white'
                : 'hover:bg-surface-container-high'
            }`}
          >
            {page}
          </button>
        ))}
        {end < last_page && (
          <>
            {end < last_page - 1 && <span className="px-1 text-on-surface-variant">...</span>}
            <button onClick={() => onPageChange(last_page)} className={btnBase}>{last_page}</button>
          </>
        )}
        <button
          onClick={() => onPageChange(current_page + 1)}
          disabled={current_page >= last_page}
          className={`${btnBase} hover:bg-surface-container-high disabled:opacity-30 disabled:cursor-not-allowed`}
        >
          <FiChevronRight size={16} />
        </button>
      </div>
    </div>
  );
}
