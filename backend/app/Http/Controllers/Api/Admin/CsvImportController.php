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
        'jour' => 'jour',
        'heure debut' => 'heure_debut', 'heure_debut' => 'heure_debut', 'debut' => 'heure_debut',
        'heure fin' => 'heure_fin', 'heure_fin' => 'heure_fin', 'fin' => 'heure_fin',
        'salle' => 'salle_code', 'salle_code' => 'salle_code', 'code salle' => 'salle_code',
        'type cours' => 'type_cours', 'type_cours' => 'type_cours', 'type' => 'type_cours',
        'ue_code' => 'ue_code', 'code ue' => 'ue_code',
        'ec_code' => 'ec_code', 'code ec' => 'ec_code',
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

                // Résoudre filiere
                $filiere = Filiere::where('code', $ueData['filiere_code']);
                if (!empty($ueData['niveau'])) {
                    $filiere = $filiere->where('niveau', $ueData['niveau']);
                }
                $filiere = $filiere->first();

                if (!$filiere) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Filière '{$ueData['filiere_code']}' introuvable."];
                    continue;
                }

                // Vérifier le scope établissement
                if ($etablissementId && $filiere->etablissement_id !== (int) $etablissementId) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Filière '{$ueData['filiere_code']}' non autorisée pour votre établissement."];
                    continue;
                }

                // Résoudre année académique
                $annee = AnneeAcademique::where('libelle', $ueData['annee_libelle'])->first();
                if (!$annee) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Année académique '{$ueData['annee_libelle']}' introuvable."];
                    continue;
                }

                // Valider semestre
                $semestre = (int) ($ueData['semestre'] ?? 0);
                if ($semestre < 1 || $semestre > 6) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Semestre invalide : {$ueData['semestre']}."];
                    continue;
                }

                // Valider volume_horaire_ue
                $vhUe = (int) ($ueData['volume_horaire_ue'] ?? 0);
                if ($vhUe <= 0) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "volume_horaire_ue invalide : {$ueData['volume_horaire_ue']}."];
                    continue;
                }

                // Trouver ou créer l'UE
                $ue = Ue::where('code', $ueData['code_ue'])
                    ->where('filiere_id', $filiere->id)
                    ->where('annee_id', $annee->id)
                    ->first();

                if (!$ue) {
                    $ue = Ue::create([
                        'code'           => $ueData['code_ue'],
                        'intitule'       => $ueData['intitule_ue'] ?? $ueData['code_ue'],
                        'filiere_id'     => $filiere->id,
                        'annee_id'       => $annee->id,
                        'semestre'       => $semestre,
                        'volume_horaire' => $vhUe,
                    ]);
                } else {
                    // Mettre à jour si existante
                    $ue->update([
                        'intitule'       => $ueData['intitule_ue'] ?? $ue->intitule,
                        'semestre'       => $semestre,
                        'volume_horaire' => $vhUe,
                    ]);
                }

                // Traiter les ECs de cette UE
                if (!empty($group['ecs'])) {
                    foreach ($group['ecs'] as $ecRow) {
                        $ecLineNum = $lineNum; // On utilise la ligne de l'EC

                        if (empty($ecRow['code_ec'])) {
                            $results['errors'][] = ['line' => $ecLineNum, 'error' => "code_ec manquant pour l'UE '{$ueData['code_ue']}'."];
                            continue;
                        }

                        $vhEc = (int) ($ecRow['volume_horaire_ec'] ?? 0);
                        if ($vhEc <= 0) {
                            $results['errors'][] = ['line' => $ecLineNum, 'error' => "volume_horaire_ec invalide pour EC '{$ecRow['code_ec']}'."];
                            continue;
                        }

                        // Trouver ou créer l'EC
                        $ec = Ec::where('code', $ecRow['code_ec'])
                            ->where('ue_id', $ue->id)
                            ->first();

                        if (!$ec) {
                            Ec::create([
                                'ue_id'          => $ue->id,
                                'code'           => $ecRow['code_ec'],
                                'intitule'       => $ecRow['intitule_ec'] ?? $ecRow['code_ec'],
                                'volume_horaire' => $vhEc,
                            ]);
                        } else {
                            $ec->update([
                                'intitule'       => $ecRow['intitule_ec'] ?? $ec->intitule,
                                'volume_horaire' => $vhEc,
                            ]);
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
     * Import de l'emploi du temps via CSV avec détection de conflits.
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

        $results = ['success' => 0, 'errors' => [], 'warnings' => [], 'total' => count($rows)];
        $etablissementId = $this->getEtablissementId($request);

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $lineNum = $i + 2;

                // Valider les champs requis
                if (empty($row['filiere_code'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'filiere_code manquant.'];
                    continue;
                }
                if (empty($row['annee_libelle'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'annee_libelle manquant.'];
                    continue;
                }
                if (empty($row['ue_code'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'ue_code manquant.'];
                    continue;
                }
                if (empty($row['ec_code'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'ec_code manquant.'];
                    continue;
                }
                if (empty($row['jour'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'jour manquant.'];
                    continue;
                }
                if (empty($row['heure_debut']) || empty($row['heure_fin'])) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => 'heure_debut ou heure_fin manquant.'];
                    continue;
                }

                // Résoudre filiere
                $filiere = Filiere::where('code', $row['filiere_code']);
                if (!empty($row['niveau'])) {
                    $filiere = $filiere->where('niveau', $row['niveau']);
                }
                $filiere = $filiere->first();

                if (!$filiere) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Filière '{$row['filiere_code']}' introuvable."];
                    continue;
                }

                if ($etablissementId && $filiere->etablissement_id !== (int) $etablissementId) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Filière '{$row['filiere_code']}' non autorisée."];
                    continue;
                }

                // Résoudre année
                $annee = AnneeAcademique::where('libelle', $row['annee_libelle'])->first();
                if (!$annee) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Année '{$row['annee_libelle']}' introuvable."];
                    continue;
                }

                // Résoudre UE
                $ue = Ue::where('code', $row['ue_code'])
                    ->where('filiere_id', $filiere->id)
                    ->where('annee_id', $annee->id)
                    ->first();
                if (!$ue) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "UE '{$row['ue_code']}' introuvable pour la filière/année."];
                    continue;
                }

                // Résoudre EC
                $ec = Ec::where('code', $row['ec_code'])->where('ue_id', $ue->id)->first();
                if (!$ec) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "EC '{$row['ec_code']}' introuvable dans l'UE '{$row['ue_code']}'."];
                    continue;
                }

                // Résoudre jour
                $jourKey = mb_strtolower(trim($row['jour']));
                $jourSemaine = self::JOURS_MAPPING[$jourKey] ?? null;
                if ($jourSemaine === null) {
                    $results['errors'][] = ['line' => $lineNum, 'error' => "Jour invalide : '{$row['jour']}'. Utilisez lundi, mardi, etc."];
                    continue;
                }

                // Valider heures
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

                // Résoudre salle (optionnelle)
                $salleId = null;
                $salleLibelle = null;
                if (!empty($row['salle_code'])) {
                    $salle = Salle::where('code', $row['salle_code'])->orWhere('nom', $row['salle_code'])->first();
                    if ($salle) {
                        $salleId = $salle->id;
                        $salleLibelle = $salle->nom;
                    } else {
                        $salleLibelle = $row['salle_code'];
                        $results['warnings'][] = ['line' => $lineNum, 'warning' => "Salle '{$row['salle_code']}' introuvable. Le nom sera utilisé comme libellé."];
                    }
                }

                // --- DÉTECTION CONFLITS ---
                $conflicts = [];

                // Conflit salle : même salle, même jour, horaires qui se chevauchent
                if ($salleId) {
                    $salleConflict = EmploiDuTemps::where('salle_id', $salleId)
                        ->where('jour_semaine', $jourSemaine)
                        ->where(function ($q) use ($heureDebut, $heureFin) {
                            $q->whereBetween('heure_debut', [$heureDebut, $heureFin])
                              ->orWhereBetween('heure_fin', [$heureDebut, $heureFin])
                              ->orWhere(function ($q2) use ($heureDebut, $heureFin) {
                                  $q2->where('heure_debut', '<=', $heureDebut)
                                     ->where('heure_fin', '>=', $heureFin);
                              });
                        })
                        ->first();

                    if ($salleConflict) {
                        $dayName = EmploiDuTemps::JOURS[$jourSemaine] ?? $jourSemaine;
                        $conflicts[] = "Conflit de salle : '{$row['salle_code']}' déjà occupée {$dayName} de {$salleConflict->heure_debut} à {$salleConflict->heure_fin}.";
                    }
                }

                // Conflit EC : même EC, même jour, même créneau
                $ecConflict = EmploiDuTemps::where('ec_id', $ec->id)
                    ->where('jour_semaine', $jourSemaine)
                    ->where(function ($q) use ($heureDebut, $heureFin) {
                        $q->whereBetween('heure_debut', [$heureDebut, $heureFin])
                          ->orWhereBetween('heure_fin', [$heureDebut, $heureFin])
                          ->orWhere(function ($q2) use ($heureDebut, $heureFin) {
                              $q2->where('heure_debut', '<=', $heureDebut)
                                 ->where('heure_fin', '>=', $heureFin);
                          });
                    })
                    ->first();

                if ($ecConflict) {
                    $dayName = EmploiDuTemps::JOURS[$jourSemaine] ?? $jourSemaine;
                    $conflicts[] = "Conflit EC : '{$row['ec_code']}' déjà programmé {$dayName} de {$ecConflict->heure_debut} à {$ecConflict->heure_fin}.";
                }

                if (!empty($conflicts)) {
                    $results['errors'][] = [
                        'line' => $lineNum,
                        'error' => implode(' ', $conflicts),
                    ];
                    continue;
                }

                // Créer l'entrée emploi du temps
                EmploiDuTemps::create([
                    'ec_id'        => $ec->id,
                    'filiere_id'   => $filiere->id,
                    'annee_id'     => $annee->id,
                    'jour_semaine' => $jourSemaine,
                    'heure_debut'  => $heureDebut,
                    'heure_fin'    => $heureFin,
                    'salle_id'     => $salleId,
                    'salle_libelle'=> $salleLibelle,
                    'type_cours'   => $row['type_cours'] ?? null,
                ]);

                $results['success']++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Erreur lors de l\'import : ' . $e->getMessage(), 500);
        }

        return $this->successResponse(
            $results,
            "Import terminé : {$results['success']}/{$results['total']} créneau(x) importé(s)."
        );
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
        fputcsv($output, [
            'code_ue', 'intitule_ue', 'filiere_code', 'niveau', 'annee_libelle',
            'semestre', 'volume_horaire_ue', 'code_ec', 'intitule_ec', 'volume_horaire_ec',
        ]);
        fputcsv($output, [
            'UE-INFO-1', 'Informatique Fondamentale', 'MIAGE', 'M1', '2025-2026',
            '1', '60', 'EC-ALGO', 'Algorithmique', '30',
        ]);
        fputcsv($output, [
            'UE-INFO-1', 'Informatique Fondamentale', 'MIAGE', 'M1', '2025-2026',
            '1', '60', 'EC-PROG', 'Programmation', '30',
        ]);
        fputcsv($output, [
            'UE-MATH-1', 'Mathématiques', 'MIAGE', 'M1', '2025-2026',
            '1', '40', 'EC-ANALYSE', 'Analyse', '20',
        ]);
    }

    private function writeEdtTemplate($output): void
    {
        fputcsv($output, [
            'filiere_code', 'niveau', 'annee_libelle', 'semestre', 'ue_code', 'ec_code',
            'jour', 'heure_debut', 'heure_fin', 'salle_code', 'type_cours',
        ]);
        fputcsv($output, [
            'MIAGE', 'M1', '2025-2026', '1', 'UE-INFO-1', 'EC-ALGO',
            'Lundi', '08:00', '10:00', 'SA-001', 'CM',
        ]);
        fputcsv($output, [
            'MIAGE', 'M1', '2025-2026', '1', 'UE-INFO-1', 'EC-PROG',
            'Mardi', '10:00', '12:00', 'SA-001', 'TD',
        ]);
        fputcsv($output, [
            'MIAGE', 'M1', '2025-2026', '1', 'UE-MATH-1', 'EC-ANALYSE',
            'Mercredi', '14:00', '16:00', 'SA-002', 'TP',
        ]);
    }

    // ─────────────────────────────────────────────
    // UTILITAIRES
    // ─────────────────────────────────────────────

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
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }
}
