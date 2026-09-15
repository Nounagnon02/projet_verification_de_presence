<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAiImportJob;
use App\Models\Analyse;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Ec;
use App\Models\Salle;
use App\Services\AiAnalysisService;
use App\Services\IdentifiantService;
use App\Services\RegleSeanceService;
use App\Traits\ScopedByEtablissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    use ScopedByEtablissement;

    public function __construct(
        protected AiAnalysisService $aiAnalysis
    ) {}

    /**
     * Importation des étudiants via CSV (US02).
     * Conforme CDC 7.2.
     *
     * POST /api/admin/import/students
     */
    public function students(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $file   = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle, 1000, ',');

        if (!$header || count($header) < 3) {
            fclose($handle);
            return $this->errorResponse('Le fichier CSV est invalide ou vide.', 422);
        }

        $header = array_map(fn ($h) => trim(mb_strtolower($h)), $header);

        // Vérifier que le CSV contient au moins une ligne de données
        $firstRow = fgetcsv($handle, 1000, ',');
        if ($firstRow === false || count($firstRow) < 2) {
            fclose($handle);
            return $this->errorResponse('Le fichier CSV ne contient aucune donnée.', 422);
        }

        // Réinjecter la première ligne dans le buffer pour le traitement
        // On utilise un fichier temporaire pour rejouer tout le contenu
        fclose($handle);
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle, 1000, ','); // relire le header
        $header = array_map(fn ($h) => trim(mb_strtolower($h)), $header);

        // Mapping des en-têtes CDC vers les champs internes
        $columnMap = [
            'nom' => 'nom',
            'prenom' => 'prenom',
            'matricule' => 'matricule',
            'filiere' => 'filiere_code',
            'annee' => 'annee_libelle',
            'email' => 'email',
            'telephone' => 'telephone',
            'groupe td' => 'groupe_td', 'groupe_td' => 'groupe_td',
            'groupe tp' => 'groupe_tp', 'groupe_tp' => 'groupe_tp',
        ];

        $mapped = [];
        foreach ($header as $col) {
            if (isset($columnMap[$col])) {
                $mapped[] = $columnMap[$col];
            } else {
                $mapped[] = $col;
            }
        }
        $header = $mapped;

        $results = ['success' => 0, 'errors' => [], 'total' => 0, 'emails_echoues' => 0];

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $results['total']++;
            $data = array_combine($header, $row);

            $rowValidator = Validator::make($data, [
                'nom'           => ['required', 'string', 'max:100'],
                'prenom'        => ['required', 'string', 'max:100'],
                'matricule'     => ['required', 'string', 'unique:etudiants,matricule'],
                'filiere_code'  => ['required', 'string', 'exists:filieres,code'],
                'annee_libelle' => ['required', 'string', 'exists:annees_academiques,libelle'],
                'email'         => ['required', 'email', 'unique:etudiants,email'],
            ]);

            if ($rowValidator->fails()) {
                $results['errors'][] = [
                    'row'    => $data['matricule'] ?? 'N/A',
                    'errors' => $rowValidator->errors()->all(),
                ];
                continue;
            }

            // Le code n'est unique que dans un établissement : celui de l'admin
            // tranche. Sans ce filtre, un admin de faculté inscrivait des
            // étudiants dans la filière homonyme d'une autre faculté.
            $etablissementId = $this->getEtablissementId($request);
            $homonymes = Filiere::where('code', $data['filiere_code'])->get();
            $filiere = $etablissementId
                ? $homonymes->firstWhere('etablissement_id', $etablissementId)
                : ($homonymes->count() === 1 ? $homonymes->first() : null);

            if (!$filiere) {
                $results['errors'][] = [
                    'row'    => $data['matricule'] ?? 'N/A',
                    'errors' => [$etablissementId
                        ? "Filière '{$data['filiere_code']}' non autorisée pour votre établissement."
                        : "Filière '{$data['filiere_code']}' présente dans plusieurs établissements : importez depuis un compte de faculté."],
                ];
                continue;
            }

            $annee   = AnneeAcademique::where('libelle', $data['annee_libelle'])->first();

            if ($annee->estClosePour($etablissementId)) {
                $results['errors'][] = [
                    'row'    => $data['matricule'] ?? 'N/A',
                    'errors' => ["{$annee->libelle} est close pour votre établissement : ligne ignorée."],
                ];
                continue;
            }

            $etudiant = Etudiant::create([
                'id'                 => (string) Str::uuid(),
                'nom'                => IdentifiantService::normalize($data['nom']),
                'prenom'             => IdentifiantService::normalize($data['prenom']),
                'matricule'          => $data['matricule'],
                'filiere_id'         => $filiere->id,
                'annee_id'           => $annee->id,
                'email'              => $data['email'],
                'identifiant_unique' => IdentifiantService::generate(
                    $data['nom'], $data['prenom'], $data['matricule'],
                    $filiere->id, $annee->id
                ),
            ]);

            // Auto-inscription aux ECs de la filière et année (CDC 7.2.3)
            $etudiant->autoEnroll();

            // Groupes de TD et de TP, créés au besoin dans la promotion.
            foreach (['td' => 'groupe_td', 'tp' => 'groupe_tp'] as $type => $colonne) {
                if (trim((string) ($data[$colonne] ?? '')) !== '') {
                    $groupe = \App\Models\Groupe::firstOrCreate([
                        'filiere_id' => $filiere->id, 'annee_id' => $annee->id, 'type' => $type, 'libelle' => mb_strtoupper(trim($data[$colonne])),
                    ]);
                    app(\App\Services\Groupes\GestionGroupes::class)->affecter($etudiant, $groupe);
                }
            }

            // Envoi synchrone : il n'y a pas de worker de queue en production,
            // un dispatch() n'aurait jamais été traité. Un échec d'e-mail ne
            // doit pas interrompre l'import — l'étudiant reste créé.
            if (!$this->envoyerIdentifiant($etudiant)) {
                $results['emails_echoues']++;
            }

            $results['success']++;
        }

        fclose($handle);

        $message = "Importation terminée : {$results['success']}/{$results['total']} étudiant(s) importé(s).";
        if ($results['emails_echoues'] > 0) {
            $message .= " {$results['emails_echoues']} e-mail(s) d'identifiant n'ont pas pu être envoyés.";
        }

        return $this->successResponse($results, $message);
    }

    /**
     * Importation et analyse des cours (UEs/ECs) via PDF (Gemini IA) — ASYNCHRONE.
     * Conforme CDC 8.1 & 8.4 — l'analyse est déléguée à un job de queue.
     *
     * POST /api/admin/import/courses
     */
    public function courses(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $file = $request->file('file');

        // Vérification des magic bytes PDF (%PDF en début de fichier)
        $handle = fopen($file->getRealPath(), 'r');
        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic !== '%PDF') {
            return $this->errorResponse('Le fichier fourni n\'est pas un PDF valide.', 422);
        }

        // Disque par défaut (FILESYSTEM_DISK), et non « supabase » codé en dur :
        // la production le déclare déjà comme disque par défaut, et les tests
        // peuvent ainsi écrire en local au lieu d'exiger un stockage distant.
        $path    = $file->store('imports/courses');

        // Création de l'analyse en base (statut: pending)
        // On stocke le chemin RELATIF — le job utilise Storage::path() pour le résoudre
        $analyse = Analyse::create([
            'type'      => 'courses',
            'status'    => 'pending',
            'file_path' => $path,
            'user_id'   => Auth::id(),
        ]);

        // Dispatch du job asynchrone sur queue dédiée
        ProcessAiImportJob::dispatch($analyse)
            ->onQueue('ai-import');

        return $this->successResponse(
            ['analysis_id' => $analyse->id, 'status' => 'pending'],
            'Analyse des cours lancée en arrière-plan.'
        );
    }

    /**
     * Importation et analyse de l'emploi du temps via PDF (US03 - Gemini IA) — ASYNCHRONE.
     * Conforme CDC 8.1 & 8.2 — l'analyse est déléguée à un job de queue.
     *
     * POST /api/admin/import/schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'file' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $file = $request->file('file');

        // Vérification des magic bytes PDF (%PDF en début de fichier)
        $handle = fopen($file->getRealPath(), 'r');
        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic !== '%PDF') {
            return $this->errorResponse('Le fichier fourni n\'est pas un PDF valide.', 422);
        }

        // Disque par défaut (FILESYSTEM_DISK), et non « supabase » codé en dur :
        // la production le déclare déjà comme disque par défaut, et les tests
        // peuvent ainsi écrire en local au lieu d'exiger un stockage distant.
        $path    = $file->store('imports/schedule');

        // Création de l'analyse en base (statut: pending)
        // On stocke le chemin RELATIF — le job utilise Storage::path() pour le résoudre
        $analyse = Analyse::create([
            'type'      => 'schedule',
            'status'    => 'pending',
            'file_path' => $path,
            'user_id'   => Auth::id(),
        ]);

        // Dispatch du job asynchrone sur queue dédiée
        ProcessAiImportJob::dispatch($analyse)
            ->onQueue('ai-import');

        return $this->successResponse(
            ['analysis_id' => $analyse->id, 'status' => 'pending'],
            'Analyse de l\'emploi du temps lancée en arrière-plan.'
        );
    }

    /**
     * Validation et sauvegarde en masse des événements extraits.
     *
     * POST /api/admin/import/validate-events
     */
    public function validateEvents(Request $request, RegleSeanceService $volumes): JsonResponse
    {
        $validated = $request->validate([
            'events'            => 'required|array|min:1',
            'events.*.ec_id'    => 'required|exists:ecs,id',
            'events.*.filiere_id' => 'required|exists:filieres,id',
            'events.*.annee_id'  => 'required|exists:annees_academiques,id',
            'events.*.date'     => 'required|date',
            'events.*.heure_debut' => 'required|date_format:H:i',
            'events.*.heure_fin' => 'required|date_format:H:i|after:events.*.heure_debut',
            // La salle se désigne par son identifiant : un nom seul ne permet ni
            // le contrôle au scan ni la détection de double réservation.
            'events.*.salle_id' => 'nullable|integer',
            'events.*.type_cours' => 'nullable|string|max:40',
            'events.*.groupe_id'  => 'nullable|integer',
        ]);

        foreach (array_unique(array_column($validated['events'], 'annee_id')) as $anneeId) {
            $this->refuserSiAnneeClose((int) $anneeId, $request);
        }

        // Vérifier que les filières appartiennent à l'établissement de l'admin
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId) {
            $filiereIds = array_unique(array_column($validated['events'], 'filiere_id'));
            $validFilieres = Filiere::where('etablissement_id', $etablissementId)
                ->whereIn('id', $filiereIds)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($filiereIds, $validFilieres);
            if (!empty($invalidIds)) {
                return $this->errorResponse(
                    'Une ou plusieurs filières ne sont pas autorisées pour votre établissement.',
                    403
                );
            }
        }

        $created = [];
        $refuses = [];
        // Heures retenues au fil de cette validation : la base ne les reflète
        // pas encore quand on contrôle le créneau suivant du même EC.
        $consommeesParEc = [];

        foreach ($validated['events'] as $eventData) {
            $eventData['statut'] = 'planifie';
            $eventData['type_cours'] = \App\Support\TypeCours::normaliser($eventData['type_cours'] ?? null);

            // Une salle active de l'établissement de la filière, et elle seule.
            if (!empty($eventData['salle_id'])) {
                $salle = Salle::whereKey($eventData['salle_id'])
                    ->where('actif', true)
                    ->where('etablissement_id', Filiere::whereKey($eventData['filiere_id'])->value('etablissement_id'))
                    ->first();

                if (!$salle) {
                    $refuses[] = [
                        'date'  => $eventData['date'] ?? null,
                        'motif' => "La salle choisie n'existe pas dans l'établissement de la filière, ou elle est désactivée.",
                    ];
                    continue;
                }

                $eventData['salle'] = $salle->nom;
            }

            // Un créneau qui déborde du volume horaire de son EC est écarté,
            // et non créé silencieusement : la règle appliquée au formulaire
            // vaut aussi pour ce que propose l'analyse IA.
            $ec = isset($eventData['ec_id']) ? Ec::find($eventData['ec_id']) : null;
            $eventData['groupe_id'] ??= null;
            if ($ec) {
                try {
                    $eventData['groupe_id'] = app(\App\Services\Groupes\GestionGroupes::class)
                        ->verifierPourSeance($eventData['groupe_id'], $eventData['type_cours'], $ec)?->id;
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $refuses[] = ['date' => $eventData['date'] ?? null, 'motif' => collect($e->errors())->flatten()->first()];
                    continue;
                }

                // Salle, promotion et groupe : la règle commune aux séances.
                $conflits = app(\App\Services\Planning\Conflits::class)->pourSeance($ec, $eventData);
                if ($conflits !== []) {
                    $refuses[] = ['date' => $eventData['date'] ?? null, 'motif' => implode(' ', $conflits)];
                    continue;
                }

                $deja = $consommeesParEc[$ec->id] ?? [];
                $refus = $volumes->refus(
                    $ec, $eventData['date'], $eventData['heure_debut'], $eventData['heure_fin'], null, $deja, $eventData['type_cours'], $eventData['groupe_id']
                );
                if ($refus) {
                    $refuses[] = ['date' => $eventData['date'] ?? null, 'motif' => $refus];
                    continue;
                }
            }

            $created[] = \App\Models\Evenement::create($eventData);

            if ($ec) {
                $consommeesParEc[$ec->id][] = [
                    'type'      => $eventData['type_cours'],
                    'groupe_id' => $eventData['groupe_id'],
                    'heures'    => RegleSeanceService::duree($eventData['heure_debut'], $eventData['heure_fin']),
                ];
            }
        }

        $message = count($created) . ' événements créés avec succès.';
        if ($refuses !== []) {
            $message .= ' ' . count($refuses) . ' écarté(s), motif détaillé pour chacun.';
        }

        return $this->createdResponse([
            'total' => count($created),
            'events' => $created,
            'refuses' => $refuses,
        ], $message);
    }

    /**
     * Validation et sauvegarde en masse des cours (UEs + ECs) extraits.
     *
     * POST /api/admin/import/validate-courses
     */
    public function validateCourses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ues'              => 'required|array|min:1',
            // Plus d'unicite globale sur le code : il est unique DANS sa filiere
            // et son annee, ce que la contrainte de base exprime desormais. Une
            // maquette se reconduit d'une annee sur l'autre, le meme code doit
            // pouvoir reapparaitre. La reprise est traitee plus bas, en
            // creation-ou-mise-a-jour.
            'ues.*.code'       => 'required|string|max:20',
            'ues.*.intitule'   => 'required|string|max:255',
            'ues.*.filiere_id' => 'required|exists:filieres,id',
            'ues.*.annee_id'   => 'required|exists:annees_academiques,id',
            // 10 et non 6 : le Master occupe les semestres 7 a 10.
            'ues.*.semestre'   => 'required|integer|min:1|max:10',
            // Déduit des EC : il n'est plus exigé.
            'ues.*.volume_horaire' => 'nullable|integer|min:0',
            'ues.*.credits'    => 'nullable|integer|min:0|max:60',
            'ues.*.ecs'        => 'nullable|array',
            'ues.*.ecs.*.code' => 'required_with:ues.*.ecs|string|max:20',
            'ues.*.ecs.*.intitule' => 'required_with:ues.*.ecs|string|max:255',
            // Par type (CM, TD, TP, réserve TP/TD) ; à défaut, un total « à ventiler ».
            'ues.*.ecs.*.volume_horaire' => 'nullable|integer|min:1',
            'ues.*.ecs.*.volume_cm'    => 'nullable|integer|min:0|max:999',
            'ues.*.ecs.*.volume_td'    => 'nullable|integer|min:0|max:999',
            'ues.*.ecs.*.volume_tp'    => 'nullable|integer|min:0|max:999',
            'ues.*.ecs.*.volume_td_tp' => 'nullable|integer|min:0|max:999',
        ]);

        foreach (array_unique(array_column($validated['ues'], 'annee_id')) as $anneeId) {
            $this->refuserSiAnneeClose((int) $anneeId, $request);
        }

        // Vérifier que les filières appartiennent à l'établissement de l'admin
        $etablissementId = $this->getEtablissementId($request);
        if ($etablissementId) {
            $filiereIds = array_unique(array_column($validated['ues'], 'filiere_id'));
            $validFilieres = Filiere::where('etablissement_id', $etablissementId)
                ->whereIn('id', $filiereIds)
                ->pluck('id')
                ->toArray();

            $invalidIds = array_diff($filiereIds, $validFilieres);
            if (!empty($invalidIds)) {
                return $this->errorResponse(
                    'Une ou plusieurs filières ne sont pas autorisées pour votre établissement.',
                    403
                );
            }
        }

        // Le semestre determine le niveau : S3 est en L2. Accepter une UE de S3
        // dans une filiere de L1 produisait une maquette incoherente que plus
        // rien ne signalait ensuite — c'est ainsi que trente-huit lignes de S3
        // se sont retrouvees en IM-L1.
        $semesterService = app(\App\Services\SemesterService::class);
        $filieres = Filiere::whereIn('id', array_column($validated['ues'], 'filiere_id'))->get()->keyBy('id');

        foreach ($validated['ues'] as $i => $ueData) {
            $filiere = $filieres->get($ueData['filiere_id']);
            $attendus = $filiere ? $semesterService->getSemestersForNiveau((string) $filiere->niveau) : [];

            if ($attendus !== [] && !in_array((int) $ueData['semestre'], $attendus, true)) {
                $liste = implode(' ou ', array_map(fn ($n) => "S{$n}", $attendus));

                return $this->errorResponse(
                    "L'UE « {$ueData['code']} » est en S{$ueData['semestre']}, "
                    . "incompatible avec la filière {$filiere->code} ({$filiere->niveau}), qui couvre {$liste}. "
                    . 'Corrigez la filière ou le semestre avant de valider.',
                    422
                );
            }
        }

        // Les règles du formulaire (RegistreMaquette), vérifiées sur TOUT le lot
        // avant la moindre écriture : un lot refusé ne laisse rien derrière lui.
        // On écrivait UE par UE, sans transaction ; une erreur au milieu laissait
        // un import à moitié fait.
        $registre = app(\App\Services\Maquette\RegistreMaquette::class);
        $refus = [];
        // Volumes d'un EC : par type s'il en a, sinon son total (« à ventiler »).
        $volumesEc = function (array $ec): array {
            $parType = array_map('intval', \Illuminate\Support\Arr::only($ec, ['volume_cm', 'volume_td', 'volume_tp', 'volume_td_tp']));

            return array_sum($parType) > 0 ? $parType : ['volume_horaire' => (int) ($ec['volume_horaire'] ?? 0)];
        };
        $ueDuLot = [];
        $ecDuLot = [];

        foreach ($validated['ues'] as $ueData) {
            $filiere = $filieres->get($ueData['filiere_id']);
            $anneeId = (int) $ueData['annee_id'];
            $prefixe = ((int) $filiere->etablissement_id) . '|' . $anneeId . '|';
            $cleUe = $prefixe . mb_strtolower($ueData['code']);

            // Le même code pour deux filières du lot : un cours commun s'il garde
            // le même intitulé, deux UE différentes sous un seul code sinon.
            $intitule = \App\Services\Maquette\RegistreMaquette::normaliser($ueData['intitule']);
            if (isset($ueDuLot[$cleUe]) && $ueDuLot[$cleUe] !== $intitule) {
                $refus[] = "Le code {$ueData['code']} désigne deux UE différentes dans ce lot : une UE ne porte qu'un code par année.";
            }
            $ueDuLot[$cleUe] ??= $intitule;

            if ($motif = $registre->conflitUe($ueData['code'], $filiere, $anneeId, $ueData['intitule'])) {
                $refus[] = $motif;
            }

            foreach ($ueData['ecs'] ?? [] as $ecData) {
                $cleEc = $prefixe . mb_strtolower($ecData['code']);

                if (isset($ecDuLot[$cleEc]) && $ecDuLot[$cleEc] !== $cleUe) {
                    $refus[] = "Le code d'EC {$ecData['code']} apparaît dans deux UE de ce lot.";
                }
                $ecDuLot[$cleEc] ??= $cleUe;

                if ($motif = $registre->conflitEc($ecData['code'], $ueData['code'], $filiere, $anneeId)) {
                    $refus[] = $motif;
                }

                if (array_sum($volumesEc($ecData)) <= 0) {
                    $refus[] = "L'EC {$ecData['code']} n'a aucun volume : renseignez ses heures de CM, TD, TP ou TP/TD.";
                }
            }
        }

        if ($refus !== []) {
            return $this->errorResponse(implode(' ', array_values(array_unique($refus))), 422);
        }

        $created = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $filieres, $registre, $volumesEc) {
            $created = [];

            foreach ($validated['ues'] as $ueData) {
                $ue = $registre->enregistrerUe(
                    $filieres->get($ueData['filiere_id']),
                    (int) $ueData['annee_id'],
                    \Illuminate\Support\Arr::only($ueData, ['code', 'intitule', 'semestre', 'volume_horaire', 'credits'])
                );

                $ecs = array_map(
                    fn (array $ecData) => $registre->enregistrerEc($ue, \Illuminate\Support\Arr::only($ecData, ['code', 'intitule']) + $volumesEc($ecData)),
                    $ueData['ecs'] ?? []
                );

                $ue->load('ecs');
                $created[] = ['ue' => $ue, 'ecs' => $ecs];
            }

            return $created;
        });

        return $this->createdResponse([
            'total_ues' => count($created),
            'ues' => $created,
        ], count($created) . ' UE(s) créée(s) avec succès.');
    }

    /**
     * Récupération du statut d'une analyse asynchrone.
     * Utilisé par le frontend pour le polling.
     *
     * GET /api/admin/import/analysis-status/{id}
     */
    public function analysisStatus(Request $request, mixed $id): JsonResponse
    {
        $id = is_numeric($id) ? (int) $id : 0;

        if ($id <= 0) {
            return $this->errorResponse('Identifiant d\'analyse invalide.', 400);
        }

        // Un administrateur ne peut suivre que les analyses qu'il a lui-même
        // lancées (l'analyse porte l'user_id de son initiateur).
        $analyse = Analyse::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $analyse) {
            return $this->errorResponse('Analyse introuvable.', 404);
        }

        return $this->successResponse([
            'analysis_id'        => $analyse->id,
            'type'               => $analyse->type,
            'status'             => $analyse->status,
            'score_de_confiance' => $analyse->score_de_confiance,
            'statut_analyse'     => $analyse->statut_analyse,
            'warning'            => $analyse->warning,
            'error_message'      => $analyse->error_message,
            'result'             => $analyse->status === 'completed' ? $analyse->result : null,
            'created_at'         => $analyse->created_at,
            'updated_at'         => $analyse->updated_at,
        ]);
    }

    /**
     * Envoie l'identifiant unique à l'étudiant de façon synchrone.
     *
     * Même logique que StudentController::store : pas de worker de queue en
     * production. Renvoie false si l'envoi échoue, sans lever d'exception
     * pour ne pas interrompre l'import.
     */
    private function envoyerIdentifiant(Etudiant $etudiant): bool
    {
        try {
            \Illuminate\Support\Facades\Mail::send('emails.identifiant', [
                'nom'         => $etudiant->nom,
                'prenom'      => $etudiant->prenom,
                'identifiant' => $etudiant->identifiant_unique,
                'filiere'     => $etudiant->filiere->intitule,
                'annee'       => $etudiant->anneeAcademique->libelle,
            ], function ($message) use ($etudiant) {
                $message->to($etudiant->email)
                    ->subject('Votre identifiant unique - Système de présence UAC');
            });

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                "Erreur envoi email import étudiant {$etudiant->matricule}: " . $e->getMessage()
            );

            return false;
        }
    }
}
