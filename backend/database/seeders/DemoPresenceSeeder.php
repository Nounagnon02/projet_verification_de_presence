<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\QrCode;
use App\Models\Salle;
use App\Models\Ue;
use App\Services\IdentifiantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Environnement de démonstration pour tester la vérification de présence.
 *
 * Crée un jeu de données cohérent et isolé (préfixe « DEMO ») : une filière,
 * ses UE/EC, une salle géolocalisée, un étudiant inscrit à une partie des EC,
 * et des cours du jour dont un « live » toujours actif au moment du seed.
 *
 * Idempotent : relancer le seeder ne duplique rien (clés naturelles).
 *
 * ⚠️ GÉOLOCALISATION : latitude/longitude ci-dessous doivent être la position
 * RÉELLE de la salle de test. Renseignez-les via les variables d'environnement
 * DEMO_LAT / DEMO_LNG, sinon les valeurs par défaut (campus UAC approximatif)
 * feront échouer le contrôle de géorepérage à 50 m.
 *
 * ⚠️ Ne JAMAIS lancer `migrate:fresh --seed` sur la base de production : cela
 * détruit toutes les tables. En prod, lancer uniquement :
 *     php artisan db:seed --class=DemoPresenceSeeder
 */
class DemoPresenceSeeder extends Seeder
{
    // Position de la salle de test — À REMPLACER par la position réelle.
    private const DEFAULT_LAT = 6.414900;   // Campus UAC / IFRI (placeholder)
    private const DEFAULT_LNG = 2.341700;
    private const RAYON_M     = 50;

    // Réseau Wi-Fi autorisé (détecté sur le poste de test).
    private const WIFI_SSID  = 'Ayo oluwa';
    private const WIFI_BSSID = 'AA:E9:A1:EF:E1:CF';

