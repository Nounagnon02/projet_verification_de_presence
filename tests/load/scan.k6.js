import http from 'k6/http';
import { check, group } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

/**
 * LOAD-01 — POST /presence/scan sous montee a 500 utilisateurs simultanes.
 * Valide l'hypothese H3 du memoire : p95 < 500 ms a 500 utilisateurs.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PIEGE METHODOLOGIQUE, a lire avant d'interpreter le moindre chiffre
 * ─────────────────────────────────────────────────────────────────────────────
 * Chaque scan reussi INVALIDE son jeton de QR Code (anti-rejeu, CDC 9.2.1) et
 * la contrainte d'unicite (etudiant_id, evenement_id) interdit un second scan
 * du meme etudiant sur le meme cours.
 *
 * Un test de charge naif — 500 utilisateurs martelant un seul evenement avec un
 * seul jeton — ne mesure donc PAS le parcours nominal : le premier scan passe,
 * les 499 suivants repartent en 410 « jeton invalide ». On mesurerait la
 * latence du chemin de REJET, qui ne touche ni l'ecriture de presence, ni le
 * georeperage, ni la detection d'appareil partage. C'est plusieurs fois plus
 * rapide que le vrai parcours, et cela flatte le chiffre publie.
 *
 * D'ou la separation en deux scenarios, mesures et rapportes separement :
 *
 *   nominal — un couple (etudiant, evenement, jeton) DISTINCT par utilisateur,
 *             pre-genere par tests/load/preparer-charge.php. C'est le chiffre
 *             a publier pour H3.
 *   rejet   — un jeton deja consomme, volontairement. Sert de reference basse
 *             et prouve que le chemin de refus ne s'ecroule pas non plus.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PREPARATION
 * ─────────────────────────────────────────────────────────────────────────────
 *   cd backend && php ../tests/load/preparer-charge.php --vus=500 > /tmp/charge.json
 *   k6 run -e JEU=/tmp/charge.json -e BASE_URL=http://localhost:8000 tests/load/scan.k6.js
 *
 * Le jeu de donnees doit etre regenere avant CHAQUE execution : un etudiant ne
 * scanne qu'une fois un evenement, un jeu rejoue ne mesure que des refus.
 *
 * Le scan est AUTHENTIFIE : chaque couple porte le jeton Bearer de son etudiant.
 * Deux variables decrivent le reseau :
 *   PROXY_HTTPS=1     imite le repartiteur de Render (ForceHttps, en production)
 *   IP_DISTINCTES=1   une adresse par utilisateur (donnees mobiles) ; sans lui,
 *                     tous arrivent de la meme adresse — une salle ou un campus
 *                     derriere un meme NAT, le cas nominal du produit
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const CHEMIN_JEU = __ENV.JEU || '/tmp/charge.json';
const VUS = Number(__ENV.VUS || 500);

// Charge le jeu une seule fois, partage entre tous les utilisateurs virtuels.
// SharedArray serait preferable pour la memoire, mais impose une fonction :
// ici le fichier est lu au demarrage, hors de la phase mesuree.
const JEU = JSON.parse(open(CHEMIN_JEU));

function entetes(vu, extra = {}) {
  const h = { 'Content-Type': 'application/json', Accept: 'application/json', ...extra };
  if (__ENV.PROXY_HTTPS === '1') h['X-Forwarded-Proto'] = 'https';
  if (__ENV.IP_DISTINCTES === '1') h['X-Forwarded-For'] = `10.${(vu >> 8) & 255}.${vu & 255}.1`;
  return h;
}

const scansReussis = new Counter('scans_reussis');
const scansRefuses = new Counter('scans_refuses');
const tauxDeSucces = new Rate('taux_de_succes_nominal');
const latenceNominale = new Trend('latence_nominale', true);
const latenceRejet = new Trend('latence_rejet', true);

export const options = {
  scenarios: {
    // UNE seule iteration par utilisateur virtuel, et c'est essentiel.
    //
    // L'hypothese H3 porte sur « 500 utilisateurs simultanes » : 500 etudiants
    // qui scannent une fois chacun. Un executeur a iterations repetees rejoue
    // les memes couples — chaque jeton etant a usage unique, tout ce qui suit la
    // premiere passe repart en 410, et la mesure ne decrit plus que des refus.
    //
    // Constate a la premiere campagne : 3 363 iterations pour 500 couples, soit
    // 6,7 reutilisations par couple.
    nominal: {
      executor: 'per-vu-iterations',
      exec: 'scanNominal',
      vus: VUS,
      iterations: 1,
      maxDuration: '5m',
      tags: { scenario: 'nominal' },
    },
    // Reference basse : le chemin de refus, mesurable en boucle puisqu'un jeton
    // deja mort le reste.
    rejet: {
      executor: 'constant-vus',
      exec: 'scanRejete',
      vus: 20,
      duration: '60s',
      startTime: '10s',
      tags: { scenario: 'rejet' },
    },
  },

  thresholds: {
    // H3 du memoire. Le seuil porte sur le scenario NOMINAL seul : l'agreger
    // avec le chemin de rejet ferait baisser le p95 sans rien ameliorer.
    'latence_nominale': ['p(50)<500', 'p(95)<500', 'p(99)<1000'],
    'taux_de_succes_nominal': ['rate>0.99'],
    'http_req_failed{scenario:nominal}': ['rate<0.01'],
  },

  summaryTrendStats: ['min', 'med', 'avg', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

// SANS_REJET=1 : ne lance que le parcours nominal. Le scenario de rejet boucle a
// pleine vitesse pendant 60 s — des dizaines de milliers de requetes, presque
// toutes des 429 — et, a 500 utilisateurs, chevauche le parcours nominal au point
// de le perturber. Pour publier un chiffre H3, le lancer seul.
if (__ENV.SANS_REJET === '1') delete options.scenarios.rejet;

/**
 * Le couple attribue a cet utilisateur virtuel.
 *
 * __VU commence a 1. Avec une iteration par utilisateur, l'index est direct et
 * aucun jeton n'est jamais rejoue. Le modulo ne sert qu'au cas ou l'on
 * lancerait plus d'utilisateurs que le jeu n'en contient — le controle « jeton
 * non deja consomme » le signalerait alors.
 */
