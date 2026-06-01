import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FiSearch, FiClock, FiArrowLeft, FiBookOpen, FiChevronRight, FiFileText } from 'react-icons/fi';

const categoriesData = {
  'guide': {
    title: "Guide d'utilisation",
    icon: <FiBookOpen />,
    desc: 'Documentation complète du système de gestion de présence',
    articles: [
      { id: 1, title: 'Premiers pas avec UAC Présence', excerpt: 'Découvrez comment naviguer dans le système et comprendre les fonctionnalités de base.', readTime: '5 min', icon: <FiFileText /> },
      { id: 2, title: 'Gestion des étudiants', excerpt: 'Apprenez à ajouter, modifier et supprimer des étudiants dans le système.', readTime: '8 min', icon: <FiFileText /> },
      { id: 3, title: 'Création de cours et UE', excerpt: 'Comment structurer vos cours et unités d\'enseignement.', readTime: '6 min', icon: <FiFileText /> },
      { id: 4, title: 'Génération de QR codes', excerpt: 'Guide pour générer et projeter les QR codes de vos séances.', readTime: '4 min', icon: <FiFileText /> },
      { id: 5, title: 'Validation de présence', excerpt: 'Les différentes méthodes de validation de présence des étudiants.', readTime: '7 min', icon: <FiFileText /> },
    ],
  },
  'faq': {
    title: 'FAQ',
    icon: <FiBookOpen />,
    desc: 'Questions fréquemment posées sur le système',
    articles: [
      { id: 6, title: 'Questions générales', excerpt: 'Réponses aux questions courantes sur l\'utilisation du système.', readTime: '3 min', icon: <FiFileText /> },
      { id: 7, title: 'Dépannage des problèmes courants', excerpt: 'Solutions aux problèmes les plus fréquemment rencontrés.', readTime: '10 min', icon: <FiFileText /> },
    ],
  },
  'support': {
    title: 'Support technique',
    icon: <FiBookOpen />,
    desc: 'Contacter l\'équipe technique',
    articles: [
      { id: 8, title: 'Comment contacter le support', excerpt: 'Les différentes façons de joindre notre équipe technique.', readTime: '2 min', icon: <FiFileText /> },
      { id: 9, title: 'Tickets de support', excerpt: 'Comment créer et suivre vos tickets de support.', readTime: '4 min', icon: <FiFileText /> },
      { id: 10, title: 'Temps de réponse', excerpt: 'Nos engagements sur les délais de réponse.', readTime: '2 min', icon: <FiFileText /> },
    ],
  },
};

export default function HelpCenterDetailPage() {
  const { id } = useParams();
  const [search, setSearch] = useState('');

  const category = categoriesData[id] || categoriesData['guide'];
  const filtered = category.articles.filter((a) =>
    a.title.toLowerCase().includes(search.toLowerCase()) ||
    a.excerpt.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="max-w-4xl">
      <div className="mb-8">
        <Link to="/help" className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary mb-4 transition-colors">
          <FiArrowLeft size={16} /> Retour au centre d'aide
        </Link>
        <div className="flex items-center gap-3 mb-2">
          <div className="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
            {category.icon}
          </div>
          <div>
            <h1 className="text-2xl font-bold text-primary font-headline">{category.title}</h1>
            <p className="text-sm text-on-surface-variant">{category.desc}</p>
          </div>
        </div>
      </div>

      <div className="relative max-w-lg mb-8">
        <FiSearch className="absolute left-4 top-1/2 -translate-y-1/2 text-outline" />
        <input className="w-full pl-10 pr-4 py-3 bg-surface-container-low rounded-xl border border-outline-variant/20 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
          placeholder="Rechercher dans cette catégorie..." value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      {filtered.length === 0 ? (
        <div className="text-center py-12 text-on-surface-variant bg-surface-container-lowest rounded-xxl border border-outline-variant/10">
          <FiBookOpen className="text-3xl mx-auto mb-3 opacity-40" />
          <p className="text-sm">Aucun article trouvé pour "{search}"</p>
        </div>
      ) : (
        <div className="space-y-3">
          {filtered.map((article) => (
            <Link
              key={article.id}
              to={`/help/article/${article.id}`}
              className="flex items-start gap-4 bg-surface-container-lowest rounded-xxl p-5 shadow-sm border border-outline-variant/10 hover:shadow-md hover:border-primary/20 transition-all group"
            >
              <div className="w-9 h-9 bg-primary/5 rounded-lg flex items-center justify-center text-primary shrink-0 mt-0.5">
                {article.icon}
              </div>
              <div className="flex-1 min-w-0">
                <h3 className="font-semibold text-sm text-primary group-hover:text-primary transition-colors">{article.title}</h3>
                <p className="text-xs text-on-surface-variant mt-1">{article.excerpt}</p>
                <div className="flex items-center gap-3 mt-2 text-[10px] text-on-surface-variant">
                  <span className="flex items-center gap-1"><FiClock size={12} /> {article.readTime}</span>
                </div>
              </div>
              <FiChevronRight className="text-outline shrink-0 mt-2 group-hover:text-primary transition-colors" />
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
