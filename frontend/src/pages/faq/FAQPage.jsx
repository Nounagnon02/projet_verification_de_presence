import { useState } from 'react';
import { FiSearch, FiChevronDown, FiChevronRight, FiHelpCircle } from 'react-icons/fi';

const FAQPage = () => {
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState(null);

  const categories = [
    {
      name: 'Démarrage',
      items: [
        { q: 'Comment accéder au tableau de bord ?', r: 'Connectez-vous avec vos identifiants administrateur. Une fois connecté, vous serez redirigé vers le tableau de bord.' },
        { q: 'Comment ajouter un étudiant ?', r: 'Allez dans la section "Gestion des Étudiants", cliquez sur "Nouvel étudiant" et remplissez le formulaire.' },
      ],
    },
    {
      name: 'Validation de présence',
      items: [
        { q: 'Comment un étudiant valide-t-il sa présence ?', r: 'L\'étudiant scanne le QR code affiché par l\'enseignant avec son téléphone, ou saisit son matricule dans la page de validation.' },
        { q: 'Que faire si le scan QR ne fonctionne pas ?', r: 'Utilisez la validation manuelle en saisissant le matricule de l\'étudiant dans la page "Valider présence".' },
        { q: 'Un étudiant peut-il valider sa présence à distance ?', r: 'Non, la géolocalisation peut être activée pour s\'assurer que l\'étudiant est bien dans la salle de cours.' },
      ],
    },
    {
      name: 'Rapports et export',
      items: [
        { q: 'Comment générer un rapport ?', r: 'Dans la section "Rapports Mensuels", sélectionnez le type de rapport et cliquez sur "PDF" ou "CSV".' },
        { q: 'Quelles données sont incluses dans les rapports ?', r: 'Les rapports incluent le taux de présence, les absences, les retards et les statistiques par cours.' },
      ],
    },
    {
      name: 'Sécurité et confidentialité',
      items: [
        { q: 'Mes données sont-elles sécurisées ?', r: 'Oui, toutes les données sont chiffrées en transit et au repos. Le système est conforme au RGPD.' },
        { q: 'Qui peut accéder aux données ?', r: 'Seuls les administrateurs autorisés ont accès aux données via une authentification sécurisée.' },
      ],
    },
  ];

  const filtered = categories.map((cat) => ({
    ...cat,
    items: cat.items.filter((item) => item.q.toLowerCase().includes(search.toLowerCase())),
  })).filter((cat) => cat.items.length > 0);

  return (
    <div className="max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-primary font-headline">Foire Aux Questions (FAQ)</h1>
        <p className="text-sm text-on-surface-variant">Trouvez rapidement une réponse à vos questions</p>
      </div>

      <div className="relative max-w-lg mb-8">
        <FiSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-outline" />
        <input className="w-full pl-10 pr-4 py-3 bg-surface-container-low rounded-xl border border-outline-variant/20 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          placeholder="Rechercher dans la FAQ..." value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      <div className="space-y-6">
        {filtered.length === 0 ? (
          <div className="text-center py-12 text-on-surface-variant">
            <FiHelpCircle className="text-3xl mx-auto mb-3 opacity-40" />
            <p>Aucune question trouvée pour "{search}"</p>
          </div>
        ) : filtered.map((cat, ci) => (
          <div key={ci} className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
            <h2 className="text-sm font-bold text-primary mb-4">{cat.name}</h2>
            <div className="space-y-2">
              {cat.items.map((item, ii) => (
                <div key={ii} className="border border-outline-variant/10 rounded-xl overflow-hidden">
                  <button onClick={() => setOpen(open === `${ci}-${ii}` ? null : `${ci}-${ii}`)}
                    className="w-full flex items-center justify-between p-4 text-left hover:bg-surface-container-low transition-colors">
                    <span className="text-sm font-medium text-on-surface">{item.q}</span>
                    {open === `${ci}-${ii}` ? <FiChevronDown className="text-outline shrink-0" /> : <FiChevronRight className="text-outline shrink-0" />}
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
        ))}
      </div>
    </div>
  );
};

export default FAQPage;
