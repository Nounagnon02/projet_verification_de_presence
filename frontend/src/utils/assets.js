/**
 * Images de l'interface.
 *
 * La photo du rectorat etait servie depuis Supabase Storage dans sa version
 * d'origine : 4,1 Mo, 4032x3024, metadonnees EXIF de l'appareil photo (dont sa
 * position GPS) comprises. Elle s'affiche en fond de la page de connexion, de
 * l'accueil et des ecrans de mot de passe oublie : tout visiteur la
 * telechargeait avant de pouvoir se connecter.
 *
 * Elle est desormais locale, redimensionnee a 1280 px, convertie en WebP et
 * debarrassee de ses metadonnees — 191 Ko, soit vingt-deux fois moins. La
 * qualite est volontairement basse : l'image est recouverte d'un degrade a
 * 90 % d'opacite et n'est pas affichee sous « lg ».
 *
 * Servir les images depuis le domaine du site plutot que depuis Supabase evite
 * en outre d'ouvrir img-src a un domaine tiers dans la politique de securite du
 * contenu.
 */
const BASE = `${import.meta.env.BASE_URL ?? '/'}images`.replace(/\/{2,}/g, '/');

export const assets = {
  rectoratUac: `${BASE}/rectorat-uac.webp`,
  /** Dimensions intrinseques, pour reserver la place et eviter le decalage. */
  rectoratUacTaille: { width: 1280, height: 960 },
};
