<?php

/**
 * Prepare le jeu de donnees de LOAD-01 : un couple (etudiant, evenement, jeton)
 * DISTINCT par utilisateur virtuel.
 *
 * Pourquoi ce script existe
 * ─────────────────────────
 * Chaque scan reussi invalide son jeton, et la contrainte d'unicite
 * (etudiant_id, evenement_id) interdit un second scan du meme etudiant sur le
 * meme cours. Sans un couple distinct par utilisateur, une campagne a 500
 * utilisateurs mesure un scan reussi et 499 refus 410 : le chiffre obtenu
 * decrit le chemin de rejet, pas le parcours nominal.
 *
 * Usage
 * ─────
 *   cd backend
 *   php ../tests/load/preparer-charge.php --vus=500 > /tmp/charge.json
 *   k6 run -e JEU=/tmp/charge.json -e VUS=500 ../tests/load/scan.k6.js
 *
 * A relancer avant CHAQUE execution : les jetons sont a usage unique.
 *
 * Options
 *   --vus=N        nombre de couples a produire (defaut 500)
 *   --iterations=N couples par utilisateur (defaut 1) ; au-dela de 1, il faut
 *                  autant d'evenements par etudiant
 *   --garder       ne supprime pas le jeu precedent avant de regenerer
 */

require __DIR__ . '/../../backend/vendor/autoload.php';

$app = require __DIR__ . '/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\QrCode;
use App\Models\Salle;
use App\Models\Ue;
use App\Services\IdentifiantService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$options = getopt('', ['vus::', 'iterations::', 'garder', 'forcer']);
$vus = (int) ($options['vus'] ?? 500);
$iterations = max(1, (int) ($options['iterations'] ?? 1));
$total = $vus * $iterations;

/** Ecrit sur STDERR : STDOUT est reserve au JSON consomme par k6. */
$journal = fn (string $message) => fwrite(STDERR, $message . PHP_EOL);

$environnement = app()->environment();
if (in_array($environnement, ['production', 'prod'], true)) {
    $journal("REFUS : ce script cree des donnees de test. Environnement detecte : {$environnement}.");
    exit(1);
}

// Refus de la base de la suite PHPUnit.
//
// Ce script ecrit des lignes REELLES, hors de toute transaction : elles
// survivent a l'execution. Or la suite PHPUnit compte des lignes et cree ses
// propres fixtures — « annees_academiques.libelle » est d'ailleurs unique
// GLOBALEMENT et non par etablissement. Une seule campagne de charge lancee sur
// la base de test fait donc echouer la suite entiere, avec des messages qui ne
// renvoient jamais vers un test de charge.
//
// Constate a la dure : 149 tests en echec d'un coup, pour une annee academique
// oubliee.
$base = config('database.connections.' . config('database.default') . '.database');
if (!isset($options['forcer']) && str_contains((string) $base, '_test')) {
    $journal("REFUS : « {$base} » est la base de la suite PHPUnit.");
    $journal('');
    $journal('Ce script ecrit des lignes persistantes : elles casseraient la suite');
    $journal('(comptages faux, collisions sur des contraintes uniques globales).');
    $journal('');
    $journal('Viser une base de developpement ou de recette, par exemple :');
    $journal('  DB_DATABASE=presence_uac_charge php ../tests/load/preparer-charge.php --vus=500');
    $journal('');
    $journal('Passer --forcer pour outrepasser, en sachant ce que cela implique.');
    exit(1);
}

$journal("Preparation de {$total} couples ({$vus} utilisateurs x {$iterations} iterations)…");

$MARQUEUR = 'CHARGE-K6';

