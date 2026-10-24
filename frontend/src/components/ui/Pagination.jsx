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

  // Cibles de 36 px (WCAG 2.5.8). Les flèches n'avaient aucun nom accessible :
  // un lecteur d'écran annonçait deux « bouton » muets.
  const btnBase =
    'inline-flex h-9 min-w-[36px] items-center justify-center px-2.5 text-sm rounded-xl transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30';
  const btnPage = (page) =>
    `${btnBase} ${
      page === current_page
        ? 'bg-primary text-on-primary font-semibold'
        : 'text-on-surface hover:bg-surface-container-high'
    }`;

  return (
    <nav aria-label="Pagination" className="flex flex-wrap items-center justify-between gap-3 pt-4">
      <p className="text-xs text-on-surface-variant">
        {from}–{to} sur {total}
      </p>
      <div className="flex items-center gap-1">
        <button
          type="button"
          onClick={() => onPageChange(current_page - 1)}
          disabled={current_page <= 1}
          aria-label="Page précédente"
          className={`${btnBase} text-on-surface hover:bg-surface-container-high disabled:opacity-30 disabled:cursor-not-allowed`}
        >
          <FiChevronLeft size={16} aria-hidden="true" />
        </button>
        {start > 1 && (
          <>
            <button type="button" onClick={() => onPageChange(1)} className={btnPage(1)}>
              1
            </button>
            {start > 2 && (
              <span className="px-1 text-on-surface-variant" aria-hidden="true">
                …
              </span>
            )}
          </>
        )}
        {pages.map((page) => (
          <button
            type="button"
            key={page}
            onClick={() => onPageChange(page)}
            aria-current={page === current_page ? 'page' : undefined}
            className={btnPage(page)}
          >
            {page}
          </button>
        ))}
        {end < last_page && (
          <>
            {end < last_page - 1 && (
              <span className="px-1 text-on-surface-variant" aria-hidden="true">
                …
              </span>
            )}
            <button type="button" onClick={() => onPageChange(last_page)} className={btnPage(last_page)}>
              {last_page}
            </button>
          </>
        )}
        <button
          type="button"
          onClick={() => onPageChange(current_page + 1)}
          disabled={current_page >= last_page}
          aria-label="Page suivante"
          className={`${btnBase} text-on-surface hover:bg-surface-container-high disabled:opacity-30 disabled:cursor-not-allowed`}
        >
          <FiChevronRight size={16} aria-hidden="true" />
        </button>
      </div>
    </nav>
  );
}
