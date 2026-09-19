// Mesure POST /presence/scan SEUL a N utilisateurs simultanes.
//
// Le scan est authentifie (jeton Bearer d'etudiant, emis par preparer-charge.php).
// La page est chargee pendant que l'etudiant se prepare, plusieurs secondes avant
// qu'il ne valide : enchainer la lecture du cours et le scan dans la meme
// iteration mesurerait une sequence que personne n'execute. Voir scan.k6.js pour
// le parcours complet.
import http from 'k6/http';
import { check } from 'k6';
import { Trend } from 'k6/metrics';

const JEU = JSON.parse(open(__ENV.JEU));
const BASE = __ENV.BASE_URL;
const VUS = Number(__ENV.VUS || 500);
const latence = new Trend('latence_scan', true);

export const options = {
  scenarios: {
    scan: { executor: 'per-vu-iterations', vus: VUS, iterations: 1, maxDuration: '3m' },
  },
  thresholds: { latence_scan: ['p(95)<500', 'p(99)<1000'] },
  summaryTrendStats: ['min', 'med', 'avg', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

// En-tetes communs. PROXY_HTTPS=1 imite le repartiteur de Render (ForceHttps
// redirige toute requete en clair en production). IP_DISTINCTES=1 donne a chaque
// utilisateur sa propre adresse (etudiants en donnees mobiles) ; sans lui, tous
// arrivent de la meme adresse — une salle ou un campus derriere un meme NAT.
function entetes(vu, extra = {}) {
  const h = { 'Content-Type': 'application/json', Accept: 'application/json', ...extra };
  if (__ENV.PROXY_HTTPS === '1') h['X-Forwarded-Proto'] = 'https';
  if (__ENV.IP_DISTINCTES === '1') h['X-Forwarded-For'] = `10.${(vu >> 8) & 255}.${vu & 255}.1`;
  return h;
}

export default function () {
  const c = JEU.nominal[(__VU - 1) % JEU.nominal.length];
  const r = http.post(`${BASE}/api/presence/scan`, JSON.stringify({
    token: c.token,
    device_fingerprint: c.empreinte,
  }), { headers: entetes(__VU, { Authorization: `Bearer ${c.bearer}` }) });

  latence.add(r.timings.duration);
  check(r, { 'scan accepte (201)': (x) => x.status === 201 });
}

export function handleSummary(d) {
  const v = d.metrics.latence_scan?.values ?? {};
  return { stdout: `
 POST /presence/scan seul — ${VUS} utilisateurs simultanes
   p50 ${(v.med ?? 0).toFixed(0)} ms | p95 ${(v['p(95)'] ?? 0).toFixed(0)} ms | p99 ${(v['p(99)'] ?? 0).toFixed(0)} ms | max ${(v.max ?? 0).toFixed(0)} ms
   requetes ${d.metrics.http_reqs?.values.count ?? 0} | echecs ${((d.metrics.http_req_failed?.values.rate ?? 0) * 100).toFixed(2)} %
` };
}
