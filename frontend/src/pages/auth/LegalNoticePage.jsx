import { useNavigate } from 'react-router-dom';
import { FiArrowLeft, FiMapPin, FiPhone, FiMail, FiGlobe, FiFileText } from 'react-icons/fi';
import { MdAccountBalance } from 'react-icons/md';

export default function LegalNoticePage() {
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
        <h1 className="text-3xl font-bold text-primary mb-2">Mentions Légales</h1>
        <p className="text-sm text-on-surface-variant mb-10">Conformément à la loi n° 2017-20 du 20 avril 2017</p>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
          <div className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <div className="w-10 h-10 bg-primary/5 rounded-xl flex items-center justify-center mb-4">
              <MdAccountBalance size={22} className="text-primary" />
            </div>
            <h2 className="text-lg font-bold text-primary mb-2">Éditeur de la plateforme</h2>
            <div className="space-y-1.5 text-sm text-on-surface">
              <p><strong>Université d'Abomey-Calavi (UAC)</strong></p>
              <p>Établissement public d'enseignement supérieur et de recherche</p>
              <p>Rectorat : Campus Universitaire d'Abomey-Calavi</p>
            </div>
          </div>

          <div className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <div className="w-10 h-10 bg-primary/5 rounded-xl flex items-center justify-center mb-4">
              <FiMapPin size={22} className="text-primary" />
            </div>
            <h2 className="text-lg font-bold text-primary mb-2">Adresse</h2>
            <div className="space-y-1.5 text-sm text-on-surface">
              <p>Campus Universitaire d'Abomey-Calavi</p>
              <p>01 BP 526 Cotonou, Bénin</p>
              <p>République du Bénin</p>
            </div>
          </div>

          <div className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <div className="w-10 h-10 bg-primary/5 rounded-xl flex items-center justify-center mb-4">
              <FiPhone size={22} className="text-primary" />
            </div>
            <h2 className="text-lg font-bold text-primary mb-2">Contact</h2>
            <div className="space-y-1.5 text-sm text-on-surface">
              <p className="flex items-center gap-2"><FiMail size={14} /> dsi@uac.bj</p>
              <p className="flex items-center gap-2"><FiPhone size={14} /> +229 21 30 10 20</p>
              <p className="flex items-center gap-2"><FiGlobe size={14} /> www.uac.bj</p>
            </div>
          </div>

          <div className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <div className="w-10 h-10 bg-primary/5 rounded-xl flex items-center justify-center mb-4">
              <FiFileText size={22} className="text-primary" />
            </div>
            <h2 className="text-lg font-bold text-primary mb-2">Représentant légal</h2>
            <div className="space-y-1.5 text-sm text-on-surface">
              <p><strong>Professeur Félicien AVLESSI</strong></p>
              <p>Recteur de l'Université d'Abomey-Calavi</p>
            </div>
          </div>
        </div>

        <div className="space-y-8 text-on-surface leading-relaxed">
          <section className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <h2 className="text-lg font-bold text-primary mb-3">Hébergement</h2>
            <p>
              La plateforme est hébergée par les infrastructures numériques de
              l'Université d'Abomey-Calavi.
            </p>
            <div className="mt-3 p-4 bg-surface-container-high rounded-xl text-sm">
              <p><strong>Hébergeur :</strong> Direction des Systèmes d'Information (DSI)</p>
              <p><strong>Adresse :</strong> Campus Universitaire d'Abomey-Calavi, 01 BP 526 Cotonou, Bénin</p>
              <p><strong>Email :</strong> dsi@uac.bj</p>
            </div>
          </section>

          <section className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <h2 className="text-lg font-bold text-primary mb-3">Propriété intellectuelle</h2>
            <p>
              L'ensemble des contenus de la plateforme (textes, graphismes, logos, icônes,
              code source, base de données) est la propriété exclusive de l'Université d'Abomey-Calavi
              ou de ses partenaires. Toute reproduction, distribution, modification ou exploitation
              non autorisée est strictement interdite et peut donner lieu à des poursuites judiciaires.
            </p>
          </section>

          <section className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <h2 className="text-lg font-bold text-primary mb-3">Protection des données</h2>
            <p>
              Conformément à la Loi n° 2017-20 du 20 avril 2017 portant protection des données à caractère
              personnel en République du Bénin, les utilisateurs disposent d'un droit d'accès, de
              rectification et de suppression des données les concernant.
            </p>
            <p className="mt-3">
              Pour exercer ces droits, contactez le Délégué à la Protection des Données à l'adresse :
              <a href="mailto:dsi@uac.bj" className="text-primary hover:underline ml-1">dsi@uac.bj</a>.
            </p>
          </section>

          <section className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <h2 className="text-lg font-bold text-primary mb-3">Cookies</h2>
            <p>
              La plateforme utilise des cookies strictement nécessaires à son fonctionnement technique
              (gestion de session, sécurité). Aucun cookie publicitaire ou de suivi n'est utilisé.
              Les cookies de session sont supprimés à la déconnexion.
            </p>
          </section>

          <section className="bg-surface-container-lowest rounded-2xl p-6 border border-outline-variant/10">
            <h2 className="text-lg font-bold text-primary mb-3">Responsabilité</h2>
            <p>
              L'Université d'Abomey-Calavi s'efforce d'assurer l'exactitude et la mise à jour des
              informations diffusées sur la plateforme. Toutefois, elle ne saurait garantir
              l'exhaustivité ou l'absence de modification par un tiers. L'UAC décline toute
              responsabilité en cas de :
            </p>
            <ul className="list-disc pl-6 mt-2 space-y-1">
              <li>Interruption temporaire du service pour maintenance technique.</li>
              <li>Perturbations liées au réseau internet ou aux infrastructures externes.</li>
              <li>Dommages résultant d'une intrusion frauduleuse d'un tiers.</li>
              <li>Contenus accessibles via des liens externes.</li>
            </ul>
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
