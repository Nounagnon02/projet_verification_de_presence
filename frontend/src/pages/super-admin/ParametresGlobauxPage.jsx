import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { FiAlertTriangle } from 'react-icons/fi';
import { recupererParametresGlobaux, modifierParametresGlobaux } from '../../api/resources/parametresGlobaux';
import { useToastCtx } from '../../context/ToastContext';

const CHAMP =
  // Classe partagée (index.css) : rayon, bordure, focus et état désactivé communs.
  'champ px-4 py-3';
const LIBELLE = 'text-xs font-semibold uppercase tracking-wider text-on-surface-variant';

/** Une section de réglages : un bloc, un sous-titre, l'explication de l'enjeu. */
function Section({ titre, description, children }) {
  return (
    <div className="carte p-5 sm:p-7 space-y-5">
      <div>
        <h2 className="font-headline text-base font-bold text-primary">{titre}</h2>
        <p className="mt-1 text-sm text-on-surface-variant">{description}</p>
      </div>
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">{children}</div>
    </div>
  );
}

function Champ({ id, label, suffixe, aide, erreur, ...props }) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className={LIBELLE}>
        {label}
      </label>
      <div className="relative">
        <input
          id={id}
          type="number"
          className={CHAMP}
          aria-invalid={!!erreur}
          aria-describedby={erreur ? `${id}-erreur` : `${id}-aide`}
          {...props}
        />
        {suffixe && (
          <span className="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-xs text-on-surface-variant">
            {suffixe}
          </span>
        )}
      </div>
      {erreur ? (
        <p id={`${id}-erreur`} className="text-xs text-statut-erreur">
          {erreur}
        </p>
      ) : (
        aide && (
          <p id={`${id}-aide`} className="text-xs text-on-surface-variant">
            {aide}
          </p>
        )
      )}
    </div>
  );
}

const CHAMPS_VIDES = {
  scan_minutes_avant_fin: '',
  scan_minutes_apres_fin: '',
  qr_ttl_secondes: '',
  qr_visible_delegue_avant_fin: '',
  seance_duree_max_heures: '',
  limite_scan_par_etudiant: '',
  limite_scan_par_ip: '',
};

