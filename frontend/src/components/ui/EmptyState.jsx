import { FiInbox } from 'react-icons/fi';

export default function EmptyState({ icon, message = 'Aucune donnée', action }) {
  const Icon = icon || FiInbox;
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <div className="p-4 bg-surface-container-high rounded-2xl mb-4">
        <Icon size={32} className="text-on-surface-variant/50" />
      </div>
      <p className="text-sm text-on-surface-variant mb-4">{message}</p>
      {action && action}
    </div>
  );
}
