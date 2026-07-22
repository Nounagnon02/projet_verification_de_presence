export function required(value) {
  if (!value || (typeof value === 'string' && !value.trim())) {
    return 'Ce champ est requis';
  }
  return null;
}

export function email(value) {
  if (!value) return null;
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(value) ? null : 'Email invalide';
}

export function matricule(value) {
  if (!value) return null;
  const re = /^\d{8,12}$/;
  return re.test(value) ? null : 'Matricule invalide (8 à 12 chiffres)';
}

export function minLength(min) {
  return (value) => {
    if (!value) return null;
    return value.length >= min ? null : `Minimum ${min} caractères`;
  };
}
