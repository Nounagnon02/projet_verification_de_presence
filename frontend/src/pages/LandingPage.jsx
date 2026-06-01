import { useState, useEffect, useRef } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiChevronRight, FiArrowRight, FiShield, FiSmartphone, FiBarChart2, FiDownload, FiLock, FiMenu, FiX, FiChevronDown, FiChevronUp } from 'react-icons/fi';
import { MdAccountBalance, MdQrCodeScanner, MdAutoAwesome, MdSchool, MdGroups, MdCalendarMonth, MdCloudDone } from 'react-icons/md';
import api from '../api/axios';

const ALL_FEATURES = [
  { icon: MdQrCodeScanner, title: 'Validation par QR Code', desc: 'Scannez les QR codes générés pour chaque séance et validez les présences en un instant depuis votre appareil mobile.' },
  { icon: MdAutoAwesome, title: 'Import IA Emploi du temps', desc: 'Importez vos emplois du temps PDF et laissez l\'intelligence artificielle extraire automatiquement les cours, horaires et salles.' },
  { icon: FiBarChart2, title: 'Statistiques & Rapports', desc: 'Suivez les taux de présence par étudiant, filière ou UE avec des tableaux de bord interactifs et exportez en PDF/CSV.' },
  { icon: FiShield, title: 'Anti-fraude intelligent', desc: 'Détection automatique des anomalies de présence avec géolocalisation, empreinte numérique et analyse des comportements suspects.' },
  { icon: FiSmartphone, title: 'Application Mobile', desc: 'Interface responsive accessible depuis n\'importe quel appareil. Validation des présences depuis votre téléphone en toute simplicité.' },
  { icon: FiDownload, title: 'Export & Archivage', desc: 'Exportez vos relevés de présence en PDF ou CSV et conservez un historique complet sur plusieurs années académiques.' },
];

const STEPS = [
  { num: '1', icon: MdAccountBalance, title: 'Créez votre structure', desc: 'Configurez vos filières, années académiques et UEs dans le panneau d\'administration.' },
  { num: '2', icon: MdCalendarMonth, title: 'Importez les cours', desc: 'Importez votre emploi du temps via PDF — l\'IA Gemini extrait automatiquement tous les événements.' },
  { num: '3', icon: MdQrCodeScanner, title: 'Générez les QR codes', desc: 'Pour chaque cours, un QR code unique est généré. Les étudiants le scannent à l\'entrée en cours.' },
  { num: '4', icon: MdCloudDone, title: 'Suivez en temps réel', desc: 'Visualisez les présences en direct et recevez des alertes en cas d\'anomalie ou de fraude suspectée.' },
];

/* ── Compteur animé ── */
function AnimatedCounter({ target, suffix }) {
  const [count, setCount] = useState(0);
  const ref = useRef(null);
  const observed = useRef(false);

  useEffect(() => {
    const el = ref.current;
    if (!el || observed.current) return;
    observed.current = true;
    const observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) {
        let start = 0;
        const step = Math.ceil(target / 60);
        const timer = setInterval(() => {
          start += step;
          if (start >= target) { start = target; clearInterval(timer); }
          setCount(start);
        }, 20);
        observer.disconnect();
      }
    }, { threshold: 0.3 });
    observer.observe(el);
    return () => observer.disconnect();
  }, [target]);

  return <span ref={ref}>{count.toLocaleString()}{suffix}</span>;
}

