import { useState } from 'react';
import { FiBell, FiShield, FiGlobe, FiSmartphone, FiSave, FiAlertTriangle, FiCheck } from 'react-icons/fi';
import { MdAccountBalance } from 'react-icons/md';

const SettingsPage = () => {
  const [saved, setSaved] = useState(false);
  const [config, setConfig] = useState({
    langue: 'fr',
    seuil_alerte: 50,
    validation_auto: true,
    notifications: true,
    qr_expiry: 30,
    double_auth: false,
    geoloc: false,
  });

  const handleSave = () => {
    setSaved(true);
    setTimeout(() => setSaved(false), 2000);
  };

  return (
    <div className="max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-primary font-headline">Paramètres</h1>
        <p className="text-sm text-on-surface-variant">Configuration du système de gestion de présence</p>
      </div>

      <div className="space-y-6">
        {/* Général */}
        <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
          <h2 className="text-sm font-bold text-primary mb-4 flex items-center gap-2"><FiGlobe /> Configuration Générale</h2>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Langue par défaut</p>
                <p className="text-xs text-on-surface-variant">Langue de l'interface utilisateur</p>
              </div>
              <select value={config.langue} onChange={(e) => setConfig({ ...config, langue: e.target.value })}
                className="px-4 py-2 bg-surface-container-high rounded-xl text-sm border-none focus:ring-2 focus:ring-primary/20">
                <option value="fr">Français</option>
                <option value="en">English</option>
              </select>
            </div>
            <hr className="border-outline-variant/10" />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Expiration du QR Code (secondes)</p>
                <p className="text-xs text-on-surface-variant">Durée de validité d'un QR code généré</p>
              </div>
              <input type="number" value={config.qr_expiry} onChange={(e) => setConfig({ ...config, qr_expiry: parseInt(e.target.value) || 30 })}
                className="w-24 px-3 py-2 bg-surface-container-high rounded-xl text-sm border-none text-center focus:ring-2 focus:ring-primary/20" />
            </div>
          </div>
        </div>

        {/* Alertes */}
        <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
          <h2 className="text-sm font-bold text-primary mb-4 flex items-center gap-2"><FiBell /> Alertes & Notifications</h2>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Notifications push</p>
                <p className="text-xs text-on-surface-variant">Recevoir des alertes en temps réel</p>
              </div>
              <button onClick={() => setConfig({ ...config, notifications: !config.notifications })}
                className={`w-12 h-6 rounded-full transition-colors relative ${config.notifications ? 'bg-primary' : 'bg-surface-container-high'}`}>
                <div className={`w-5 h-5 bg-white rounded-full shadow-sm absolute top-0.5 transition-transform ${config.notifications ? 'translate-x-6' : 'translate-x-0.5'}`} />
              </button>
            </div>
            <hr className="border-outline-variant/10" />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Seuil d'alerte d'absence (%)</p>
                <p className="text-xs text-on-surface-variant">Notifier quand un étudiant dépasse ce taux</p>
              </div>
              <div className="flex items-center gap-2">
                <input type="range" min={0} max={100} value={config.seuil_alerte} onChange={(e) => setConfig({ ...config, seuil_alerte: parseInt(e.target.value) })}
                  className="w-24 accent-primary" />
                <span className="text-sm font-mono font-bold w-8">{config.seuil_alerte}%</span>
              </div>
            </div>
            <hr className="border-outline-variant/10" />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Validation automatique</p>
                <p className="text-xs text-on-surface-variant">Valider la présence dès le scan QR</p>
              </div>
              <button onClick={() => setConfig({ ...config, validation_auto: !config.validation_auto })}
                className={`w-12 h-6 rounded-full transition-colors relative ${config.validation_auto ? 'bg-primary' : 'bg-surface-container-high'}`}>
                <div className={`w-5 h-5 bg-white rounded-full shadow-sm absolute top-0.5 transition-transform ${config.validation_auto ? 'translate-x-6' : 'translate-x-0.5'}`} />
              </button>
            </div>
          </div>
        </div>

        {/* Sécurité */}
        <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/10">
          <h2 className="text-sm font-bold text-primary mb-4 flex items-center gap-2"><FiShield /> Sécurité</h2>
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Authentification à deux facteurs</p>
                <p className="text-xs text-on-surface-variant">Renforcer la sécurité du compte admin</p>
              </div>
              <button onClick={() => setConfig({ ...config, double_auth: !config.double_auth })}
                className={`w-12 h-6 rounded-full transition-colors relative ${config.double_auth ? 'bg-primary' : 'bg-surface-container-high'}`}>
                <div className={`w-5 h-5 bg-white rounded-full shadow-sm absolute top-0.5 transition-transform ${config.double_auth ? 'translate-x-6' : 'translate-x-0.5'}`} />
              </button>
            </div>
            <hr className="border-outline-variant/10" />
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium">Géolocalisation obligatoire</p>
                <p className="text-xs text-on-surface-variant">Exiger la géolocalisation pour valider la présence</p>
              </div>
              <button onClick={() => setConfig({ ...config, geoloc: !config.geoloc })}
                className={`w-12 h-6 rounded-full transition-colors relative ${config.geoloc ? 'bg-primary' : 'bg-surface-container-high'}`}>
                <div className={`w-5 h-5 bg-white rounded-full shadow-sm absolute top-0.5 transition-transform ${config.geoloc ? 'translate-x-6' : 'translate-x-0.5'}`} />
              </button>
            </div>
          </div>
        </div>

        {/* Save Button */}
        <div className="flex items-center justify-end gap-3">
          {saved && (
            <span className="flex items-center gap-1 text-xs text-secondary font-semibold">
              <FiCheck /> Paramètres enregistrés
            </span>
          )}
          <button onClick={handleSave} className="flex items-center gap-2 px-6 py-3 bg-primary text-on-primary rounded-xl font-semibold text-sm hover:opacity-90 transition-all shadow-sm">
            <FiSave /> Enregistrer
          </button>
        </div>
      </div>
    </div>
  );
};

export default SettingsPage;
