import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiSearch,
  FiHelpCircle,
  FiMail,
  FiMessageCircle,
  FiBookOpen,
  FiChevronRight,
  FiChevronDown,
} from 'react-icons/fi';
import { usePrefixAide } from '../../hooks/usePrefixAide';

const HelpCenterPage = () => {
  const prefix = usePrefixAide();
  const [search, setSearch] = useState('');
  const [openFaq, setOpenFaq] = useState(null);

  const categories = [
    {
      icon: <FiBookOpen />,
      title: "Guide d'utilisation",
      desc: 'Documentation du système',
      articles: 5,
      to: `${prefix}/help/category/guide`,
    },
    { icon: <FiMessageCircle />, title: 'FAQ', desc: 'Questions fréquemment posées', articles: 9, to: `${prefix}/faq` },
    {
      icon: <FiMail />,
      title: 'Support technique',
      desc: "Contacter l'équipe technique",
      articles: 3,
      to: `${prefix}/help/category/support`,
    },
  ];

  const faqs = [
    {
      q: 'Comment générer un QR code pour un cours ?',
      r: 'Depuis la liste des séances, ouvrez la séance concernée et cliquez sur "Générer QR Code". Le code sera affiché et pourra être projeté aux étudiants.',
    },
    {
      q: 'Que faire si un étudiant ne peut pas scanner ?',
      r: "Utilisez la page Saisie manuelle (section Présences) en saisissant le matricule de l'étudiant.",
    },
    {
      q: 'Comment exporter les rapports ?',
      r: 'Dans la section Rapports, choisissez un rapport dans le menu "Exporter" : le fichier (CSV ou PDF selon le rapport) se télécharge directement.',
    },
    {
      q: 'Les données sont-elles sécurisées ?',
      r: "Les mots de passe sont hachés, les connexions passent en HTTPS en production, et chaque établissement est cloisonné : un administrateur n'accède qu'aux données de sa propre faculté.",
    },
  ];

  const filtered = faqs.filter((f) => f.q.toLowerCase().includes(search.toLowerCase()));

  return (
    <div className="max-w-4xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-primary font-headline">Centre d'Aide</h1>
        <p className="text-sm text-on-surface-variant">Documentation, FAQ et support technique</p>
      </div>

      {/* Search */}
      <div className="relative max-w-lg mb-8">
        <FiSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant" />
        <input
          className="w-full pl-10 pr-4 py-3 bg-surface-container-low rounded-xl border border-outline-variant/20 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          placeholder="Rechercher dans l'aide..."
          aria-label="Rechercher dans l'aide"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      {/* Categories */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
        {categories.map((cat, i) => (
          <Link
            key={i}
            to={cat.to}
            className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm border border-outline-variant/10 hover:shadow-md hover:border-primary/20 transition-all"
          >
            <div className="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-3">
              {cat.icon}
            </div>
            <h3 className="font-bold text-sm text-primary">{cat.title}</h3>
            <p className="text-xs text-on-surface-variant mt-1">{cat.desc}</p>
            <span className="text-[10px] text-primary font-semibold mt-2 block">{cat.articles} articles</span>
          </Link>
        ))}
      </div>

      {/* FAQ */}
      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
        <h2 className="text-sm font-bold text-primary mb-6">Questions fréquentes</h2>
        <div className="space-y-2">
          {filtered.length === 0 ? (
            <p className="text-sm text-on-surface-variant">Aucun résultat pour "{search}"</p>
          ) : (
            filtered.map((faq, i) => (
              <div key={i} className="border border-outline-variant/10 rounded-xl overflow-hidden">
                <button
                  onClick={() => setOpenFaq(openFaq === i ? null : i)}
                  className="w-full flex items-center justify-between p-4 text-left hover:bg-surface-container-low transition-colors"
                >
                  <span className="text-sm font-medium text-on-surface">{faq.q}</span>
                  {openFaq === i ? (
                    <FiChevronDown className="text-on-surface-variant shrink-0" />
                  ) : (
                    <FiChevronRight className="text-on-surface-variant shrink-0" />
                  )}
                </button>
                {openFaq === i && (
                  <div className="px-4 pb-4 text-sm text-on-surface-variant border-t border-outline-variant/10 pt-3">
                    {faq.r}
                  </div>
                )}
              </div>
            ))
          )}
        </div>
      </div>

      {/* Contact */}
      <div className="mt-8 bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10 text-center">
        <FiHelpCircle className="text-3xl text-primary mx-auto mb-3" />
        <h3 className="font-bold text-primary text-sm">Vous ne trouvez pas votre réponse ?</h3>
        <p className="text-xs text-on-surface-variant mt-1">Notre équipe est disponible pour vous aider</p>
        {/* Idem : bouton sans action. Le formulaire de contact existe sur
            /support/contact. */}
        <Link
          to={`${prefix}/support/contact`}
          className="inline-block mt-4 px-6 py-2.5 bg-primary text-on-primary rounded-xl font-semibold text-sm hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
        >
          Contacter le support
        </Link>
      </div>
    </div>
  );
};

export default HelpCenterPage;
