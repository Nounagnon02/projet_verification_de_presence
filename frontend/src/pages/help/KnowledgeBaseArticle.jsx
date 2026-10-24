import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FiArrowLeft, FiClock, FiUser, FiPrinter, FiBookmark, FiChevronRight, FiFileText } from 'react-icons/fi';
import { usePrefixAide } from '../../hooks/usePrefixAide';

const articlesData = {
  1: {
    title: 'Premiers pas avec Présence',
    author: 'Documentation UAC Présences',
    readTime: '5 min',
    sections: [
      {
        id: 'introduction',
        title: 'Introduction',
        content:
          "Bienvenue sur Présence, le système de gestion de présence académique de l'UAC. Cette plateforme permet de gérer les étudiants, les cours et emplois du temps, de générer des QR codes de séance et de suivre l'assiduité à partir de rapports exportables.",
      },
      {
        id: 'connexion',
        title: 'Connexion au système',
        content:
          'Pour vous connecter, rendez-vous sur la page de connexion et saisissez vos identifiants administrateur. Si vous avez oublié votre mot de passe, utilisez le lien "Mot de passe oublié" sur la page de connexion. Pour des raisons de sécurité, déconnectez-vous toujours après utilisation, surtout sur un appareil partagé.',
      },
      {
        id: 'navigation',
        title: "Navigation dans l'interface",
        content:
          "Le tableau de bord est votre point d'entrée principal. La barre latérale donne accès aux différentes sections : Dashboard, Étudiants, Cours & UE/EC, Emploi du temps, Séances, Présences, Rapports et Paramètres.",
      },
      {
        id: 'dashboard',
        title: 'Comprendre le tableau de bord',
        content:
          "Le tableau de bord présente 4 indicateurs : le nombre total d'étudiants, les présences du jour, le taux de présence global et les scans suspects à arbitrer. Il affiche aussi un graphique d'évolution des présences, les étudiants les plus absents et les cours du jour.",
      },
      {
        id: 'profils',
        title: 'Gestion du profil',
        content:
          "Dans la page Profil, vous pouvez modifier votre nom et votre adresse email, changer votre mot de passe, activer l'authentification à deux facteurs et consulter les appareils actuellement connectés à votre compte.",
      },
    ],
    related: [
      { id: 2, title: 'Gestion des étudiants' },
      { id: 4, title: 'Génération de QR codes' },
    ],
  },
  2: {
    title: 'Gestion des étudiants',
    author: 'Documentation UAC Présences',
    readTime: '6 min',
    sections: [
      {
        id: 'introduction',
        title: "Vue d'ensemble",
        content:
          "La section Étudiants permet d'administrer l'ensemble des étudiants inscrits dans votre établissement. Vous pouvez ajouter, modifier, supprimer et rechercher des étudiants, et les affecter à une filière et une année académique.",
      },
      {
        id: 'ajout',
        title: 'Ajouter un étudiant',
        content:
          'Cliquez sur le bouton "Nouvel étudiant" pour ouvrir le formulaire d\'inscription. Remplissez les champs obligatoires : nom, prénom, matricule, email, filière et année académique. Le matricule doit être unique dans le système.',
      },
      {
        id: 'modification',
        title: 'Modifier un étudiant',
        content:
          "Pour modifier les informations d'un étudiant, cliquez sur l'icône de modification dans la liste, mettez à jour les champs nécessaires et enregistrez.",
      },
      {
        id: 'recherche',
        title: 'Recherche et filtres',
        content:
          "Utilisez la barre de recherche pour trouver un étudiant par nom, prénom, matricule ou email. Les filtres par filière et année académique permettent d'affiner la liste.",
      },
    ],
    related: [
      { id: 1, title: 'Premiers pas avec Présence' },
      { id: 5, title: 'Validation de présence' },
    ],
  },
  3: {
    title: 'Création de cours et UE',
    author: 'Documentation UAC Présences',
    readTime: '6 min',
    sections: [
      {
        id: 'structure',
        title: 'Structure UE / EC',
        content:
          "Un cours se compose d'une UE (unité d'enseignement) qui contient un ou plusieurs EC (éléments constitutifs). C'est au niveau de l'EC que se gèrent le volume horaire et les séances, depuis la section Cours & UE/EC.",
      },
      {
        id: 'ec',
        title: 'Ajouter un EC',
        content:
          'Depuis une UE, ajoutez un EC avec son code, son intitulé et son volume horaire. Ce volume borne le total des séances que vous pourrez créer pour cet EC.',
      },
      {
        id: 'emploi-du-temps',
        title: 'Emploi du temps',
        content:
          "La section Emploi du temps permet de déclarer les créneaux récurrents d'un EC (jour de la semaine, heure de début et de fin, salle). La génération planifiée crée ensuite les séances réelles à partir de ces créneaux.",
      },
    ],
    related: [
      { id: 1, title: 'Premiers pas avec Présence' },
      { id: 4, title: 'Génération de QR codes' },
    ],
  },
  4: {
    title: 'Génération de QR codes',
    author: 'Documentation UAC Présences',
    readTime: '4 min',
    sections: [
      {
        id: 'principe',
        title: 'Principe',
        content:
          "Chaque séance (Séances) peut afficher un QR code que les étudiants scannent pour valider leur présence. Le code se renouvelle automatiquement toutes les minutes : un code photographié puis partagé est déjà périmé quand quelqu'un d'autre tente de le scanner.",
      },
      {
        id: 'affichage',
        title: 'Afficher le code',
        content:
          'Depuis la liste des séances, ouvrez une séance et cliquez sur "Générer QR Code" pour l\'afficher et le projeter aux étudiants. Le code n\'est disponible que pendant la fenêtre de prise de présence de la séance, ancrée sur son heure de fin.',
      },
      {
        id: 'delegue',
        title: 'Le délégué de promotion',
        content:
          "Un étudiant responsable désigné dans sa promotion peut aussi afficher le QR code depuis l'application mobile, un peu avant la fin du cours, utile si l'enseignant n'a pas accès à l'interface d'administration.",
      },
    ],
    related: [
      { id: 3, title: 'Création de cours et UE' },
      { id: 5, title: 'Validation de présence' },
    ],
  },
  5: {
    title: 'Validation de présence',
    author: 'Documentation UAC Présences',
    readTime: '5 min',
    sections: [
      {
        id: 'scan',
        title: 'Scan du QR code',
        content:
          "La méthode normale : l'étudiant scanne le QR code projeté avec l'application mobile. Le serveur vérifie aussi, selon la configuration de la salle, la position GPS et le réseau Wi-Fi pour s'assurer qu'il est bien sur place.",
      },
      {
        id: 'manuelle',
        title: 'Saisie manuelle',
        content:
          'Si un étudiant ne peut pas scanner, utilisez la page Saisie manuelle (section Présences) pour enregistrer sa présence en saisissant son matricule.',
      },
      {
        id: 'file-attente',
        title: 'Scans suspects',
        content:
          "Un scan jugé anormal (par exemple plusieurs étudiants depuis un même appareil) n'est pas rejeté silencieusement : il apparaît dans la file d'attente des présences, où un administrateur décide de le valider ou de le refuser.",
      },
    ],
    related: [
      { id: 4, title: 'Génération de QR codes' },
      { id: 2, title: 'Gestion des étudiants' },
    ],
  },
  8: {
    title: 'Comment contacter le support',
    author: 'Documentation UAC Présences',
    readTime: '2 min',
    sections: [
      {
        id: 'formulaire',
        title: 'Le formulaire de contact',
        content:
          'Depuis le Centre d\'aide, cliquez sur "Contacter le support" pour ouvrir un formulaire avec un sujet, une priorité et un message. L\'envoi crée un ticket que vous pouvez ensuite suivre.',
      },
      {
        id: 'email',
        title: 'Par email',
        content:
          "Écrivez à dsi@uac.bj : c'est aujourd'hui le seul canal qui parvient à l'équipe technique, le ticket restant visible de vous seul.",
      },
    ],
    related: [
      { id: 9, title: 'Tickets de support' },
      { id: 10, title: 'Temps de réponse' },
    ],
  },
  9: {
    title: 'Tickets de support',
    author: 'Documentation UAC Présences',
    readTime: '3 min',
    sections: [
      {
        id: 'suivi',
        title: 'Suivre un ticket',
        content:
          'Chaque message envoyé via le formulaire de contact devient un ticket, visible dans la liste des tickets avec son statut (ouvert, en cours, résolu, fermé) et sa priorité.',
      },
      {
        id: 'reponse',
        title: 'Répondre à un ticket',
        content:
          "Ouvrez un ticket pour consulter l'échange et ajouter une réponse. Vous ne voyez que vos propres tickets.",
      },
    ],
    related: [
      { id: 8, title: 'Comment contacter le support' },
      { id: 10, title: 'Temps de réponse' },
    ],
  },
  10: {
    title: 'Temps de réponse',
    author: 'Documentation UAC Présences',
    readTime: '2 min',
    sections: [
      {
        id: 'delais',
        title: 'Ce que la plateforme garantit',
        content:
          "Aucun délai : un ticket n'est visible que par la personne qui l'a créé, et la plateforme ne le transmet pas encore à l'équipe technique. Pour obtenir une réponse, écrivez à dsi@uac.bj en rappelant le numéro du ticket.",
      },
    ],
    related: [
      { id: 8, title: 'Comment contacter le support' },
      { id: 9, title: 'Tickets de support' },
    ],
  },
};

