// Logo de l'application. En thème sombre (le thème suit le système), le logo
// couleur posait « UAC » en bleu nuit sur un fond presque noir : illisible.
// La variante blanche, déjà livrée pour les fonds sombres, prend alors le relais.
export default function Logo({ className = 'h-7 w-auto', alt = 'UAC Présences' }) {
  return (
    <picture className="contents">
      <source srcSet="/images/logo-blanc.png" media="(prefers-color-scheme: dark)" />
      <img src="/images/logo-couleur-compact.png" alt={alt} className={className} />
    </picture>
  );
}
