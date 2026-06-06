import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FiArrowLeft, FiClock, FiUser, FiPrinter, FiBookmark, FiChevronRight, FiFileText } from 'react-icons/fi';

const articlesData = {
  1: {
    title: 'Premiers pas avec Présence',
    author: 'Équipe technique',
    date: '15 mai 2026',
    readTime: '5 min',
    sections: [
      { id: 'introduction', title: 'Introduction', content: 'Bienvenue sur Présence, le système de gestion de présence académique. Cette plateforme vous permet de gérer efficacement les présences des étudiants, de générer des rapports détaillés et de suivre l\'assiduité en temps réel.' },
      { id: 'connexion', title: 'Connexion au système', content: 'Pour vous connecter, rendez-vous sur la page de connexion et saisissez vos identifiants administrateur. Si vous avez oublié votre mot de passe, utilisez le lien "Mot de passe oublié" sur la page de connexion. Pour des raisons de sécurité, déconnectez-vous toujours après utilisation, surtout sur un appareil partagé.' },
      { id: 'navigation', title: 'Navigation dans l\'interface', content: 'Le tableau de bord est votre point d\'entrée principal. Il affiche les statistiques clés : taux de présence global, nombre d\'étudiants, cours actifs et sessions aujourd\'hui. La barre latérale vous permet d\'accéder rapidement aux différentes sections : Dashboard, Étudiants, Cours, Présence, Rapports, Paramètres.' },
      { id: 'dashboard', title: 'Comprendre le tableau de bord', content: 'Le tableau de bord présente 4 indicateurs clés en haut de page, suivis d\'un graphique d\'évolution des présences sur 30 jours. Vous trouverez également la liste des étudiants avec le plus d\'absences et l\'historique des événements récents. Les données sont actualisées en temps réel.' },
      { id: 'profils', title: 'Gestion des profils', content: 'Dans la section "Admin Profile", vous pouvez modifier vos informations personnelles, votre photo de profil et vos préférences de notification. Assurez-vous de maintenir vos informations à jour pour faciliter la communication.' },
    ],
    related: [
      { id: 2, title: 'Gestion des étudiants' },
      { id: 3, title: 'Création de cours et UE' },
    ],
  },
  2: {
    title: 'Gestion des étudiants',
    author: 'Équipe technique',
    date: '12 mai 2026',
    readTime: '8 min',
    sections: [
      { id: 'introduction', title: 'Vue d\'ensemble', content: 'La section Gestion des Étudiants vous permet d\'administrer l\'ensemble des étudiants inscrits dans votre établissement. Vous pouvez ajouter, modifier, supprimer et rechercher des étudiants, ainsi que les affecter à des filières et années académiques.' },
      { id: 'ajout', title: 'Ajouter un étudiant', content: 'Cliquez sur le bouton "Nouvel étudiant" pour ouvrir le formulaire d\'inscription. Remplissez les champs obligatoires : nom, prénom, matricule, email, filière et année académique. Le matricule doit être unique dans le système. Vous pouvez également ajouter une photo d\'identité.' },
      { id: 'modification', title: 'Modifier un étudiant', content: 'Pour modifier les informations d\'un étudiant, cliquez sur l\'icône de modification dans la liste. Mettez à jour les champs nécessaires et enregistrez. L\'historique des modifications est conservé pour le suivi administratif.' },
      { id: 'recherche', title: 'Recherche et filtres', content: 'Utilisez la barre de recherche pour trouver un étudiant par nom, prénom, matricule ou email. Les filtres par filière et année académique vous permettent d\'affiner votre recherche. Les résultats sont triés par défaut par nom de famille.' },
    ],
    related: [
      { id: 1, title: 'Premiers pas avec Présence' },
      { id: 5, title: 'Validation de présence' },
    ],
  },
};

export default function KnowledgeBaseArticle() {
  const { id } = useParams();
  const [activeSection, setActiveSection] = useState(null);
  const article = articlesData[id] || articlesData[1];

  if (!article) {
    return (
      <div className="max-w-3xl mx-auto text-center py-12">
        <h1 className="text-2xl font-bold text-primary font-headline mb-2">Article non trouvé</h1>
        <p className="text-sm text-on-surface-variant mb-4">Cet article n'existe pas ou a été supprimé.</p>
        <Link to="/help" className="text-primary font-semibold text-sm hover:underline">Retour au centre d'aide</Link>
      </div>
    );
  }

  return (
    <div className="max-w-4xl">
      <div className="mb-6">
        <Link to="/help" className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary transition-colors">
          <FiArrowLeft size={16} /> Retour au centre d'aide
        </Link>
      </div>

      <div className="flex gap-8">
        {/* Article content */}
        <div className="flex-1 min-w-0">
          <div className="bg-surface-container-lowest rounded-xxl p-8 shadow-sm border border-outline-variant/10">
            <div className="flex items-start justify-between mb-6">
              <div>
                <h1 className="text-2xl font-bold text-primary font-headline mb-3">{article.title}</h1>
                <div className="flex flex-wrap items-center gap-4 text-xs text-on-surface-variant">
                  <span className="flex items-center gap-1.5"><FiUser size={14} /> {article.author}</span>
                  <span className="flex items-center gap-1.5"><FiClock size={14} /> {article.readTime} de lecture</span>
                  <span>{article.date}</span>
                </div>
              </div>
              <button onClick={() => window.print()} className="p-2 hover:bg-surface-container-high rounded-lg transition-colors" title="Imprimer">
                <FiPrinter className="text-on-surface-variant" size={18} />
              </button>
            </div>

            <div className="space-y-8">
              {article.sections.map((section, i) => (
                <div key={section.id} id={section.id}>
                  <h2 className="text-lg font-bold text-primary font-headline mb-3">{section.title}</h2>
                  <p className="text-sm text-on-surface leading-relaxed">{section.content}</p>
                </div>
              ))}
            </div>
          </div>

          {/* Related articles */}
          {article.related && article.related.length > 0 && (
            <div className="mt-8 bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
              <h3 className="text-sm font-bold text-primary mb-4">Articles connexes</h3>
              <div className="space-y-2">
                {article.related.map((rel) => (
                  <Link
                    key={rel.id}
                    to={`/help/article/${rel.id}`}
                    className="flex items-center justify-between p-3 hover:bg-surface-container-low rounded-xl transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <FiFileText className="text-primary shrink-0" size={16} />
                      <span className="text-sm text-on-surface">{rel.title}</span>
                    </div>
                    <FiChevronRight className="text-outline shrink-0" size={16} />
                  </Link>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Table of contents sidebar */}
        <div className="hidden lg:block w-64 shrink-0">
          <div className="sticky top-6 bg-surface-container-lowest rounded-xxl p-5 shadow-sm border border-outline-variant/10">
            <div className="flex items-center gap-2 mb-4">
              <FiBookmark size={16} className="text-primary" />
              <h3 className="text-xs font-bold text-primary uppercase tracking-wider">Sommaire</h3>
            </div>
            <nav className="space-y-1">
              {article.sections.map((section) => (
                <a
                  key={section.id}
                  href={`#${section.id}`}
                  onClick={(e) => { e.preventDefault(); setActiveSection(section.id); }}
                  className={`block text-xs py-1.5 px-2 rounded-lg transition-colors ${
                    activeSection === section.id ? 'bg-primary/10 text-primary font-semibold' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-high'
                  }`}
                >
                  {section.title}
                </a>
              ))}
            </nav>
          </div>
        </div>
      </div>
    </div>
  );
}