export default function KnowledgeBaseArticle() {
  const prefix = usePrefixAide();
  const { id } = useParams();
  const [activeSection, setActiveSection] = useState(null);
  const article = articlesData[id] || articlesData[1];

  if (!article) {
    return (
      <div className="max-w-3xl mx-auto text-center py-12">
        <h1 className="text-2xl font-bold text-primary font-headline mb-2">Article non trouvé</h1>
        <p className="text-sm text-on-surface-variant mb-4">Cet article n'existe pas ou a été supprimé.</p>
        <Link to={`${prefix}/help`} className="text-primary font-semibold text-sm hover:underline">
          Retour au centre d'aide
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-4xl">
      <div className="mb-6">
        <Link
          to={`${prefix}/help`}
          className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary transition-colors"
        >
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
                  <span className="flex items-center gap-1.5">
                    <FiUser size={14} /> {article.author}
                  </span>
                  <span className="flex items-center gap-1.5">
                    <FiClock size={14} /> {article.readTime} de lecture
                  </span>
                </div>
              </div>
              <button
                onClick={() => window.print()}
                className="p-2 hover:bg-surface-container-high rounded-lg transition-colors"
                title="Imprimer"
              >
                <FiPrinter className="text-on-surface-variant" size={18} />
              </button>
            </div>

            <div className="space-y-8">
              {article.sections.map((section) => (
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
                    to={`${prefix}/help/article/${rel.id}`}
                    className="flex items-center justify-between p-3 hover:bg-surface-container-low rounded-xl transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <FiFileText className="text-primary shrink-0" size={16} />
                      <span className="text-sm text-on-surface">{rel.title}</span>
                    </div>
                    <FiChevronRight className="text-on-surface-variant shrink-0" size={16} />
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
                  onClick={(e) => {
                    e.preventDefault();
                    setActiveSection(section.id);
                  }}
                  className={`block text-xs py-1.5 px-2 rounded-lg transition-colors ${
                    activeSection === section.id
                      ? 'bg-primary/10 text-primary font-semibold'
                      : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-high'
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