if (!isset($options['garder'])) {
    // Le jeu precedent est inutilisable — ses jetons sont consommes — et ses
    // presences faussent les taux du tableau de bord.
    $journal('Suppression du jeu precedent…');
    DB::transaction(function () use ($MARQUEUR) {
        $etabIds = Etablissement::where('code', 'like', $MARQUEUR . '%')->pluck('id');
        if ($etabIds->isEmpty()) {
            return;
        }
        $filiereIds   = Filiere::whereIn('etablissement_id', $etabIds)->pluck('id');
        $evenementIds = Evenement::whereIn('filiere_id', $filiereIds)->pluck('id');

        $ueIds       = Ue::whereIn('filiere_id', $filiereIds)->pluck('id');
        $etudiantIds = Etudiant::whereIn('filiere_id', $filiereIds)->pluck('id');

        DB::table('presences')->whereIn('evenement_id', $evenementIds)->delete();
        QrCode::whereIn('evenement_id', $evenementIds)->delete();
        Evenement::whereIn('id', $evenementIds)->delete();
        DB::table('etudiant_ec')->whereIn('etudiant_id', $etudiantIds)->delete();
        Etudiant::whereIn('id', $etudiantIds)->delete();
        Ec::whereIn('ue_id', $ueIds)->delete();
        Ue::whereIn('id', $ueIds)->delete();
        Salle::whereIn('etablissement_id', $etabIds)->delete();
        DB::table('filiere_annee')->whereIn('filiere_id', $filiereIds)->delete();
        Filiere::whereIn('id', $filiereIds)->delete();
        // Indispensable : « annees_academiques.libelle » est unique GLOBALEMENT,
        // pas par etablissement. Une annee de charge laissee derriere bloque
        // ensuite toute la suite PHPUnit, qui cree « 2025-2026 » dans ses
        // fixtures — l'erreur remonte alors comme 149 tests en echec, sans
        // aucun rapport apparent avec un test de charge.
        AnneeAcademique::whereIn('etablissement_id', $etabIds)->delete();
        Etablissement::whereIn('id', $etabIds)->delete();
    });
}

$sfx = Str::upper(Str::random(4));

[$etab, $annee, $filiere, $salle, $ue] = DB::transaction(function () use ($MARQUEUR, $sfx) {
    $etab = Etablissement::create([
        'code'  => $MARQUEUR . '-' . $sfx,
        'nom'   => 'Etablissement de charge ' . $sfx,
        'email' => 'charge-' . Str::lower($sfx) . '@k6.test',
        'actif' => true,
    ]);

    // Plage d'annees reservee a la charge (2090+), et non « 2025-2026 ».
    //
    // « annees_academiques.libelle » est unique GLOBALEMENT, pas par
    // etablissement : un libelle realiste entre en collision avec les fixtures
    // de la suite PHPUnit, qui creent « 2025-2026 ». Une annee de charge oubliee
    // en base fait alors echouer 149 tests d'un coup, sans qu'aucun message ne
    // renvoie vers un test de charge.
    //
    // Le format « AAAA-AAAA » est conserve : l'identifiant unique des etudiants
    // le reprend telle quelle (CDC 7.1.3).
    $annee = AnneeAcademique::create([
        'libelle'          => '2090-2091',
        'date_debut'       => '2090-10-01',
        'date_fin'         => '2091-09-30',
        'active'           => false,
        'etablissement_id' => $etab->id,
    ]);

    $filiere = Filiere::create([
        'code'             => 'CHG-' . $sfx,
        'intitule'         => 'Filiere de charge',
        'niveau'           => 'L3',
        'etablissement_id' => $etab->id,
    ]);
    $filiere->anneesAcademiques()->syncWithoutDetaching([$annee->id]);

    // Salle sans GPS ni Wi-Fi : le georeperage n'est pas l'objet de LOAD-01, et
    // une salle geolocalisee ferait echouer tous les scans faute de position
    // credible. La mesure porte sur le chemin d'ecriture.
    $salle = Salle::create([
        'etablissement_id' => $etab->id,
        'nom'              => 'Salle de charge',
        'code'             => 'SALLE-CHG-' . $sfx,
        'hors_reseau'      => true,
        'actif'            => true,
    ]);

    $ue = Ue::create([
        'code'           => 'UE-CHG-' . $sfx,
        'intitule'       => 'UE de charge',
        'filiere_id'     => $filiere->id,
        'annee_id'       => $annee->id,
        'semestre'       => 1,
        'volume_horaire' => 30,
    ]);

    return [$etab, $annee, $filiere, $salle, $ue];
});

// La fenetre de presence est ancree sur l'heure de FIN : [fin - 15, fin + 10].
// La fin est placee devant nous pour que la campagne entiere se deroule dedans.
$fin = Carbon::now()->addMinutes(9);
$appKey = (string) config('app.key');

