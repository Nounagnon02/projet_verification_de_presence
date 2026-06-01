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
}) {
  const renderSortIcon = (field) => {
    if (sortField !== field) return <FiChevronUp size={14} className="text-on-surface-variant/40" />;
    return sortDirection === 'asc' ? <FiArrowUp size={14} /> : <FiArrowDown size={14} />;
  };

  if (loading) {
    return (
      <div className={className}>
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
        <table className="w-full">
          <thead>
            <tr className="border-b border-outline-variant/10">
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
                >
                  <span className="flex items-center gap-1">
                    {col.label}
                    {col.sortable && renderSortIcon(col.key)}
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
              >
                {columns.map(col => (
                  <td key={col.key} className="px-4 py-3 text-sm text-on-surface">
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
