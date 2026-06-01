import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FiSearch, FiChevronDown, FiChevronRight, FiArrowLeft, FiHelpCircle } from 'react-icons/fi';

const faqCategories = {
  'demarrage': {
    name: 'Démarrage',
    items: [
      { q: 'Comment accéder au tableau de bord ?', r: 'Connectez-vous avec vos identifiants administrateur. Une fois connecté, vous serez redirigé vers le tableau de bord qui affiche les statistiques clés de présence.' },
      { q: 'Comment ajouter un étudiant ?', r: 'Allez dans la section "Gestion des Étudiants", cliquez sur "Nouvel étudiant" et remplissez le formulaire avec les informations requises.' },
      { q: 'Comment créer une filière ?', r: 'Dans Paramètres > Filières, cliquez sur "Nouvelle filière" et renseignez le code et le nom complet.' },
      { q: 'Comment configurer une année académique ?', r: 'Dans Paramètres > Années Académiques, créez une nouvelle année et définissez-la comme active.' },
    ],
  },
  'validation': {
    name: 'Validation de présence',
    items: [
      { q: 'Comment un étudiant valide-t-il sa présence ?', r: "L'étudiant scanne le QR code affiché par l'enseignant avec son téléphone, ou saisit son matricule dans la page de validation." },
      { q: 'Que faire si le scan QR ne fonctionne pas ?', r: "Utilisez la validation manuelle en saisissant le matricule de l'étudiant dans la page Valider présence." },
      { q: 'Un étudiant peut-il valider sa présence à distance ?', r: 'Non, la géolocalisation peut être activée pour s\'assurer que l\'étudiant est bien présent dans la salle de cours.' },
      { q: 'Que signifie "Session expirée" ?', r: 'La session de validation a dépassé le temps imparti. L\'enseignant doit générer un nouveau QR code.' },
      { q: 'Peut-on valider la présence après la séance ?', r: 'Non, la validation doit être effectuée pendant la session active. Contactez l\'administration en cas de besoin exceptionnel.' },
    ],
  },
  'rapports': {
    name: 'Rapports et export',
    items: [
      { q: 'Comment générer un rapport ?', r: 'Dans la section "Rapports Mensuels", sélectionnez le type de rapport et les filtres souhaités, puis cliquez sur "Générer".' },
      { q: 'Quelles données sont incluses dans les rapports ?', r: 'Les rapports incluent le taux de présence, les absences, les retards et les statistiques détaillées par cours, filière et période.' },
      { q: 'Comment exporter en Excel ?', r: 'Utilisez la page "Export Excel" dans la section Rapports pour configurer les colonnes et la période avant export.' },
      { q: 'Puis-je comparer les données entre semestres ?', r: 'Oui, la section Comparaison vous permet de comparer les taux de présence par semestre, filière ou année académique.' },
    ],
  },
  'securite': {
    name: 'Sécurité et confidentialité',
    items: [
      { q: 'Mes données sont-elles sécurisées ?', r: 'Oui, toutes les données sont chiffrées en transit et au repos via TLS et AES-256. Le système est conforme au RGPD.' },
      { q: 'Qui peut accéder aux données ?', r: 'Seuls les administrateurs autorisés ont accès aux données via une authentification sécurisée avec Sanctum.' },
      { q: 'Comment modifier mon mot de passe ?', r: 'Dans Paramètres > Sécurité, utilisez le formulaire de modification de mot de passe.' },
      { q: 'L\'authentification à deux facteurs est-elle disponible ?', r: 'Oui, vous pouvez activer la 2FA dans les paramètres de sécurité du compte.' },
    ],
  },
  'tickets': {
    name: 'Tickets et support',
    items: [
      { q: 'Comment créer un ticket de support ?', r: 'Dans la section Support, cliquez sur "Nouveau ticket", remplissez le formulaire avec votre sujet et message.' },
      { q: 'Quel est le délai de réponse moyen ?', r: 'Notre équipe répond généralement sous 24 à 48 heures ouvrées pour les tickets standards.' },
      { q: 'Puis-je suivre l\'état de mon ticket ?', r: 'Oui, dans la liste des tickets, vous pouvez voir le statut (ouvert, en cours, résolu, fermé) de chaque demande.' },
      { q: 'Le chat en direct est-il disponible ?', r: 'Oui, le chat en direct est disponible du lundi au vendredi de 8h à 18h dans la section Support > Chat en direct.' },
    ],
  },
};

export default function FAQDetailPage() {
  const { id } = useParams();
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState(null);

  const category = faqCategories[id] || faqCategories['demarrage'];
  const filtered = category.items.filter((item) =>
    item.q.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="max-w-3xl">
      <div className="mb-8">
        <Link to="/faq" className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary mb-4 transition-colors">
          <FiArrowLeft size={16} /> Retour à la FAQ
        </Link>
        <h1 className="text-2xl font-bold text-primary font-headline">{category.name}</h1>
        <p className="text-sm text-on-surface-variant mt-1">{filtered.length} question{filtered.length !== 1 ? 's' : ''}</p>
      </div>

      <div className="relative max-w-lg mb-8">
        <FiSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-outline" />
        <input className="w-full pl-10 pr-4 py-3 bg-surface-container-low rounded-xl border border-outline-variant/20 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          placeholder="Rechercher dans cette catégorie..." value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      {filtered.length === 0 ? (
        <div className="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-xxl border border-outline-variant/10">
          <FiHelpCircle className="text-3xl mx-auto mb-3 opacity-40" />
          <p className="text-sm">Aucune question trouvée pour "{search}"</p>
        </div>
      ) : (
        <div className="space-y-2">
          {filtered.map((item, i) => (
            <div key={i} className="bg-surface-container-lowest rounded-xl border border-outline-variant/10 overflow-hidden shadow-sm">
              <button
                onClick={() => setOpen(open === i ? null : i)}
                className="w-full flex items-center justify-between p-4 text-left hover:bg-surface-container-low transition-colors"
              >
                <span className="text-sm font-medium text-on-surface pr-4">{item.q}</span>
                {open === i ? <FiChevronDown className="text-outline shrink-0" /> : <FiChevronRight className="text-outline shrink-0" />}
              </button>
              {open === i && (
                <div className="px-4 pb-4 text-sm text-on-surface-variant border-t border-outline-variant/10 pt-3 leading-relaxed">
                  {item.r}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      <div className="mt-8 bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10 text-center">
        <FiHelpCircle className="text-3xl text-primary mx-auto mb-3" />
        <h3 className="font-bold text-primary text-sm">Vous ne trouvez pas votre réponse ?</h3>
        <p className="text-xs text-on-surface-variant mt-1">Notre équipe est disponible pour vous aider</p>
        <Link to="/support/contact" className="inline-block mt-4 px-6 py-2.5 bg-primary text-white rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
          Contacter le support
        </Link>
      </div>
    </div>
  );
}
