import { useId } from 'react';
import { FiSearch } from 'react-icons/fi';

export default function SearchInput({ value, onChange, placeholder = 'Rechercher...', className = '', 'aria-label': ariaLabel, id }) {
  // useId au lieu de Math.random() : appelé pendant le rendu, ce dernier
  // produisait un identifiant différent à chaque passage, ce qui cassait
  // l'association entre le label et le champ d'une mise à jour à l'autre.
  const idGenere = useId();
  const inputId = id || `search-${idGenere}`;
  return (
    <div className={`relative ${className}`}>
      <FiSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant" size={16} aria-hidden="true" />
      <input
        type="search"
        id={inputId}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full pl-9 pr-4 py-2 bg-surface-container-high rounded-xl text-sm text-on-surface placeholder:text-on-surface-variant/50 border-b-2 border-transparent focus:border-primary focus:outline-none transition-colors"
        aria-label={ariaLabel || placeholder}
        autoComplete="off"
      />
    </div>
  );
}
