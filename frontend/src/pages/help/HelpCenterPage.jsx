import { useState } from 'react';
import { FiSearch, FiHelpCircle, FiMail, FiMessageCircle, FiBookOpen, FiChevronRight, FiChevronDown } from 'react-icons/fi';

const HelpCenterPage = () => {
  const [search, setSearch] = useState('');
  const [openFaq, setOpenFaq] = useState(null);

  const categories = [
    { icon: <FiBookOpen />, title: 'Guide d\'utilisation', desc: 'Documentation complète du système', articles: 12 },
    { icon: <FiMessageCircle />, title: 'FAQ', desc: 'Questions fréquemment posées', articles: 24 },
    { icon: <FiMail />, title: 'Support technique', desc: 'Contacter l\'équipe technique', articles: 3 },
  ];

  const faqs = [
    { q: 'Comment générer un QR code pour un cours ?', r: 'Dans le tableau de bord, cliquez sur "Générer QR Code" pour le cours concerné. Le QR code sera affiché et pourra être projeté aux étudiants.' },
    { q: 'Que faire si un étudiant ne peut pas scanner ?', r: 'Utilisez la validation manuelle depuis la page "Valider présence" en saisissant le matricule de l\'étudiant.' },
    { q: 'Comment exporter les rapports ?', r: 'Rendez-vous dans la section "Rapports Mensuels", sélectionnez le type de rapport et cliquez sur "PDF" ou "CSV".' },
    { q: 'Les données sont-elles sécurisées ?', r: 'Oui, toutes les données sont chiffrées et hébergées sur des serveurs sécurisés. Le système est conforme au RGPD.' },
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
        <FiSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-outline" />
        <input className="w-full pl-10 pr-4 py-3 bg-surface-container-low rounded-xl border border-outline-variant/20 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          placeholder="Rechercher dans l'aide..." value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      {/* Categories */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-10">
        {categories.map((cat, i) => (
          <div key={i} className="bg-surface-container-lowest rounded-xxl p-5 shadow-sm border border-outline-variant/10 hover:shadow-md transition-all cursor-pointer">
            <div className="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-3">{cat.icon}</div>
            <h3 className="font-bold text-sm text-primary">{cat.title}</h3>
            <p className="text-xs text-on-surface-variant mt-1">{cat.desc}</p>
            <span className="text-[10px] text-primary font-semibold mt-2 block">{cat.articles} articles</span>
          </div>
        ))}
      </div>

      {/* FAQ */}
      <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
        <h2 className="text-sm font-bold text-primary mb-6">Questions fréquentes</h2>
        <div className="space-y-2">
          {filtered.length === 0 ? (
            <p className="text-sm text-on-surface-variant">Aucun résultat pour "{search}"</p>
          ) : filtered.map((faq, i) => (
            <div key={i} className="border border-outline-variant/10 rounded-xl overflow-hidden">
              <button onClick={() => setOpenFaq(openFaq === i ? null : i)} className="w-full flex items-center justify-between p-4 text-left hover:bg-surface-container-low transition-colors">
                <span className="text-sm font-medium text-on-surface">{faq.q}</span>
                {openFaq === i ? <FiChevronDown className="text-outline shrink-0" /> : <FiChevronRight className="text-outline shrink-0" />}
              </button>
              {openFaq === i && (
                <div className="px-4 pb-4 text-sm text-on-surface-variant border-t border-outline-variant/10 pt-3">
                  {faq.r}
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Contact */}
      <div className="mt-8 bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10 text-center">
        <FiHelpCircle className="text-3xl text-primary mx-auto mb-3" />
        <h3 className="font-bold text-primary text-sm">Vous ne trouvez pas votre réponse ?</h3>
        <p className="text-xs text-on-surface-variant mt-1">Notre équipe est disponible pour vous aider</p>
        <button className="mt-4 px-6 py-2.5 bg-primary text-on-primary rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
          Contacter le support
        </button>
      </div>
    </div>
  );
};

export default HelpCenterPage;
