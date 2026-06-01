export function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
  });
}

export function formatDateTime(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

export function formatTime(date) {
  if (!date) return '';
  return new Date(date).toLocaleTimeString('fr-FR', {
    hour: '2-digit', minute: '2-digit',
  });
}

export function formatPercentage(value) {
  if (value == null) return '—';
  return `${Math.round(value)}%`;
}

export function formatNumber(value) {
  if (value == null) return '—';
  return value.toLocaleString('fr-FR');
}
