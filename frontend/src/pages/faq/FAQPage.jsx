import { useState } from 'react';
import { FiSearch, FiChevronDown, FiChevronRight, FiHelpCircle } from 'react-icons/fi';

const FAQPage = () => {
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState(null);

  const categories = [
    {
      name: 'Démarrage',
      items: [
        {
          q: 'Comment accéder au tableau de bord ?',
          r: 'Connectez-vous avec vos identifiants administrateur. Une fois connecté, vous serez redirigé vers le tableau de bord.',
        },
        {
          q: 'Comment ajouter un étudiant ?',
          r: 'Allez dans la section "Gestion des Étudiants", cliquez sur "Nouvel étudiant" et remplissez le formulaire.',
        },
      ],
    },
    {
      name: 'Validation de présence',
      items: [
        {
          q: 'Comment un étudiant valide-t-il sa présence ?',
          r: "L'étudiant scanne, avec l'application mobile, le QR code affiché par l'enseignant ou le délégué de promotion. Selon la salle, le serveur vérifie aussi la position GPS et le réseau Wi-Fi.",
        },
        {
          q: 'Que faire si le scan QR ne fonctionne pas ?',
          r: 'Utilisez la page Saisie manuelle (section Présences) pour enregistrer sa présence en saisissant son matricule.',
        },
        {
          q: 'Un étudiant peut-il valider sa présence à distance ?',
          r: 'La géolocalisation et le réseau Wi-Fi de la salle peuvent être vérifiés par le serveur, selon la configuration de la salle, pour limiter ce risque.',
        },
      ],
    },
    {
      name: 'Rapports et export',
      items: [
        {
          q: 'Comment générer un rapport ?',
          r: 'Dans la section Rapports, choisissez un rapport dans le menu "Exporter" : le fichier (CSV ou PDF selon le rapport) se télécharge directement.',
        },
        {
          q: 'Quelles données sont incluses dans les rapports ?',
          r: 'Selon le rapport choisi : liste des présences, étudiants les plus absents, détail par UE ou rapport de filière.',
        },
      ],
    },
    {
      name: 'Sécurité et confidentialité',
      items: [
        {
          q: 'Mes données sont-elles sécurisées ?',
          r: "Les mots de passe sont hachés, les connexions passent en HTTPS en production, et chaque établissement est cloisonné : un administrateur n'accède qu'aux données de sa propre faculté.",
        },
        {
          q: 'Qui peut accéder aux données ?',
          r: "Seuls les comptes authentifiés ont accès à l'application, chacun selon son rôle (super administrateur, administrateur de faculté, étudiant).",
        },
      ],
    },
  ];

  const filtered = categories
    .map((cat) => ({
      ...cat,
      items: cat.items.filter((item) => item.q.toLowerCase().includes(search.toLowerCase())),
    }))
    .filter((cat) => cat.items.length > 0);

  return (
    <div className="max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-primary font-headline">Foire Aux Questions (FAQ)</h1>
        <p className="text-sm text-on-surface-variant">Trouvez rapidement une réponse à vos questions</p>
      </div>

      <div className="relative max-w-lg mb-8">
        <FiSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant" />
        <input
          className="w-full pl-10 pr-4 py-3 bg-surface-container-low rounded-xl border border-outline-variant/20 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          placeholder="Rechercher dans la FAQ..."
          aria-label="Rechercher dans la FAQ"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      <div className="space-y-6">
        {filtered.length === 0 ? (
          <div className="text-center py-12 text-on-surface-variant">
            <FiHelpCircle className="text-3xl mx-auto mb-3 opacity-40" />
            <p>Aucune question trouvée pour "{search}"</p>
          </div>
        ) : (
          filtered.map((cat, ci) => (
            <div
              key={ci}
              className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10"
            >
              <h2 className="text-sm font-bold text-primary mb-4">{cat.name}</h2>
              <div className="space-y-2">
                {cat.items.map((item, ii) => (
                  <div key={ii} className="border border-outline-variant/10 rounded-xl overflow-hidden">
                    <button
                      onClick={() => setOpen(open === `${ci}-${ii}` ? null : `${ci}-${ii}`)}
                      className="w-full flex items-center justify-between p-4 text-left hover:bg-surface-container-low transition-colors"
                    >
                      <span className="text-sm font-medium text-on-surface">{item.q}</span>
                      {open === `${ci}-${ii}` ? (
                        <FiChevronDown className="text-on-surface-variant shrink-0" />
                      ) : (
                        <FiChevronRight className="text-on-surface-variant shrink-0" />
                      )}
                    </button>
                    {open === `${ci}-${ii}` && (
                      <div className="px-4 pb-4 text-sm text-on-surface-variant border-t border-outline-variant/10 pt-3">
                        {item.r}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default FAQPage;