function prochainCouple() {
  return JEU.nominal[(__VU - 1) % JEU.nominal.length];
}

export function scanNominal() {
  const couple = prochainCouple();

  group('scan nominal', () => {
    // Le client lit d'abord le cours associe au jeton du QR Code (page de
    // confirmation). Cet aller-retour fait partie du parcours reel et doit donc
    // etre compte dans la mesure.
    const infos = http.get(
      `${BASE_URL}/api/presence/course-by-token/${couple.token}`,
      { headers: entetes(__VU), tags: { name: 'course-by-token' } },
    );

    const reponse = http.post(
      `${BASE_URL}/api/presence/scan`,
      JSON.stringify({
        token: couple.token,
        device_fingerprint: couple.empreinte,
        latitude: couple.latitude,
        longitude: couple.longitude,
      }),
      {
        headers: entetes(__VU, { Authorization: `Bearer ${couple.bearer}` }),
        tags: { name: 'scan' },
      },
    );

    const ok = reponse.status === 201;
    latenceNominale.add(infos.timings.duration + reponse.timings.duration);
    tauxDeSucces.add(ok);
    ok ? scansReussis.add(1) : scansRefuses.add(1);

    check(reponse, {
      'scan accepte (201)': (r) => r.status === 201,
      // Un 410 ici signale un jeu de donnees rejoue, pas un defaut du serveur.
      'jeton non deja consomme': (r) => r.status !== 410,
      'aucune erreur serveur': (r) => r.status < 500,
    });
  });
}

export function scanRejete() {
  const couple = JEU.rejet[(__VU - 1) % JEU.rejet.length];

  const reponse = http.post(
    `${BASE_URL}/api/presence/scan`,
    JSON.stringify({
      token: couple.token,
      device_fingerprint: couple.empreinte,
    }),
    {
      headers: entetes(__VU, { Authorization: `Bearer ${couple.bearer}` }),
      tags: { name: 'scan-rejet' },
    },
  );

  latenceRejet.add(reponse.timings.duration);

  check(reponse, {
    'refus attendu (4xx)': (r) => r.status >= 400 && r.status < 500,
    'aucune erreur serveur': (r) => r.status < 500,
  });
}

export function handleSummary(donnees) {
  const nominale = donnees.metrics.latence_nominale?.values ?? {};
  const rejet = donnees.metrics.latence_rejet?.values ?? {};

  const rapport = [
    '',
    '════════════════════════════════════════════════════════════',
    ` LOAD-01 — ${VUS} utilisateurs simultanes sur POST /presence/scan`,
    '════════════════════════════════════════════════════════════',
    '',
    ' PARCOURS NOMINAL (chiffre a publier pour H3)',
    `   p50 ......... ${(nominale['p(50)'] ?? nominale.med ?? 0).toFixed(0)} ms   (cible < 500)`,
    `   p95 ......... ${(nominale['p(95)'] ?? 0).toFixed(0)} ms   (cible < 500)`,
    `   p99 ......... ${(nominale['p(99)'] ?? 0).toFixed(0)} ms   (cible < 1000)`,
    `   scans OK .... ${donnees.metrics.scans_reussis?.values.count ?? 0}`,
    `   scans KO .... ${donnees.metrics.scans_refuses?.values.count ?? 0}`,
    '',
    ' CHEMIN DE REJET (reference basse — NE PAS publier comme H3)',
    `   p95 ......... ${(rejet['p(95)'] ?? 0).toFixed(0)} ms`,
    '',
    ' Rappel : un chiffre mesure sur un jeu de donnees rejoue ne decrit que',
    ' des refus. Regenerer preparer-charge.php avant chaque execution.',
    '════════════════════════════════════════════════════════════',
    '',
  ].join('\n');

  return {
    stdout: rapport,
    'resultats-load-01.json': JSON.stringify(donnees, null, 2),
  };
}