$nominal = [];
$LOT = 200;

for ($debut = 0; $debut < $total; $debut += $LOT) {
    $taille = min($LOT, $total - $debut);

    DB::transaction(function () use ($debut, $taille, $ue, $filiere, $annee, $salle, $fin, $appKey, &$nominal, $sfx) {
        for ($i = $debut; $i < $debut + $taille; $i++) {
            // Un EC et un evenement par couple : c'est ce qui rend les scans
            // independants malgre la contrainte (etudiant_id, evenement_id).
            $ec = Ec::create([
                'ue_id'          => $ue->id,
                'code'           => "EC-CHG-{$sfx}-{$i}",
                'intitule'       => "EC de charge {$i}",
                'volume_horaire' => 20,
            ]);

            $evenement = Evenement::create([
                'ec_id'       => $ec->id,
                'filiere_id'  => $filiere->id,
                'annee_id'    => $annee->id,
                'date'        => $fin->toDateString(),
                'heure_debut' => $fin->copy()->subHours(2)->format('H:i:s'),
                'heure_fin'   => $fin->format('H:i:s'),
                'salle'       => $salle->nom,
                'salle_id'    => $salle->id,
                'statut'      => 'planifie',
            ]);

            $nom = "CHARGE{$i}";
            $prenom = "Prenom{$i}";
            $matricule = "MAT-CHG-{$sfx}-{$i}";

            $etudiant = Etudiant::create([
                'nom'                => $nom,
                'prenom'             => $prenom,
                'matricule'          => $matricule,
                'email'              => "charge{$i}-" . Str::lower($sfx) . '@k6.test',
                'filiere_id'         => $filiere->id,
                'annee_id'           => $annee->id,
                'identifiant_unique' => IdentifiantService::generate(
                    $nom, $prenom, $matricule, $filiere->id, $annee->id
                ),
            ]);

            // Inscription a l'EC : sans elle, l'etape 5 du scan refuse.
            $etudiant->ecs()->syncWithoutDetaching([
                $ec->id => ['annee_id' => $annee->id],
            ]);

            $token = (string) Str::uuid();
            QrCode::create([
                'evenement_id' => $evenement->id,
                'token'        => $token,
                'expire_at'    => $evenement->fermetureScan(),
                'actif'        => true,
            ]);

            $nominal[] = [
                'identifiant_unique' => $etudiant->identifiant_unique,
                'token'              => $token,
                'empreinte'          => 'k6-' . $sfx . '-' . $i,
                'latitude'           => null,
                'longitude'          => null,
            ];
        }
    });

    $journal(sprintf('  %d / %d couples', min($debut + $LOT, $total), $total));
}

// Jeu du chemin de rejet : des jetons volontairement inactifs. Le defi est
// calcule ici comme le serveur le fera — il n'est de toute facon pas atteint,
// le refus intervenant des la verification du jeton.
$rejet = [];
foreach (array_slice($nominal, 0, min(20, count($nominal))) as $couple) {
    $tokenMort = (string) Str::uuid();
    QrCode::create([
        'evenement_id' => Evenement::where('filiere_id', $filiere->id)->value('id'),
        'token'        => $tokenMort,
        'expire_at'    => Carbon::now()->subMinute(),
        'actif'        => false,
    ]);

    $rejet[] = [
        'identifiant_unique' => $couple['identifiant_unique'],
        'token'              => $tokenMort,
        'empreinte'          => $couple['empreinte'] . '-rejet',
        'defi'               => hash_hmac('sha256', $tokenMort, $appKey),
    ];
}

$journal(sprintf(
    'Pret. Fenetre de scan ouverte de %s a %s — lancer k6 maintenant.',
    Evenement::where('filiere_id', $filiere->id)->first()->ouvertureScan()->format('H:i:s'),
    $fin->copy()->addMinutes(10)->format('H:i:s'),
));

echo json_encode([
    'genere_le'  => Carbon::now()->toIso8601String(),
    'marqueur'   => $MARQUEUR . '-' . $sfx,
    'vus'        => $vus,
    'iterations' => $iterations,
    'nominal'    => $nominal,
    'rejet'      => $rejet,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