    public function run(): void
    {
        $lat = (float) env('DEMO_LAT', self::DEFAULT_LAT);
        $lng = (float) env('DEMO_LNG', self::DEFAULT_LNG);

        // 1. Établissement + année active (réutilisés).
        $etablissement = Etablissement::firstOrCreate(
            ['code' => 'IFRI'],
            ['nom' => 'Institut de Formation et de Recherche en Informatique', 'email' => 'contact@ifri.uac.bj', 'actif' => true]
        );

        $annee = AnneeAcademique::where('active', true)->first()
            ?? AnneeAcademique::firstOrCreate(
                ['libelle' => '2025-2026'],
                ['date_debut' => '2025-10-01', 'date_fin' => '2026-09-30', 'active' => true, 'etablissement_id' => $etablissement->id]
            );

        // 2. Filière de démonstration (niveau L1).
        $filiere = Filiere::firstOrCreate(
            ['code' => 'DEMO-IM-L1'],
            ['intitule' => 'Informatique et Multimédia — L1 (Démonstration)', 'niveau' => 'L1', 'etablissement_id' => $etablissement->id]
        );

        // 3. UE et EC (matières). Semestre 1.
        //    Structure : [code UE => [intitulé UE, [ [code EC, intitulé EC], ... ] ]]
        $catalogue = [
            'DEMO-UE-ALGO' => ['Algorithmique et Programmation', [
                ['DEMO-EC-ALGO', 'Algorithmique'],
                ['DEMO-EC-PROG', 'Programmation en C'],
            ]],
            'DEMO-UE-MATH' => ['Mathématiques pour l\'informatique', [
                ['DEMO-EC-ANAL', 'Analyse mathématique'],
                ['DEMO-EC-ALGB', 'Algèbre linéaire'],
            ]],
            'DEMO-UE-SYST' => ['Systèmes et Architecture', [
                ['DEMO-EC-ARCH', 'Architecture des ordinateurs'],
            ]],
        ];

        $ecs = []; // code EC => modèle
        foreach ($catalogue as $ueCode => [$ueIntitule, $ecList]) {
            $ue = Ue::firstOrCreate(
                ['code' => $ueCode],
                ['intitule' => $ueIntitule, 'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'semestre' => 1, 'volume_horaire' => 50]
            );
            foreach ($ecList as [$ecCode, $ecIntitule]) {
                $ecs[$ecCode] = Ec::firstOrCreate(
                    ['code' => $ecCode],
                    ['ue_id' => $ue->id, 'intitule' => $ecIntitule, 'volume_horaire' => 30, 'statut' => 'en_cours']
                );
            }
        }

        // 4. Salle de test géolocalisée + Wi-Fi.
        $salle = Salle::updateOrCreate(
            ['code' => 'DEMO-A101'],
            [
                'etablissement_id'  => $etablissement->id,
                'nom'               => 'Salle A101 — Bâtiment Informatique',
                'latitude'          => $lat,
                'longitude'         => $lng,
                'rayon_geofence_m'  => self::RAYON_M,
                'ssid_attendu'      => self::WIFI_SSID,
                'bssid_attendu'     => self::WIFI_BSSID,
                'hors_reseau'       => false,
                'actif'             => true,
            ]
        );

        // 5. Étudiant de démonstration.
        $matricule = 'DEMO-2025-001';
        $nom = 'ADJOVI';
        $prenom = 'Ange';
        $identifiant = IdentifiantService::generate($nom, $prenom, $matricule, $filiere->id, $annee->id);

        $etudiant = Etudiant::firstOrCreate(
            ['matricule' => $matricule],
            [
                'id'                 => (string) Str::uuid(),
                'nom'                => IdentifiantService::normalize($nom),
                'prenom'             => IdentifiantService::normalize($prenom),
                'filiere_id'         => $filiere->id,
                'annee_id'           => $annee->id,
                'email'              => 'ange.adjovi.demo@example.com',
                'identifiant_unique' => $identifiant,
            ]
        );

        // 6. Inscriptions pédagogiques : l'étudiant est inscrit à une PARTIE
        //    des EC seulement (pour illustrer inscrit / non inscrit).
        $inscrits = ['DEMO-EC-ALGO', 'DEMO-EC-PROG', 'DEMO-EC-ANAL'];
        foreach ($inscrits as $ecCode) {
            $etudiant->ecs()->syncWithoutDetaching([
                $ecs[$ecCode]->id => ['annee_id' => $annee->id],
            ]);
        }

        // 7. Cours du jour (événements). Toutes les heures sont en UTC (fuseau
        //    de l'application). Un cours « live » est ancré sur l'heure du seed
        //    pour être actif au moment du test.
        $today = today()->toDateString();

        $evenements = [];

        // Cours 1 — matinée (horaires fixes demandés).
        $evenements['cours1'] = $this->evenement($ecs['DEMO-EC-ALGO'], $filiere, $annee, $salle, $today, '07:00:00', '09:30:00', 'planifie');

        // Cours 2 — matinée/midi (horaires fixes demandés).
        $evenements['cours2'] = $this->evenement($ecs['DEMO-EC-PROG'], $filiere, $annee, $salle, $today, '09:00:00', '13:00:00', 'planifie');

        // Cours 3 — LIVE : fenêtre ouverte maintenant (−30 min → +6 h) pour
        //    garantir un événement scannable pendant la session de test.
        $debutLive = Carbon::now()->subMinutes(30)->format('H:i:s');
        $finLive   = Carbon::now()->addHours(6)->format('H:i:s');
        $evenements['live'] = $this->evenement($ecs['DEMO-EC-ANAL'], $filiere, $annee, $salle, $today, $debutLive, $finLive, 'en_cours');

        // QR Code actif pour le cours live (l'admin peut aussi en régénérer un
        // depuis l'interface au moment du test).
        QrCode::updateOrCreate(
            ['evenement_id' => $evenements['live']->id, 'actif' => true],
            ['token' => (string) Str::uuid(), 'expire_at' => Carbon::now()->addHours(6)]
        );

        // 8. Historique : un cours d'hier terminé où l'étudiant était présent
        //    (donne de la matière aux rapports et au taux de présence).
        $hier = today()->subDay()->toDateString();
        $evHier = $this->evenement($ecs['DEMO-EC-ALGO'], $filiere, $annee, $salle, $hier, '08:00:00', '10:00:00', 'termine');
        Presence::firstOrCreate(
            ['etudiant_id' => $etudiant->id, 'evenement_id' => $evHier->id],
            [
                'heure_scan'         => Carbon::parse($hier . ' 08:05:00'),
                'device_fingerprint' => 'demo-device-etudiant',
                'ip_address'         => '127.0.0.1',
                'statut'             => 'valide',
                'latitude'           => $lat,
                'longitude'          => $lng,
            ]
        );

        // 9. Récapitulatif (affiché à l'exécution du seeder).
        $this->command?->info('=== Environnement de démonstration prêt ===');
        $this->command?->info("Établissement : {$etablissement->nom} (#{$etablissement->id})");
        $this->command?->info("Année        : {$annee->libelle} (#{$annee->id})");
        $this->command?->info("Filière      : {$filiere->code} (#{$filiere->id})");
        $this->command?->info("Salle        : {$salle->nom} (#{$salle->id}) — GPS {$lat},{$lng} rayon {$salle->rayon_geofence_m}m — Wi-Fi " . self::WIFI_SSID);
        $this->command?->info("Étudiant     : {$etudiant->nom} {$etudiant->prenom} — matricule {$matricule}");
        $this->command?->info("Identifiant  : {$etudiant->identifiant_unique}");
        $this->command?->info("Email        : {$etudiant->email}");
        $this->command?->info("Cours live   : événement #{$evenements['live']->id} ({$debutLive} → {$finLive} UTC, statut en_cours)");
        $this->command?->info("Cours 1      : événement #{$evenements['cours1']->id} (07:00 → 09:30)");
        $this->command?->info("Cours 2      : événement #{$evenements['cours2']->id} (09:00 → 13:00)");
    }

    /**
     * Crée (ou retrouve) un événement pour un EC, une salle et un créneau.
     */
    private function evenement(Ec $ec, Filiere $filiere, AnneeAcademique $annee, Salle $salle, string $date, string $debut, string $fin, string $statut): Evenement
    {
        return Evenement::firstOrCreate(
            [
                'ec_id'       => $ec->id,
                'date'        => $date,
                'heure_debut' => $debut,
            ],
            [
                'filiere_id'  => $filiere->id,
                'annee_id'    => $annee->id,
                'heure_fin'   => $fin,
                'salle'       => $salle->nom,
                'salle_id'    => $salle->id,
                'statut'      => $statut,
            ]
        );
    }
}
