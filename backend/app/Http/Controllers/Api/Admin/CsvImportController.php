<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Salle;
use App\Models\Ue;
use App\Traits\ScopedByEtablissement;
use App\Services\CorrespondanceSalles;
use App\Services\RegleSeanceService;
use App\Services\Planning\Conflits;
use App\Services\Schedule\ValidateurCreneau;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CsvImportController extends Controller
{
    use ScopedByEtablissement;

    /**
     * Mapping des noms de jours en français vers leur numéro (1-7).
     */
    private const JOURS_MAPPING = [
        'lundi'     => 1, 'mardi'    => 2, 'mercredi'  => 3,
        'jeudi'     => 4, 'vendredi' => 5, 'samedi'    => 6,
        'dimanche'  => 7,
        'lundis'    => 1, 'mardis'   => 2, 'mercredis' => 3,
        'jeudis'    => 4, 'vendredis'=> 5, 'samedis'   => 6,
        'dimanches' => 7,
    ];

    /**
     * Mapping des en-têtes CSV vers les en-têtes normalisés.
     * Permet de supporter plusieurs variantes de noms de colonnes.
     */
    private const HEADER_ALIASES = [
        'code ue' => 'code_ue', 'code_ue' => 'code_ue', 'codeue' => 'code_ue',
        'intitule ue' => 'intitule_ue', 'intitule_ue' => 'intitule_ue', 'libelle ue' => 'intitule_ue',
        'filiere' => 'filiere_code', 'filiere_code' => 'filiere_code', 'code filiere' => 'filiere_code',
        'annee' => 'annee_libelle', 'annee_libelle' => 'annee_libelle', 'annee academique' => 'annee_libelle',
        'semestre' => 'semestre',
        'volume horaire ue' => 'volume_horaire_ue', 'volume_horaire_ue' => 'volume_horaire_ue', 'vh ue' => 'volume_horaire_ue',
        'code ec' => 'code_ec', 'code_ec' => 'code_ec', 'codeec' => 'code_ec',
        'intitule ec' => 'intitule_ec', 'intitule_ec' => 'intitule_ec', 'libelle ec' => 'intitule_ec',
        'volume horaire ec' => 'volume_horaire_ec', 'volume_horaire_ec' => 'volume_horaire_ec', 'vh ec' => 'volume_horaire_ec',
        // Heures d'un EC par type, et crédits de l'UE : colonnes de la maquette.
        'volume cm' => 'volume_cm', 'volume_cm' => 'volume_cm', 'cm' => 'volume_cm', 'cours' => 'volume_cm',
        'volume td' => 'volume_td', 'volume_td' => 'volume_td', 'td' => 'volume_td',
        'volume tp' => 'volume_tp', 'volume_tp' => 'volume_tp', 'tp' => 'volume_tp',
        'volume tp/td' => 'volume_td_tp', 'volume td/tp' => 'volume_td_tp', 'volume_td_tp' => 'volume_td_tp', 'tp/td' => 'volume_td_tp', 'td/tp' => 'volume_td_tp',
        'credits' => 'credits_ue', 'crédits' => 'credits_ue', 'credits ue' => 'credits_ue', 'credits_ue' => 'credits_ue', 'cect' => 'credits_ue',
        'jour' => 'jour',
        'heure debut' => 'heure_debut', 'heure_debut' => 'heure_debut', 'debut' => 'heure_debut',
        'heure fin' => 'heure_fin', 'heure_fin' => 'heure_fin', 'fin' => 'heure_fin',
        'salle' => 'salle_code', 'salle_code' => 'salle_code', 'code salle' => 'salle_code',
        'type cours' => 'type_cours', 'type_cours' => 'type_cours', 'type' => 'type_cours',
        'groupe' => 'groupe', 'groupe td/tp' => 'groupe',
        'enseignant' => 'enseignant', 'enseignants' => 'enseignant', 'professeur' => 'enseignant',
        'valide du' => 'valide_du', 'valide_du' => 'valide_du', 'valable du' => 'valide_du',
        'valide au' => 'valide_au', 'valide_au' => 'valide_au', 'valable au' => 'valide_au',
        // « code ue » et « code ec » NE figurent pas ici : ils sont deja definis
        // plus haut vers code_ue et code_ec. Les redefinir vers ue_code et
        // ec_code faisait gagner la seconde definition — PHP conserve la
        // derniere valeur d'une cle repetee — si bien qu'un fichier a en-tetes
        // espacees, « Code UE », echouait entierement sur « code_ue manquant ».
        'ue_code' => 'ue_code',
        'ec_code' => 'ec_code',
        'niveau' => 'niveau',
    ];

    // ─────────────────────────────────────────────
    // IMPORT CSV UE/EC
    // ─────────────────────────────────────────────

    /**
     * Import des Unités d'Enseignement et Éléments Constitutifs via CSV.
     *
     * POST /api/admin/import/csv/courses
     */
    public function importCourses(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $file = $request->file('file');
        $rows = $this->parseCsv($file->getRealPath());

        if (empty($rows)) {
            return $this->errorResponse('Le fichier CSV est vide ou invalide.', 422);
        }

        $results = ['success' => 0, 'errors' => [], 'total' => count($rows)];
        $etablissementId = $this->getEtablissementId($request);
        $semesterService = app(\App\Services\SemesterService::class);
        $registre = app(\App\Services\Maquette\RegistreMaquette::class);

        DB::beginTransaction();
        try {
            // Grouper les lignes par UE (une UE peut avoir plusieurs ECs sur plusieurs lignes)
            $grouped = [];
            foreach ($rows as $i => $row) {
                $lineNum = $i + 2; // +2 car ligne 1 = header
                $key = $row['code_ue'] . '|' . ($row['filiere_code'] ?? '') . '|' . ($row['annee_libelle'] ?? '');
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'ue' => $row,
                        'ecs' => [],
                        'line_start' => $lineNum,
                    ];
                }
                if (!empty($row['code_ec'])) {
                    $grouped[$key]['ecs'][] = $row;
                }
            }

            foreach ($grouped as $key => $group) {
                $ueData = $group['ue'];
                $lineNum = $group['line_start'];

                // Valider les champs requis pour l'UE
                if (empty($ueData['code_ue'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'code_ue manquant.'];
                    continue;
                }
                if (empty($ueData['filiere_code'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'filiere_code manquant.'];
                    continue;
                }
                if (empty($ueData['annee_libelle'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'annee_libelle manquant.'];
                    continue;
                }

                [$filiere, $refus] = $this->resoudreFiliere(
                    $ueData['filiere_code'], $ueData['niveau'] ?? null, $etablissementId,
                    "Filière '{$ueData['filiere_code']}' non autorisée pour votre établissement."
                );

                if (!$filiere) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => $refus];
                    continue;
                }

                // Résoudre année académique
                $annee = AnneeAcademique::where('libelle', $ueData['annee_libelle'])->first();
                if (!$annee) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Année académique '{$ueData['annee_libelle']}' introuvable."];
                    continue;
                }
                if ($annee->estClosePour($etablissementId)) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "{$annee->libelle} est close pour votre établissement : ligne ignorée."];
                    continue;
                }

                // Valider semestre.
                //
                // La borne etait 6, alors que ues.semestre va jusqu'a 10 : aucune
                // UE de Master n'etait importable, sans que le message le dise.
                $semestre = (int) ($ueData['semestre'] ?? 0);

                if ($semestre < 1 || $semestre > 10) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Semestre invalide : {$ueData['semestre']}. Attendu entre 1 et 10."];
                    continue;
                }

                // Le semestre determine le niveau : S3 est en L2, quel que soit
                // ce que la colonne « niveau » pretend. Laisser passer une UE de
                // S3 dans une filiere de L1 produisait une maquette incoherente
                // que rien ne signalait ensuite.
                $semestresDuNiveau = $semesterService->getSemestersForNiveau((string) $filiere->niveau);

                if ($semestresDuNiveau !== [] && !in_array($semestre, $semestresDuNiveau, true)) {
                    $attendu = implode(' ou ', array_map(fn ($n) => "S{$n}", $semestresDuNiveau));
                    $results['errors'][] = [
                        'line'  => $lineNum,
                        'error' => "Semestre S{$semestre} incompatible avec la filière '{$filiere->code}' ({$filiere->niveau}), qui couvre {$attendu}.",
                    ];
                    continue;
                }

                // Le volume d'une UE est la somme de ses EC : l'ancienne colonne
                // volume_horaire_ue est facultative. Les crédits aussi.
                $vhUe = (int) ($ueData['volume_horaire_ue'] ?? 0);
                $credits = ($ueData['credits_ue'] ?? '') !== '' ? (int) $ueData['credits_ue'] : null;

                // Créer l'UE, ou compléter la même : les règles du formulaire
                // (RegistreMaquette). Un code porté la même année par une autre
                // filière de l'établissement est refusé ; les imports laissaient
                // deux UE de même code.
                try {
                    $ue = $registre->enregistrerUe($filiere, $annee->id, [
                        'code'           => $ueData['code_ue'],
                        'intitule'       => $ueData['intitule_ue'] ?? null,
                        'semestre'       => $semestre,
                        'volume_horaire' => $vhUe > 0 ? $vhUe : null,
                        'credits'        => $credits,
                    ]);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => collect($e->errors())->flatten()->first()];
                    continue;
                }

                // Traiter les ECs de cette UE
                if (!empty($group['ecs'])) {
                    foreach ($group['ecs'] as $ecRow) {
                        $ecLineNum = $lineNum; // On utilise la ligne de l'EC

                        if (empty($ecRow['code_ec'])) {
                            $results['errors'][] = ['line' => $ecLineNum, 'error' => "code_ec manquant pour l'UE '{$ueData['code_ue']}'."];
                            continue;
                        }

                        // Heures par type (CM, TD, TP, réserve TP/TD) ; à défaut,
                        // l'ancienne colonne volume_horaire_ec, et l'EC reste « à ventiler ».
                        $parType = [];
                        foreach (['volume_cm', 'volume_td', 'volume_tp', 'volume_td_tp'] as $colonne) {
                            $parType[$colonne] = max(0, (int) ($ecRow[$colonne] ?? 0));
                        }
                        $vhEc = (int) ($ecRow['volume_horaire_ec'] ?? 0);

                        if (array_sum($parType) === 0 && $vhEc <= 0) {
                            $results['errors'][] = ['line' => $ecLineNum, 'error' => "Aucun volume pour l'EC '{$ecRow['code_ec']}' : renseignez volume_cm, volume_td, volume_tp ou volume_td_tp."];
                            continue;
                        }

                        try {
                            $registre->enregistrerEc($ue, [
                                'code'     => $ecRow['code_ec'],
                                'intitule' => $ecRow['intitule_ec'] ?? null,
                            ] + (array_sum($parType) > 0 ? $parType : ['volume_horaire' => $vhEc]));
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            $results['errors'][] = ['line' => $ecLineNum, 'error' => collect($e->errors())->flatten()->first()];
                        }
                    }
                }

                $results['success']++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Erreur lors de l\'import : ' . $e->getMessage(), 500);
        }

        return $this->successResponse(
            $results,
            "Import terminé : {$results['success']}/{$results['total']} UE(s) importée(s)."
        );
    }

    // ─────────────────────────────────────────────
    // IMPORT CSV EMPLOI DU TEMPS
    // ─────────────────────────────────────────────

    /**
     * Import de l'emploi du temps via CSV.
     *
     * Les lignes sont lues et résolues ici (filière, année, UE, EC, jour,
     * heures, salle). Groupe, validité, doublons et conflits passent par
     * ValidateurCreneau, comme l'import IA et la grille : mêmes règles. Le
     * contrôle d'ici ne connaissait ni les cours communs, ni les groupes, ni
     * les cours simultanés d'une même promotion, et comparait toutes les
     * années entre elles.
     *
     * POST /api/admin/import/csv/schedule
     */
    public function importSchedule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $file = $request->file('file');
        $rows = $this->parseCsv($file->getRealPath());

        if (empty($rows)) {
            return $this->errorResponse('Le fichier CSV est vide ou invalide.', 422);
        }

        $results = ['success' => 0, 'errors' => [], 'warnings' => [], 'salles_creees' => [], 'total' => count($rows)];
        $etablissementId = $this->getEtablissementId($request);

        // Une reconnaissance de salles par établissement : un super admin peut
        // importer pour plusieurs facultés dans le même fichier.
        $correspondances = [];
        $sallesCreees = [];
        /** @var array<string, ValidateurCreneau> $validateurs  un par filière et année */
        $validateurs = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $lineNum = $i + 2;

                // Valider les champs requis
                foreach (['filiere_code', 'annee_libelle', 'ue_code', 'ec_code', 'jour'] as $requis) {
                    if (empty($row[$requis])) {
                        $results['errors'][] = ['line' => $lineNum, 'error' => "{$requis} manquant."];
                        continue 2;
                    }
                }
                if (empty($row['heure_debut']) || empty($row['heure_fin'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'heure_debut ou heure_fin manquant.'];
                    continue;
                }

                [$filiere, $refus] = $this->resoudreFiliere(
                    $row['filiere_code'], $row['niveau'] ?? null, $etablissementId,
                    "Filière '{$row['filiere_code']}' non autorisée."
                );

                if (!$filiere) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => $refus];
                    continue;
                }

                $annee = AnneeAcademique::where('libelle', $row['annee_libelle'])->first();
                if (!$annee) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Année '{$row['annee_libelle']}' introuvable."];
                    continue;
                }
                if ($annee->estClosePour($etablissementId)) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "{$annee->libelle} est close pour votre établissement : ligne ignorée."];
                    continue;
                }

                // L'UE que suit la filière cette année, cours communs compris :
                // on ne cherchait que parmi celles qu'elle porte.
                $ue = Ue::whereRaw('lower(code) = ?', [mb_strtolower(trim($row['ue_code']))])
                    ->where('annee_id', $annee->id)
                    ->whereHas('filieres', fn ($f) => $f->where('filieres.id', $filiere->id))
                    ->first();
                if (!$ue) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "UE '{$row['ue_code']}' introuvable pour la filière/année."];
                    continue;
                }

                $ec = Ec::whereRaw('lower(code) = ?', [mb_strtolower(trim($row['ec_code']))])->where('ue_id', $ue->id)->first();
                if (!$ec) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "EC '{$row['ec_code']}' introuvable dans l'UE '{$row['ue_code']}'."];
                    continue;
                }

                $jourSemaine = self::JOURS_MAPPING[mb_strtolower(trim($row['jour']))] ?? null;
                if ($jourSemaine === null) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Jour invalide : '{$row['jour']}'. Utilisez lundi, mardi, etc."];
                    continue;
                }

                $heureDebut = $row['heure_debut'];
                $heureFin = $row['heure_fin'];

                if (!preg_match('/^\d{2}:\d{2}$/', $heureDebut) || !preg_match('/^\d{2}:\d{2}$/', $heureFin)) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Format d'heure invalide. Utilisez HH:mm (ex: 08:00)."];
                    continue;
                }

                if ($heureDebut >= $heureFin) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "heure_fin ({$heureFin}) doit être après heure_debut ({$heureDebut})."];
                    continue;
                }

                // Version de l'emploi du temps : facultative.
                [$valideDu, $refusDu] = $this->dateCsv($row['valide_du'] ?? null, 'valide_du');
                [$valideAu, $refusAu] = $this->dateCsv($row['valide_au'] ?? null, 'valide_au');
                if ($refusDu || $refusAu) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => $refusDu ?? $refusAu];
                    continue;
                }

                // Salle (optionnelle). Reconnue parmi les salles de
                // l'établissement de la filière, sans tenir compte de la casse
                // ni des espaces, et créée si elle n'existe pas encore : le
                // fichier est écrit par l'administration, un nom inconnu y est
                // presque toujours une vraie salle pas encore déclarée. Une salle
                // créée pour des lignes toutes refusées est retirée à la fin.
                $salle = null;

                if (!empty($row['salle_code'])) {
                    if (!$filiere->etablissement_id) {
                        $results['warnings'][] = [
                            'line'    => $lineNum,
                            'warning' => "La filière '{$filiere->code}' n'est rattachée à aucun établissement : la salle '{$row['salle_code']}' est ignorée, le scan ne vérifiera que le QR code.",
                        ];
                    } else {
                        $correspondance = $correspondances[$filiere->etablissement_id]
                            ??= CorrespondanceSalles::pour($filiere->etablissement_id);

                        [$salle, $creee] = $correspondance->trouverOuCreer($row['salle_code']);

                        if (!$salle->actif) {
                            $results['errors'][] = [
                                'line'  => $lineNum,
                                'error' => "La salle '{$salle->nom}' est désactivée. Réactivez-la dans Paramètres > Salles, ou corrigez le fichier.",
                            ];
                            continue;
                        }

                        if ($creee) {
                            $sallesCreees[$salle->id] = $salle->nom;
                        }
                    }
                }

                // Les règles communes : groupe, validité, doublons, conflits de
                // salle, de promotion et d'enseignant.
                $cle = "{$filiere->id}|{$annee->id}";
                $validateur = $validateurs[$cle] ??= $this->validateurPour($filiere->id, $annee->id);

                if ($salle) {
                    $validateur->connaitreSalle($salle);
                }

                $verdict = $validateur->valider([
                    'ec_id'        => $ec->id,
                    'ec_code'      => $ec->code,
                    'jour_semaine' => $jourSemaine,
                    'heure_debut'  => $heureDebut,
                    'heure_fin'    => $heureFin,
                    'salle_id'     => $salle?->id,
                    'sans_salle'   => $salle === null,
                    'type_seance'  => $row['type_cours'] ?? null,
                    'groupe'       => $row['groupe'] ?? null,
                    'enseignants'  => Conflits::enseignants($row['enseignant'] ?? null),
                    'valide_du'    => $valideDu,
                    'valide_au'    => $valideAu,
                ]);

                if ($verdict['statut'] !== ValidateurCreneau::VALIDE) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => implode(' ', $verdict['motifs'])];
                    continue;
                }

                $c = $verdict['creneau'];
                $cree = EmploiDuTemps::create([
                    'ec_id'         => $ec->id,
                    'filiere_id'    => $filiere->id,
                    'annee_id'      => $annee->id,
                    'jour_semaine'  => $c['jour_semaine'],
                    'heure_debut'   => $c['heure_debut'],
                    'heure_fin'     => $c['heure_fin'],
                    'salle_id'      => $c['salle_id'],
                    'salle_libelle' => $c['salle_libelle'],
                    'type_cours'    => $c['type_cours'],
                    'groupe_id'     => $c['groupe_id'],
                    'enseignant'    => $c['enseignant'],
                    'valide_du'     => $c['valide_du'],
                    'valide_au'     => $c['valide_au'],
                ]);

                // Les autres filières du fichier le connaissent aussi : une salle
                // se partage entre elles.
                foreach ($validateurs as $autreCle => $autre) {
                    if ($autreCle !== $cle) {
                        $autre->connaitreCreneau($cree);
                    }
                }

                $results['success']++;
            }

            foreach (array_keys($sallesCreees) as $salleId) {
                if (!EmploiDuTemps::where('salle_id', $salleId)->exists()) {
                    Salle::whereKey($salleId)->delete();
                    unset($sallesCreees[$salleId]);
                }
            }

            $results['salles_creees'] = array_values($sallesCreees);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Erreur lors de l\'import : ' . $e->getMessage(), 500);
        }

        $message = "Import terminé : {$results['success']}/{$results['total']} créneau(x) importé(s).";

        // Une salle créée ne vérifie que le QR code : l'administration doit le
        // savoir pour relever ses coordonnées et son réseau.
        if ($results['salles_creees'] !== []) {
            $message .= ' Salles créées : ' . implode(', ', $results['salles_creees'])
                . ' — GPS et Wi-Fi à configurer dans Paramètres > Salles.';
        }

        return $this->successResponse($results, $message);
    }

    private function validateurPour(int $filiereId, int $anneeId): ValidateurCreneau
    {
        $validateur = new ValidateurCreneau();
        $validateur->preparer($filiereId, $anneeId);

        return $validateur;
    }

    /**
     * Date de validité d'une ligne : AAAA-MM-JJ ou JJ/MM/AAAA ; vide, sans borne.
     *
     * @return array{0: ?string, 1: ?string}  la date, ou le motif du refus
     */
    private function dateCsv(?string $valeur, string $colonne): array
    {
        $valeur = trim((string) $valeur);

        if ($valeur === '') {
            return [null, null];
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $valeur);

            if ($date && $date->format($format) === $valeur) {
                return [$date->format('Y-m-d'), null];
            }
        }

        return [null, "{$colonne} illisible : « {$valeur} ». Utilisez AAAA-MM-JJ ou JJ/MM/AAAA."];
    }

    // ─────────────────────────────────────────────
    // TÉLÉCHARGEMENT TEMPLATES CSV
    // ─────────────────────────────────────────────

    /**
     * Téléchargement d'un template CSV vierge.
     *
     * GET /api/admin/import/csv/template/{type}
     */
    public function downloadTemplate(string $type): mixed
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_' . $type . '.csv"',
        ];

        $callback = function () use ($type) {
            $output = fopen('php://output', 'w');
            fputs($output, "\xEF\xBB\xBF"); // BOM UTF-8

            match ($type) {
                'ue-ec' => $this->writeUeEcTemplate($output),
                'edt'   => $this->writeEdtTemplate($output),
                default => abort(404, 'Template introuvable. Types disponibles : ue-ec, edt.'),
            };

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function writeUeEcTemplate($output): void
    {
        // Heures en présentiel par type : jamais le TPE ni le CTT. volume_td_tp
        // reçoit la colonne « TP/TD » quand la maquette ne sépare pas les deux.
        fputcsv($output, [
            'code_ue', 'intitule_ue', 'filiere_code', 'niveau', 'annee_libelle', 'semestre', 'credits_ue',
            'code_ec', 'intitule_ec', 'volume_cm', 'volume_td', 'volume_tp', 'volume_td_tp',
        ]);
        fputcsv($output, ['INF1322', 'Approche orientée objet', 'IM-L2', 'L2', '2025-2026', '3', '6', '1INF1322', 'Analyse et conception orientée objet', '10', '0', '0', '15']);
        fputcsv($output, ['INF1322', 'Approche orientée objet', 'IM-L2', 'L2', '2025-2026', '3', '6', '2INF1322', 'Application avec les langages Java et C++', '20', '0', '0', '30']);
        fputcsv($output, ['MTH1321', 'Structures algébriques et leurs applications en informatique', 'IM-L2', 'L2', '2025-2026', '3', '5', '1MTH1321', 'Structures algébriques et leurs applications en informatique', '30', '20', '0', '0']);
    }

    private function writeEdtTemplate($output): void
    {
        // Le groupe vise un TD ou un TP ; la validité situe la version de
        // l'emploi du temps ; l'enseignant est gardé en texte.
        fputcsv($output, [
            'filiere_code', 'niveau', 'annee_libelle', 'semestre', 'ue_code', 'ec_code',
            'jour', 'heure_debut', 'heure_fin', 'salle_code', 'type_cours',
            'groupe', 'enseignant', 'valide_du', 'valide_au',
        ]);
        fputcsv($output, ['IM-L2', 'L2', '2025-2026', '3', 'INF1322', '1INF1322', 'Lundi', '08:00', '10:00', 'Amphi A', 'CM', '', 'HOUNDJI', '', '']);
        fputcsv($output, ['IM-L2', 'L2', '2025-2026', '3', 'INF1322', '2INF1322', 'Mardi', '10:00', '12:00', 'Salle TP1', 'TP', 'G1', '', '2026-03-02', '']);
        fputcsv($output, ['IM-L2', 'L2', '2025-2026', '3', 'MTH1321', '1MTH1321', 'Mercredi', '14:00', '16:00', 'Salle TD2', 'TD', 'G2', '', '', '']);
    }

    // ─────────────────────────────────────────────
    // UTILITAIRES
    // ─────────────────────────────────────────────

    /**
     * La filière désignée par son code, et par son niveau s'il est donné.
     *
     * Le code n'est unique que dans un établissement : celui de l'admin
     * tranche. Pour un super admin, un code partagé par plusieurs
     * établissements est ambigu, et refusé plutôt que deviné.
     *
     * @return array{0: ?Filiere, 1: ?string}  la filière, ou le motif du refus
     */
    private function resoudreFiliere(string $code, ?string $niveau, ?int $etablissementId, string $refusEtablissement): array
    {
        $candidates = Filiere::where('code', $code)
            ->when(!empty($niveau), fn ($q) => $q->where('niveau', $niveau))
            ->get();

        if ($candidates->isEmpty()) {
            return [null, "Filière '{$code}' introuvable."];
        }

        if ($etablissementId) {
            $filiere = $candidates->firstWhere('etablissement_id', $etablissementId);

            return $filiere ? [$filiere, null] : [null, $refusEtablissement];
        }

        return $candidates->count() === 1
            ? [$candidates->first(), null]
            : [null, "Filière '{$code}' présente dans plusieurs établissements : importez depuis un compte de faculté."];
    }

    /**
     * Parse un fichier CSV avec normalisation des en-têtes.
     */
    private function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        // Lire le header et normaliser
        $rawHeaders = fgetcsv($handle, 0, ',');
        if (!$rawHeaders || count($rawHeaders) < 2) {
            fclose($handle);
            return [];
        }

        $headers = array_map(function ($h) {
            $normalized = mb_strtolower(trim($h));
            return self::HEADER_ALIASES[$normalized] ?? $normalized;
        }, $rawHeaders);

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            $line = array_map('trim', $line);
            if (count($line) < 2) {
                continue; // ignorer les lignes vides
            }

            $row = [];
            foreach ($headers as $i => $header) {
                if (isset($line[$i])) {
                    $row[$header] = $line[$i];
                }
            }
            // « code ue » et « ue_code » designent la meme colonne. L'import des
            // UE lit code_ue, celui de l'emploi du temps lit ue_code : on
            // renseigne les deux a partir de celle qui est presente, faute de
            // quoi le fichier n'aurait fonctionne qu'avec l'orthographe attendue
            // par l'import visé — sans que rien ne l'indique a l'utilisateur.
            foreach ([['code_ue', 'ue_code'], ['code_ec', 'ec_code']] as [$a, $b]) {
                $valeur = $row[$a] ?? $row[$b] ?? null;

                if ($valeur !== null && $valeur !== '') {
                    $row[$a] = $valeur;
                    $row[$b] = $valeur;
                }
            }

            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }
}
