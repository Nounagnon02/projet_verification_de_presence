import { FiArrowUp, FiArrowDown, FiChevronUp, FiChevronDown } from 'react-icons/fi';
import LoadingSkeleton from './LoadingSkeleton';
import EmptyState from './EmptyState';
import Pagination from './Pagination';
import cn from '../../utils/cn';

export default function DataTable({
  columns,
  data,
  loading = false,
  sortField,
  sortDirection,
  onSort,
  onRowClick,
  pagination,
  onPageChange,
  emptyMessage = 'Aucune donnée',
  emptyAction,
  className = '',
  'aria-label': ariaLabel,
  caption,
}) {
  const renderSortIcon = (field) => {
    if (sortField !== field) return <FiChevronUp size={14} className="text-on-surface-variant/40" aria-hidden="true" />;
    return sortDirection === 'asc' ? <FiArrowUp size={14} aria-hidden="true" /> : <FiArrowDown size={14} aria-hidden="true" />;
  };

  if (loading) {
    return (
      <div className={className} role="status" aria-live="polite" aria-label="Chargement des données">
        <LoadingSkeleton rows={5} cols={columns.length} />
      </div>
    );
  }

  if (!data || data.length === 0) {
    return <EmptyState message={emptyMessage} action={emptyAction} />;
  }

  return (
    <div className={className}>
      <div className="overflow-x-auto">
        <table className="w-full" role="grid" aria-label={ariaLabel}>
          {caption && <caption className="sr-only">{caption}</caption>}
          <thead>
            <tr className="border-b border-outline-variant/10" role="row">
              {columns.map(col => (
                <th
                  key={col.key}
                  className={cn(
                    'px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant',
                    col.sortable && 'cursor-pointer hover:text-on-surface select-none',
                    col.className,
                  )}
                  onClick={() => col.sortable && onSort?.(col.key)}
                  style={col.width ? { width: col.width } : undefined}
                  scope="col"
                  aria-sort={sortField === col.key ? (sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'}
                >
                  <span className="flex items-center gap-1">
                    {col.label}
                    {col.sortable && <span aria-hidden="true">{renderSortIcon(col.key)}</span>}
                  </span>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.map((row, i) => (
              <tr
                key={row.id || i}
                className={cn(
                  'border-b border-outline-variant/5 transition-colors',
                  onRowClick ? 'cursor-pointer hover:bg-surface-container-low' : '',
                )}
                onClick={() => onRowClick?.(row)}
                onKeyDown={(e) => { if ((e.key === 'Enter' || e.key === ' ') && onRowClick) { e.preventDefault(); onRowClick(row); }}}
                tabIndex={onRowClick ? 0 : undefined}
                role="row"
                aria-selected={false}
              >
                {columns.map(col => (
                  <td key={col.key} className="px-4 py-3 text-sm text-on-surface" role="gridcell">
                    {col.render ? col.render(row[col.key], row) : row[col.key]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {pagination && onPageChange && (
        <Pagination pagination={pagination} onPageChange={onPageChange} />
      )}
    </div>
  );
}
