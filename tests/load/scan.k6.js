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
 * Le jeu de donnees doit etre regenere avant CHAQUE execution : les jetons sont
 * a usage unique, un jeu rejoue ne mesure que des 410.
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const CHEMIN_JEU = __ENV.JEU || '/tmp/charge.json';
const VUS = Number(__ENV.VUS || 500);

// Charge le jeu une seule fois, partage entre tous les utilisateurs virtuels.
// SharedArray serait preferable pour la memoire, mais impose une fonction :
// ici le fichier est lu au demarrage, hors de la phase mesuree.
const JEU = JSON.parse(open(CHEMIN_JEU));

const scansReussis = new Counter('scans_reussis');
const scansRefuses = new Counter('scans_refuses');
const tauxDeSucces = new Rate('taux_de_succes_nominal');
const latenceNominale = new Trend('latence_nominale', true);
const latenceRejet = new Trend('latence_rejet', true);

export const options = {
  scenarios: {
    // Montee progressive jusqu'a 500 utilisateurs, puis palier.
    nominal: {
      executor: 'ramping-vus',
      exec: 'scanNominal',
      startVUs: 0,
      stages: [
        { duration: '30s', target: Math.floor(VUS / 4) },
        { duration: '30s', target: Math.floor(VUS / 2) },
        { duration: '60s', target: VUS },
        { duration: '60s', target: VUS },
        { duration: '15s', target: 0 },
      ],
      gracefulRampDown: '10s',
      tags: { scenario: 'nominal' },
    },
    // Reference basse, a charge constante et modeste : sert de comparaison,
    // pas de mesure de capacite.
    rejet: {
      executor: 'constant-vus',
      exec: 'scanRejete',
      vus: 20,
      duration: '3m15s',
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

/**
 * Un couple distinct par utilisateur virtuel et par iteration.
 *
 * __VU commence a 1 ; l'index combine le numero d'utilisateur et celui de
 * l'iteration pour ne jamais rejouer un jeton deja consomme.
 */
function prochainCouple() {
  const index = ((__VU - 1) * 1000 + __ITER) % JEU.nominal.length;
  return JEU.nominal[index];
}

export function scanNominal() {
  const couple = prochainCouple();

  group('scan nominal', () => {
    // Le defi anti-fraude est emis par le serveur : le client doit d'abord lire
    // le cours associe au jeton. Cet aller-retour fait partie du parcours reel
    // et doit donc etre compte dans la mesure.
    const infos = http.get(
      `${BASE_URL}/api/presence/course-by-token/${couple.token}`,
      { tags: { name: 'course-by-token' } },
    );

    const defi = infos.status === 200 ? infos.json('data.scan_challenge') : null;

    const reponse = http.post(
      `${BASE_URL}/api/presence/scan`,
      JSON.stringify({
        identifiant_unique: couple.identifiant_unique,
        token: couple.token,
        device_fingerprint: couple.empreinte,
        scan_challenge: defi,
        latitude: couple.latitude,
        longitude: couple.longitude,
      }),
      {
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
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
      identifiant_unique: couple.identifiant_unique,
      token: couple.token,
      device_fingerprint: couple.empreinte,
      scan_challenge: couple.defi,
    }),
    {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
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
    ' des refus 410. Regenerer preparer-charge.php avant chaque execution.',
    '════════════════════════════════════════════════════════════',
    '',
  ].join('\n');

  return {
    stdout: rapport,
    'tests/load/resultats-load-01.json': JSON.stringify(donnees, null, 2),
  };
}
