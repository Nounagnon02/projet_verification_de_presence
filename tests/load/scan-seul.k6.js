// Mesure POST /presence/scan SEUL a 500 utilisateurs simultanes.
// Le defi est pre-calcule hors mesure : dans le parcours reel, la page est
// chargee pendant que l'etudiant saisit son identifiant, plusieurs secondes
// avant qu'il ne valide. Enchainer les deux requetes dans la meme iteration
// mesurerait une sequence que personne n'execute.
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

// Le defi est obtenu au setup, hors de la fenetre mesuree.
export function setup() {
  const defis = {};
  for (let i = 0; i < VUS; i++) {
    const c = JEU.nominal[i % JEU.nominal.length];
    const r = http.get(`${BASE}/api/presence/course-by-token/${c.token}`);
    if (r.status === 200) defis[c.token] = r.json('data.scan_challenge');
  }
  return { defis };
}

export default function (donnees) {
  const c = JEU.nominal[(__VU - 1) % JEU.nominal.length];
  const r = http.post(`${BASE}/api/presence/scan`, JSON.stringify({
    identifiant_unique: c.identifiant_unique,
    token: c.token,
    device_fingerprint: c.empreinte,
    scan_challenge: donnees.defis[c.token],
  }), { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } });

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
