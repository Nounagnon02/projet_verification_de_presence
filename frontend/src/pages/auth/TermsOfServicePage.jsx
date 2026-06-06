import { useNavigate } from 'react-router-dom';
import { FiArrowLeft } from 'react-icons/fi';
import { MdAccountBalance } from 'react-icons/md';

export default function TermsOfServicePage() {
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
        <h1 className="text-3xl font-bold text-primary mb-2">Conditions Générales d'Utilisation</h1>
        <p className="text-sm text-on-surface-variant mb-10">Dernière mise à jour : 5 juin 2026</p>

        <div className="space-y-8 text-on-surface leading-relaxed">
          <section>
            <h2 className="text-xl font-bold text-primary mb-3">1. Objet</h2>
            <p>
              Les présentes Conditions Générales d'Utilisation (ci-après « CGU ») régissent l'accès et l'utilisation
              de la plateforme <strong>Présence</strong>, un service de gestion des présences académiques
              proposé par l'<strong>Université d'Abomey-Calavi</strong> (ci-après « l'UAC »).
            </p>
            <p className="mt-3">
              La plateforme permet aux enseignants et au personnel administratif de gérer les présences aux cours
              via un système de扫描 QR code, d'importer des emplois du temps, et de générer des statistiques de présence.
            </p>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">2. Définitions</h2>
            <ul className="list-disc pl-6 space-y-2">
              <li><strong>Utilisateur :</strong> toute personne physique disposant d'un compte sur la plateforme (enseignant, personnel administratif, administrateur).</li>
              <li><strong>Plateforme :</strong> l'application web Présence accessible depuis le navigateur.</li>
              <li><strong>Données personnelles :</strong> toute information se rapportant à une personne physique identifiée ou identifiable.</li>
              <li><strong>Session :</strong> période d'utilisation continue de la plateforme par un utilisateur connecté.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">3. Accès à la Plateforme</h2>
            <p>
              L'accès à la plateforme est réservé aux personnels enseignants et administratifs de l'UAC
              disposant d'identifiants valides. Chaque utilisateur s'engage à :
            </p>
            <ul className="list-disc pl-6 mt-2 space-y-2">
              <li>Ne pas partager ses identifiants de connexion avec des tiers.</li>
              <li>Ne pas tenter d'accéder aux données d'autres utilisateurs sans autorisation.</li>
              <li>Utiliser la plateforme exclusivement dans le cadre de ses missions académiques.</li>
              <li>Signaler toute utilisation non autorisée de son compte à l'administration.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">4. Fonctionnalités</h2>
            <p>La plateforme offre les fonctionnalités suivantes :</p>
            <ul className="list-disc pl-6 mt-2 space-y-2">
              <li>Gestion des utilisateurs et des rôles (administration).</li>
              <li>Import et gestion des emplois du temps.</li>
              <li>Scannage de codes QR pour valider les présences.</li>
              <li>Gestion des unités d'enseignement (UE) et des éléments constitutifs (EC).</li>
              <li>Génération de rapports et statistiques de présence.</li>
              <li>Détection des anomalies de présence.</li>
              <li>Export des données de présence.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">5. Obligations de l'Utilisateur</h2>
            <p>L'utilisateur s'engage à :</p>
            <ul className="list-disc pl-6 mt-2 space-y-2">
              <li>Fournir des informations exactes et à jour lors de son inscription.</li>
              <li>Ne pas utiliser la plateforme à des fins frauduleuses ou illicites.</li>
              <li>Ne pas perturber le fonctionnement technique de la plateforme.</li>
              <li>Respecter la confidentialité des données des étudiants.</li>
              <li>Se déconnecter après chaque utilisation sur un poste partagé.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">6. Propriété Intellectuelle</h2>
            <p>
              L'ensemble des éléments constituant la plateforme (logiciel, design, base de données, documentation)
              est la propriété exclusive de l'Université d'Abomey-Calavi. Toute reproduction, modification,
              distribution ou exploitation non autorisée est interdite.
            </p>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">7. Protection des Données</h2>
            <p>
              Conformément à la Loi n° 2017-20 du 20 avril 2017 portant protection des données à caractère
              personnel en République du Bénin, l'UAC s'engage à protéger les données personnelles des utilisateurs.
              Les traitements de données effectués via la plateforme sont déclarés auprès de l'Autorité de
              Protection des Données Personnelles (APDP).
            </p>
            <p className="mt-3">
              Les données collectées sont utilisées uniquement dans le cadre de la gestion des présences
              académiques et ne sont pas cédées à des tiers. Les utilisateurs disposent d'un droit d'accès,
              de rectification et de suppression de leurs données.
            </p>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">8. Sécurité</h2>
            <p>
              L'UAC met en œuvre les mesures techniques et organisationnelles appropriées pour garantir la
              sécurité des données traitées sur la plateforme, notamment :
            </p>
            <ul className="list-disc pl-6 mt-2 space-y-2">
              <li>Chiffrement des communications via TLS 1.3.</li>
              <li>Hachage sécurisé des mots de passe (bcrypt).</li>
              <li>Journalisation des accès et des actions critiques.</li>
              <li>Sauvegardes régulières des données.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">9. Limitation de Responsabilité</h2>
            <p>
              L'UAC ne saurait être tenue responsable des dommages indirects résultant de l'utilisation
              de la plateforme, notamment en cas de :
            </p>
            <ul className="list-disc pl-6 mt-2 space-y-2">
              <li>Interruption temporaire du service pour maintenance.</li>
              <li>Perturbations liées au réseau internet.</li>
              <li>Utilisation frauduleuse des identifiants d'un utilisateur.</li>
              <li>Force majeure telle que définie par la jurisprudence béninoise.</li>
            </ul>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">10. Modification des CGU</h2>
            <p>
              L'UAC se réserve le droit de modifier les présentes CGU à tout moment. Les utilisateurs seront
              informés des modifications substantielles par email ou lors de leur prochaine connexion.
              L'utilisation continue de la plateforme après modification vaut acceptation des nouvelles CGU.
            </p>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">11. Loi Applicable et Juridiction</h2>
            <p>
              Les présentes CGU sont régies par le droit béninois. Tout litige relatif à l'interprétation
              ou à l'exécution des CGU relève de la compétence exclusive des tribunaux de Cotonou.
            </p>
          </section>

          <section>
            <h2 className="text-xl font-bold text-primary mb-3">12. Contact</h2>
            <p>
              Pour toute question relative aux présentes CGU, vous pouvez contacter :
            </p>
            <div className="mt-3 p-4 bg-surface-container-high rounded-xl">
              <p><strong>Université d'Abomey-Calavi</strong></p>
              <p>Direction des Systèmes d'Information</p>
              <p>01 BP 526 Cotonou, Bénin</p>
              <p>Email : dsi@uac.bj</p>
              <p>Tél : +229 21 30 10 20</p>
            </div>
          </section>
        </div>

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