export default function ParametresGlobauxPage() {
  const queryClient = useQueryClient();
  const { addToast } = useToastCtx() ?? {};
  const parametresQuery = useQuery({
    queryKey: ['parametres-globaux'],
    queryFn: ({ signal }) => recupererParametresGlobaux(signal),
  });

  const [form, setForm] = useState(CHAMPS_VIDES);
  const [erreurs, setErreurs] = useState({});
  const [envoi, setEnvoi] = useState(false);

  // Même motif que EtablissementDetailPage : préremplissage dérivé du
  // chargement, pendant le rendu plutôt que dans un effet — un effet aurait
  // déclenché un rendu supplémentaire à chaque arrivée de données.
  const [charges, setCharges] = useState(null);
  const donnees = parametresQuery.data?.data ?? null;
  if (donnees && charges !== donnees) {
    setCharges(donnees);
    setForm({
      scan_minutes_avant_fin: donnees.scan_minutes_avant_fin,
      scan_minutes_apres_fin: donnees.scan_minutes_apres_fin,
      qr_ttl_secondes: donnees.qr_ttl_secondes,
      qr_visible_delegue_avant_fin: donnees.qr_visible_delegue_avant_fin,
      seance_duree_max_heures: donnees.seance_duree_max_heures,
      limite_scan_par_etudiant: donnees.limite_scan_par_etudiant,
      limite_scan_par_ip: donnees.limite_scan_par_ip,
    });
  }

  const champ = (nom) => ({
    value: form[nom],
    onChange: (e) => setForm((f) => ({ ...f, [nom]: e.target.value })),
    erreur: erreurs[nom],
  });

  const enregistrer = async (e) => {
    e.preventDefault();
    setEnvoi(true);
    setErreurs({});

    try {
      const reponse = await modifierParametresGlobaux(form);
      addToast?.(reponse?.message || 'Réglages mis à jour.', 'success');
      queryClient.invalidateQueries({ queryKey: ['parametres-globaux'] });
    } catch (err) {
      const errs = err.response?.data?.errors;
      if (errs) {
        setErreurs(Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])));
        addToast?.('Certains réglages ont été refusés : voir le détail sous chaque champ.', 'error');
      } else {
        addToast?.(err.response?.data?.message || 'La mise à jour a échoué.', 'error');
      }
    } finally {
      setEnvoi(false);
    }
  };

  if (parametresQuery.isLoading) {
    return (
      <div className="flex items-center justify-center py-16">
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    );
  }

  if (parametresQuery.isError) {
    return (
      <div className="flex items-start gap-3 rounded-xl border border-error/20 bg-statut-erreur-fond p-4 text-sm text-statut-erreur">
        <FiAlertTriangle className="mt-0.5 shrink-0" size={18} />
        <span>Impossible de charger les réglages. Réessayez dans un instant.</span>
      </div>
    );
  }

  return (
    <form onSubmit={enregistrer} className="mx-auto max-w-4xl space-y-6">
      <div>
        <h1 className="font-headline text-2xl font-bold text-primary">Paramètres</h1>
        <p className="mt-1 max-w-2xl text-sm text-on-surface-variant">
          Réglages anti-fraude et de scan, communs à toutes les facultés. Chaque borne ci-dessous rejette une valeur qui
          a un effet documenté et mesuré : les assouplir reviendrait à permettre de recréer un incident déjà survenu.
        </p>
      </div>

      <Section
        titre="Fenêtre de prise de présence"
        description="Ancrée sur l'heure de FIN du cours, pas son début : un scan accepté dès l'ouverture permettrait de valider sa présence puis de repartir."
      >
        <Champ
          id="scan-avant"
          label="Ouverture avant la fin"
          suffixe="minutes"
          min={1}
          max={120}
          aide="De 1 à 120 minutes."
          {...champ('scan_minutes_avant_fin')}
        />
        <Champ
          id="scan-apres"
          label="Fermeture après la fin"
          suffixe="minutes"
          min={0}
          max={60}
          aide="De 0 à 60 minutes."
          {...champ('scan_minutes_apres_fin')}
        />
      </Section>

      <Section
        titre="QR Code dynamique"
        description="Un code photographié puis partagé doit être déjà périmé quand le destinataire le scanne : c'est cette durée de vie qui le rend inexploitable, pas un usage unique."
      >
        <Champ
          id="qr-ttl"
          label="Durée de vie avant rotation"
          suffixe="secondes"
          min={30}
          max={300}
          aide="De 30 à 300 s. En dessous de 30 s, sans effet réel : le jeton tourne au mieux une fois par minute."
          {...champ('qr_ttl_secondes')}
        />
        <Champ
          id="qr-delegue"
          label="Visible par le délégué avant la fin"
          suffixe="minutes"
          min={1}
          max={60}
          aide="Idéalement alignée sur l'ouverture de la fenêtre de scan, ci-dessus."
          {...champ('qr_visible_delegue_avant_fin')}
        />
      </Section>

      <Section
        titre="Durée d'une séance"
        description="Plafond d'UN créneau, distinct du volume horaire total de l'EC : sans lui, un cours disposant d'heures restantes accepterait un créneau de plusieurs heures en une seule fois."
      >
        <Champ
          id="seance-duree"
          label="Durée maximale d'un créneau"
          suffixe="heures"
          min={0.5}
          max={12}
          step={0.5}
          aide="De 0,5 à 12 heures."
          {...champ('seance_duree_max_heures')}
        />
      </Section>

      <Section
        titre="Limites de débit du scan (anti-fraude)"
        description="Par étudiant : la règle anti-fraude elle-même. Par adresse IP : un filet anti-inondation, pas une règle de fraude : une salle ou un campus entier peuvent partager une seule adresse."
      >
        <Champ
          id="limite-etudiant"
          label="Scans par minute, par étudiant"
          min={1}
          max={20}
          aide="De 1 à 20. C'est la règle du CDC (3 par défaut) : ne la resserrer qu'avec prudence."
          {...champ('limite_scan_par_etudiant')}
        />
        <Champ
          id="limite-ip"
          label="Scans par minute, par adresse IP"
          min={1000}
          max={100000}
          aide="Au moins 1000 : en dessous, une salle derrière une IP partagée (réseau commun, CGNAT) se verrait refuser des scans légitimes : déjà constaté à 600."
          {...champ('limite_scan_par_ip')}
        />
      </Section>

      <div className="flex justify-end">
        <button
          type="submit"
          disabled={envoi}
          className="flex items-center gap-2 rounded-xl bg-gradient-to-br from-marque to-marque-fonce px-6 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-transform hover:scale-[0.99] active:scale-[0.98] disabled:opacity-60"
        >
          {envoi && <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />}
          Enregistrer
        </button>
      </div>
    </form>
  );
}
