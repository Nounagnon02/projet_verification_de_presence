import { useState } from 'react';
import { FiDownload, FiFileText } from 'react-icons/fi';
import { useToastCtx } from '../../context/ToastContext';
import Button from '../ui/Button';
import api from '../../api/axios';

/**
 * Modèles réellement servis par GET /admin/import/csv/template/{type}.
 * CsvImportController::downloadTemplate n'accepte que « ue-ec » et « edt » ;
 * toute autre valeur est refusée côté serveur, d'où la liste figée ici.
 */
const MODELES = [
  {
    type: 'ue-ec',
    libelle: 'Modèle Cours (UE/EC)',
    colonnes: 'code_ue, intitule_ue, filiere_code, niveau, annee_libelle, semestre, volume_horaire_ue, code_ec, intitule_ec, volume_horaire_ec',
  },
  {
    type: 'edt',
    libelle: 'Modèle Emploi du temps',
    colonnes: 'filiere_code, niveau, annee_libelle, semestre, ue_code, ec_code, jour, heure_debut, heure_fin, salle_code, type_cours',
  },
];

/**
 * Nom du fichier à enregistrer : celui annoncé par l'en-tête
 * Content-Disposition quand il est lisible, sinon un nom par défaut.
 */
const nomDeFichier = (contentDisposition, type) => {
  const parDefaut = `modele_${type}.csv`;
  if (!contentDisposition) return parDefaut;
  const trouve = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(contentDisposition);
  return trouve?.[1]?.trim() || parDefaut;
};

/**
 * Boutons de téléchargement des modèles CSV vierges (mémoire §3.2.4).
 *
 * Le téléchargement passe par le client axios authentifié : l'endpoint est
 * derrière le middleware d'authentification, le jeton Bearer est donc requis
 * et ne doit pas transiter par l'URL.
 *
 * @param {string[]} [types]  Restreint l'affichage à certains types de modèle.
 * @param {string}   [className]
 */
const CsvTemplateDownload = ({ types, className = '' }) => {
  // Type en cours de téléchargement : pilote l'état de chargement du bouton.
  const [enCours, setEnCours] = useState(null);
  const { addToast } = useToastCtx();

  const modeles = types?.length
    ? MODELES.filter((modele) => types.includes(modele.type))
    : MODELES;

  const telecharger = async (modele) => {
    setEnCours(modele.type);
    try {
      const { data, headers } = await api.get(
        `/admin/import/csv/template/${modele.type}`,
        { responseType: 'blob' }
      );

      // Créer un lien de téléchargement
      const url = window.URL.createObjectURL(new Blob([data], { type: 'text/csv;charset=utf-8' }));
      const lien = document.createElement('a');
      lien.href = url;
      lien.download = nomDeFichier(headers?.['content-disposition'], modele.type);
      document.body.appendChild(lien);
      lien.click();
      lien.remove();
      window.URL.revokeObjectURL(url);
      addToast?.(`Modèle « ${modele.libelle} » téléchargé`, 'success');
    } catch {
      addToast?.(
        `Téléchargement impossible : le modèle « ${modele.libelle} » n'a pas pu être récupéré.`,
        'error'
      );
    } finally {
      setEnCours(null);
    }
  };

  if (modeles.length === 0) return null;

  return (
    <div className={`space-y-3 ${className}`}>
      <div className="flex items-center gap-2 text-[10px] font-semibold text-on-surface-variant uppercase tracking-wider">
        <FiFileText size={14} />
        Modèles CSV
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        {modeles.map((modele) => (
          <div
            key={modele.type}
            className="bg-surface-container-lowest rounded-xl p-4 shadow-sm border border-outline-variant/10 flex flex-col gap-3"
          >
            <div className="space-y-1">
              <p className="text-sm font-semibold text-on-surface">{modele.libelle}</p>
              <p className="text-[10px] font-mono text-on-surface-variant break-words">
                {modele.colonnes}
              </p>
            </div>

            <Button
              variant="outline"
              size="sm"
              className="mt-auto text-primary"
              loading={enCours === modele.type}
              disabled={enCours !== null}
              onClick={() => telecharger(modele)}
              aria-label={`Télécharger le ${modele.libelle}`}
            >
              {enCours !== modele.type && <FiDownload size={14} />}
              {enCours === modele.type ? 'Téléchargement...' : 'Télécharger le modèle'}
            </Button>
          </div>
        ))}
      </div>
    </div>
  );
};

export default CsvTemplateDownload;
