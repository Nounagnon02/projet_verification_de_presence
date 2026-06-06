import { useNavigate } from 'react-router-dom';
import { FiArrowLeft, FiShield, FiEye, FiLock, FiDatabase, FiUsers, FiMail } from 'react-icons/fi';
import { MdAccountBalance } from 'react-icons/md';

const sections = [
  {
    icon: FiEye,
    title: '1. Quelles données collectons-nous ?',
    content: [
      'Données d\'identification : nom, prénom, adresse email académique, matricule étudiant ou enseignant.',
      'Données de connexion : historique des connexions, adresse IP, navigateur utilisé, horodatage.',
      'Données de présence : historiques de scans QR code, cours suivis, horaires de présence.',
      'Données académiques : filière, année universitaire, UE/EC inscrits, emploi du temps.',
    ],
  },
  {
    icon: FiDatabase,
    title: '2. Finalités du traitement',
    content: [
      'Gestion et suivi des présences aux cours et examens.',
      'Génération de statistiques académiques et de rapports de présence.',
      'Détection des anomalies et fraudes potentielles.',
      'Communication avec les étudiants et enseignants concernant leur présence.',
      'Amélioration du service et analyse pédagogique.',
    ],
  },
  {
    icon: FiShield,
    title: '3. Base légale du traitement',
    content: [
      'Le traitement des données est fondé sur l\'exécution de la mission d\'intérêt public de l\'Université d\'Abomey-Calavi en matière d\'enseignement supérieur et de recherche.',
      'Certains traitements peuvent reposer sur le consentement explicite des utilisateurs, notamment pour les communications facultatives.',
    ],
  },
  {
    icon: FiUsers,
    title: '4. Destinataires des données',
    content: [
      'Les données sont accessibles aux personnels administratifs et enseignants habilités de l\'UAC.',
      'Les données ne sont pas cédées à des tiers commerciaux.',
      'Des sous-traitants techniques (hébergeur, prestataire de services cloud) peuvent avoir accès aux données dans le cadre strict de leurs prestations et sous contrat de sous-traitance conforme à la réglementation.',
    ],
  },
  {
    icon: FiLock,
    title: '5. Durée de conservation',
    content: [
      'Données de compte : conservées pendant toute la durée de la relation académique et jusqu\'à 5 ans après la dernière utilisation.',
      'Données de présence : conservées pour une durée de 10 ans à des fins d\'archivage académique.',
      'Données de connexion : conservées 1 an à des fins de sécurité.',
      'À l\'expiration de ces durées, les données sont anonymisées ou supprimées.',
    ],
  },
  {
    icon: FiMail,
    title: '6. Vos droits',
    content: [
      'Droit d\'accès : vous pouvez obtenir une copie des données vous concernant.',
      'Droit de rectification : vous pouvez demander la correction de données inexactes.',
      'Droit à l\'effacement : vous pouvez demander la suppression de vos données dans les limites prévues par la loi.',
      'Droit à la limitation : vous pouvez demander la suspension du traitement de vos données.',
      'Droit d\'opposition : vous pouvez vous opposer au traitement de vos données pour motifs légitimes.',
    ],
  },
  {
    icon: FiShield,
    title: '7. Sécurité des données',
    content: [
      'L\'UAC met en œuvre des mesures techniques et organisationnelles pour garantir la confidentialité et l\'intégrité des données :',
      'Chiffrement des données en transit (TLS 1.3) et au repos (AES-256).',
      'Authentification forte des utilisateurs (mots de passe hachés, tokens d\'API sécurisés).',
      'Journalisation des accès et audit régulier des accès aux données sensibles.',
      'Sauvegardes chiffrées avec test de restauration périodique.',
      'Formation du personnel aux bonnes pratiques de protection des données.',
    ],
  },
];

export default function PrivacyPolicyPage() {
  const navigate = useNavigate();

  return (
    <div className="min-h-screen bg-surface">
      {/* Header */}
      <div className="sticky top-0 z-10 bg-surface/80 backdrop-blur-lg border-b border-outline-variant/10">
        <div className="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 bg-primary rounded-xl flex items-center justify-center text-white">
              <MdAccountBalance size={20} />
            </div>
            <span className="text-lg font-bold text-primary font-headline">Présence</span>
          </div>
          <button onClick={() => navigate('/login')}
            className="inline-flex items-center gap-1.5 text-sm text-on-surface-variant hover:text-primary transition-colors">
            <FiArrowLeft size={14} /> Retour
          </button>
        </div>
      </div>

      <main className="max-w-4xl mx-auto px-6 py-12">
        <div className="flex items-center gap-4 mb-2">
          <div className="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center">
            <FiShield className="text-primary" size={24} />
          </div>
          <div>
            <h1 className="text-3xl font-bold text-primary">Politique de Confidentialité</h1>
            <p className="text-sm text-on-surface-variant">Dernière mise à jour : 5 juin 2026</p>
          </div>
        </div>

        <p className="mt-6 mb-10 text-on-surface leading-relaxed">
          L'<strong>Université d'Abomey-Calavi</strong> attache une grande importance à la protection
          et à la confidentialité des données personnelles de ses utilisateurs. La présente politique
          de confidentialité vous informe de la manière dont vos données sont collectées, traitées et
          protégées dans le cadre de l'utilisation de la plateforme <strong>Présence</strong>,
          conformément à la Loi n° 2017-20 du 20 avril 2017 portant protection des données à caractère
          personnel en République du Bénin et au Règlement Général sur la Protection des Données (RGPD)
          de l'Union Européenne.
        </p>

        <div className="space-y-8">
          {sections.map((section) => (
            <section key={section.title} className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
              <div className="flex items-start gap-4">
                <div className="w-10 h-10 bg-primary/5 rounded-xl flex items-center justify-center shrink-0 mt-1">
                  <section.icon className="text-primary" size={20} />
                </div>
                <div>
                  <h2 className="text-lg font-bold text-primary mb-3">{section.title}</h2>
                  <ul className="space-y-2">
                    {section.content.map((item, i) => (
                      <li key={i} className="flex items-start gap-2 text-on-surface">
                        <span className="text-primary mt-1.5 shrink-0">•</span>
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </section>
          ))}
        </div>

        <section className="mt-8 bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
          <h2 className="text-lg font-bold text-primary mb-3">8. Contact et réclamation</h2>
          <p className="text-on-surface mb-4">
            Pour exercer vos droits ou pour toute question relative à la protection de vos données, vous pouvez contacter :
          </p>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="p-4 bg-surface-container-high rounded-xl">
              <p className="font-semibold text-primary">Délégué à la Protection des Données (DPD)</p>
              <p className="text-sm text-on-surface-variant mt-1">dsi@uac.bj</p>
              <p className="text-sm text-on-surface-variant">+229 21 30 10 20</p>
            </div>
            <div className="p-4 bg-surface-container-high rounded-xl">
              <p className="font-semibold text-primary">Autorité de contrôle (APDP)</p>
              <p className="text-sm text-on-surface-variant mt-1">Autorité de Protection des Données Personnelles</p>
              <p className="text-sm text-on-surface-variant">01 BP 1234 Cotonou, Bénin</p>
            </div>
          </div>
        </section>

        <div className="mt-12 pt-8 border-t border-outline-variant/10 flex justify-center">
          <button onClick={() => navigate('/login')}
            className="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold text-sm hover:opacity-90 transition-all">
            <FiArrowLeft size={14} /> Retour à la connexion
          </button>
        </div>
      </main>
    </div>
  );
}
