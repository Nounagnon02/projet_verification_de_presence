import { FiSearch } from 'react-icons/fi';

export default function SearchInput({ value, onChange, placeholder = 'Rechercher...', className = '' }) {
  return (
    <div className={`relative ${className}`}>
      <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant" size={16} />
      <input
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full pl-9 pr-4 py-2 bg-surface-container-high rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/50 border-b-2 border-transparent focus:border-primary focus:outline-none transition-colors"
      />
    </div>
  );
}