/* ── Navbar ── */
function Navbar() {
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  return (
    <nav
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${
        scrolled
          ? 'bg-white/90 backdrop-blur-xl shadow-lg shadow-black/5'
          : 'bg-white'
      }`}
    >
      <div className="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
        <div className="flex items-center justify-between h-20">
          {/* ── Coin supérieur gauche ── */}
          <Link to="/" className="flex items-center gap-3 group shrink-0">
            <div className="w-10 h-10 bg-gradient-to-br from-primary to-primary-container rounded-xl flex items-center justify-center text-white shadow-lg shadow-primary/15">
              <MdAccountBalance size={22} />
            </div>
            <div>
              <span className="text-lg font-bold tracking-tight text-primary font-headline">UAC Présence</span>
              <p className="text-[10px] font-medium text-on-surface-variant uppercase tracking-[0.2em]">Academic Portal</p>
            </div>
          </Link>

          {/* ── Coin supérieur droit ── */}
          <div className="hidden md:flex items-center gap-8">
            <a href="#features" className="text-sm font-medium text-on-surface-variant hover:text-primary transition-colors">Fonctionnalités</a>
            <a href="#stats" className="text-sm font-medium text-on-surface-variant hover:text-primary transition-colors">Statistiques</a>
            <a href="#how-it-works" className="text-sm font-medium text-on-surface-variant hover:text-primary transition-colors">Guide</a>
            <Link
              to="/login"
              className="flex items-center gap-2 bg-gradient-to-br from-primary to-primary-container text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all"
            >
              <FiLock size={14} /> Connexion
            </Link>
          </div>

          <button
            className="md:hidden p-2 hover:bg-surface-container-high rounded-lg transition-colors"
            onClick={() => setOpen(!open)}
          >
            {open ? <FiX size={22} /> : <FiMenu size={22} />}
          </button>
        </div>

        {open && (
          <div className="md:hidden pb-6 space-y-3">
            <a href="#features" onClick={() => setOpen(false)} className="block px-3 py-2.5 text-sm font-medium text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">Fonctionnalités</a>
            <a href="#stats" onClick={() => setOpen(false)} className="block px-3 py-2.5 text-sm font-medium text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">Statistiques</a>
            <a href="#how-it-works" onClick={() => setOpen(false)} className="block px-3 py-2.5 text-sm font-medium text-on-surface-variant hover:bg-surface-container-high rounded-xl transition-colors">Guide</a>
            <Link to="/login" onClick={() => setOpen(false)} className="flex items-center justify-center gap-2 bg-primary text-white px-6 py-3 rounded-xl text-sm font-bold">Connexion</Link>
          </div>
        )}
      </div>
    </nav>
  );
}

/* ── Footer ── */
function Footer() {
  return (
    <footer className="bg-primary text-white">
      <div className="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 py-12">
        <div className="flex flex-col md:flex-row justify-between items-center gap-6">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
              <MdAccountBalance size={22} className="text-white" />
            </div>
            <div>
              <span className="text-lg font-bold tracking-tight font-headline">UAC Présence</span>
              <p className="text-[10px] font-medium text-white/60 uppercase tracking-[0.2em]">Academic Portal</p>
            </div>
          </div>
          <p className="text-sm text-white/60">© {new Date().getFullYear()} UAC Présence. Tous droits réservés.</p>
          <p className="text-sm text-white/50">Développé avec ❤️ pour l'UAC</p>
        </div>
      </div>
    </footer>
  );
}

/* ── Page principale ── */
export default function LandingPage() {
  const navigate = useNavigate();
  const [showAllFeatures, setShowAllFeatures] = useState(false);
  const [stats, setStats] = useState(null);
  const [statsLoading, setStatsLoading] = useState(true);
  const displayedFeatures = showAllFeatures ? ALL_FEATURES : ALL_FEATURES.slice(0, 3);

  // Chargement dynamique des statistiques depuis l'API
  useEffect(() => {
    api.get('/landing/stats')
      .then(res => {
        if (res.data?.success && res.data?.data) {
          setStats(res.data.data);
        }
      })
      .catch(() => {
        console.warn('Impossible de charger les statistiques — le backend est peut-être indisponible.');
      })
      .finally(() => setStatsLoading(false));
  }, []);

  // Construit le tableau STATS à partir des données API
  const STATS = stats ? [
    { label: 'Étudiants suivis', value: stats.total_etudiants ?? 0, suffix: '+' },
    { label: 'Cours enregistrés', value: stats.total_cours ?? 0, suffix: '+' },
    { label: 'Présences validées', value: stats.presences_valides ?? 0, suffix: '+' },
    { label: 'Taux de présence', value: stats.taux_presence_global ?? 0, suffix: '%' },
  ] : [];

  return (
    <div className="bg-surface text-on-surface min-h-screen">
      <Navbar />

      {/* ═══════════════ HERO — Image UAC en fond ═══════════════ */}
      <section className="relative min-h-screen flex items-center pt-20 overflow-hidden">
        <div className="absolute inset-0">
          <img
            src="/images/rectorat-uac.jpg"
            alt="Rectorat de l'Université d'Abomey-Calavi"
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-b from-primary/75 via-primary/60 to-primary/80"></div>
          <div className="absolute inset-0 shadow-[inset_0_0_120px_rgba(0,0,0,0.4)] pointer-events-none"></div>
        </div>

        <div className="relative max-w-7xl mx-auto px-6 sm:px-8 lg:px-12 py-24 w-full">
          <div className="max-w-4xl mx-auto text-center">
            <div className="inline-flex items-center gap-2 bg-white/15 backdrop-blur-sm border border-white/20 rounded-full px-5 py-1.5 mb-8">
              <MdSchool size={16} className="text-white/90" />
              <span className="text-xs font-semibold text-white/90 tracking-wide">
                Université d'Abomey-Calavi
              </span>
            </div>

            <h1 className="text-5xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight leading-[1.08] font-headline mb-6">
              <span className="text-white">Gérez les présences</span><br />
              <span className="text-white/90">intelligemment</span>
            </h1>

            <p className="text-lg sm:text-xl text-white/80 max-w-2xl mx-auto leading-relaxed mb-10">
              Solution de gestion de présence académique nouvelle génération pour l'UAC.
              QR codes, import IA, anti-fraude, et tableaux de bord en temps réel.
            </p>

            <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
              <button
                onClick={() => navigate('/login')}
                className="group flex items-center gap-2 bg-white text-primary px-8 py-4 rounded-xl font-bold text-base shadow-2xl shadow-black/10 hover:scale-[1.02] active:scale-[0.98] transition-all w-full sm:w-auto justify-center"
              >
                Accéder au portail <FiArrowRight className="group-hover:translate-x-1 transition-transform" />
              </button>
              <a
                href="#features"
                className="flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white border border-white/25 px-8 py-4 rounded-xl font-bold text-base hover:bg-white/25 transition-all w-full sm:w-auto justify-center"
              >
                En savoir plus
              </a>
            </div>
          </div>

          {/* Mini cartes */}
          <div className="mt-20 grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
            {[
              { icon: MdQrCodeScanner, label: 'Scanner QR', desc: 'Validation en un scan' },
              { icon: MdAutoAwesome, label: 'IA Gemini', desc: 'Analyse automatique' },
              { icon: MdGroups, label: 'Étudiants', desc: 'Suivi personnalisé' },
            ].map((item, i) => (
              <div
                key={i}
                className="group bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 text-center hover:bg-white/20 hover:border-white/30 hover:shadow-2xl hover:shadow-black/10 transition-all duration-300"
              >
                <div className="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 group-hover:bg-white/20 transition-all duration-300">
                  <item.icon className="text-2xl text-white" />
                </div>
                <h3 className="font-bold text-white mb-1">{item.label}</h3>
                <p className="text-xs text-white/70">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
          <FiChevronDown size={24} className="text-white/50" />
        </div>
      </section>

      {/* ═══════════════ STATS ═══════════════ */}
      {stats && (
        <section id="stats" className="py-20 relative">
          <div className="absolute inset-0 bg-[linear-gradient(180deg,transparent_0%,#f7f9fd_100%)] pointer-events-none"></div>
          <div className="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
            <div className="bg-gradient-to-br from-primary to-primary-container rounded-[32px] p-10 sm:p-14 shadow-2xl shadow-primary/20">
              <div className="grid grid-cols-2 md:grid-cols-4 gap-8 sm:gap-12">
                {STATS.map((stat, i) => (
                  <div key={i} className="text-center">
                    <p className="text-4xl sm:text-5xl font-extrabold text-white font-headline tracking-tight">
                      <AnimatedCounter target={stat.value} suffix={stat.suffix} />
                    </p>
                    <p className="text-sm text-white/70 mt-2 font-medium">{stat.label}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>
      )}

      {/* ═══════════════ FEATURES ═══════════════ */}
      <section id="features" className="py-20">
        <div className="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
          <div className="max-w-2xl mx-auto text-center mb-12">
            <span className="text-xs font-bold text-primary uppercase tracking-[0.2em] bg-primary-fixed/50 px-4 py-1.5 rounded-full">Fonctionnalités</span>
            <h2 className="text-4xl sm:text-5xl font-bold text-primary mt-6 mb-4 font-headline">Tout ce dont vous avez besoin</h2>
            <p className="text-on-surface-variant">Une plateforme complète pour la gestion des présences académiques.</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-5xl mx-auto">
            {displayedFeatures.map((feature, i) => {
              const Icon = feature.icon;
              return (
                <div key={i} className="group bg-surface-container-lowest rounded-2xl p-8 border border-outline-variant/10 hover:border-primary/20 hover:shadow-xl transition-all duration-300">
                  <div className="w-14 h-14 bg-primary/5 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 group-hover:bg-primary/10 transition-all">
                    <Icon className="text-2xl text-primary" />
                  </div>
                  <h3 className="text-lg font-bold text-primary mb-2">{feature.title}</h3>
                  <p className="text-sm text-on-surface-variant leading-relaxed">{feature.desc}</p>
                </div>
              );
            })}
          </div>

          {/* Bouton Voir plus / Voir moins */}
          {!showAllFeatures && (
            <div className="text-center mt-10">
              <button
                onClick={() => setShowAllFeatures(true)}
                className="inline-flex items-center gap-2 text-primary font-bold text-sm hover:gap-3 transition-all group"
              >
                Voir toutes les fonctionnalités <FiChevronRight className="group-hover:translate-x-1 transition-transform" />
              </button>
            </div>
          )}
          {showAllFeatures && (
            <div className="text-center mt-10">
              <button
                onClick={() => setShowAllFeatures(false)}
                className="inline-flex items-center gap-2 text-primary font-bold text-sm hover:gap-3 transition-all group"
              >
                Voir moins <FiChevronUp className="group-hover:-translate-y-0.5 transition-transform" />
              </button>
            </div>
          )}
        </div>
      </section>

      {/* ═══════════════ HOW IT WORKS ═══════════════ */}
      <section id="how-it-works" className="py-20 bg-surface-container-low">
        <div className="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
          <div className="max-w-2xl mx-auto text-center mb-12">
            <span className="text-xs font-bold text-primary uppercase tracking-[0.2em] bg-primary-fixed/50 px-4 py-1.5 rounded-full">Guide</span>
            <h2 className="text-4xl sm:text-5xl font-bold text-primary mt-6 mb-4 font-headline">Comment ça marche</h2>
            <p className="text-on-surface-variant">Mettez en place votre système en quelques étapes.</p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 max-w-5xl mx-auto">
            {STEPS.map((step, i) => {
              const Icon = step.icon;
              return (
                <div key={i} className="relative text-center group">
                  <div className="w-20 h-20 bg-primary-container rounded-[28px] flex items-center justify-center mx-auto mb-6 shadow-lg shadow-primary/10 group-hover:scale-110 transition-transform">
                    <Icon className="text-3xl text-white" />
                  </div>
                  <div className="absolute -top-2 -right-2 w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white text-sm font-bold shadow-lg shadow-primary/20">
                    {step.num}
                  </div>
                  <h3 className="font-bold text-primary mb-2">{step.title}</h3>
                  <p className="text-sm text-on-surface-variant leading-relaxed">{step.desc}</p>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <Footer />
    </div>
  );
}
