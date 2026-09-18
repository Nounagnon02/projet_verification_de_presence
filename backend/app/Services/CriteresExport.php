<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Décrit les critères d'un export filtré, pour que le fichier le dise.
 *
 * Aucun export ne mentionnait ses filtres ni son périmètre : la liste d'une
 * filière sur une semaine pouvait passer pour l'historique complet. Une seule
 * description sert à tous les formats — en-tête du PDF et de l'Excel, nom du
 * fichier — pour qu'ils ne puissent pas se contredire.
 */
class CriteresExport
{
    private const STATUT_FICHIER = ['valide' => 'presents', 'suspect' => 'suspects', 'rejete' => 'rejetes', 'absent' => 'absents'];

    /**
     * @param  iterable<int>  $semestresConcernes  semestres des présences exportées
     * @return array{entite: string, semestres: ?string, lignes: array<int, array{0: string, 1: string}>, filtre: bool, fichier: array<int, string>}
     */
    public function decrire(Request $request, ?int $etablissementId, iterable $semestresConcernes = []): array
    {
        $lignes = [];
        $fichier = [];

        if ($request->filled('annee_id') && ($annee = AnneeAcademique::find($request->annee_id))) {
            $lignes[] = ['Année académique', $annee->libelle];
            $fichier[] = $annee->libelle;
        }

        if ($request->filled('filiere_id') && ($filiere = Filiere::find($request->filiere_id))) {
            $lignes[] = ['Filière', trim("{$filiere->code} — {$filiere->intitule}", ' —')];
            $fichier[] = $filiere->code;
        }

        if ($request->filled('niveau')) {
            $lignes[] = ['Niveau', (string) $request->niveau];
            $fichier[] = (string) $request->niveau;
        }

        // Le semestre n'est pas une ligne de filtre : il a sa propre ligne,
        // « Semestre(s) », qui dit aussi quels semestres couvre un export non filtré.
        if ($request->filled('semestre')) {
            $fichier[] = 'S' . $request->semestre;
        }

        if ($request->filled('ue_id') && ($ue = Ue::find($request->ue_id))) {
            $lignes[] = ['UE', trim("{$ue->code} — {$ue->intitule}", ' —')];
            $fichier[] = $ue->code;
        }

        if ($request->filled('ec_id') && ($ec = Ec::find($request->ec_id))) {
            $lignes[] = ['EC', trim("{$ec->code} — {$ec->intitule}", ' —')];
            $fichier[] = $ec->code;
        }

        $debut = $this->date($request->date_debut);
        $fin = $this->date($request->date_fin);

        if ($debut || $fin) {
            $lignes[] = ['Période', match (true) {
                $debut && $fin => "du {$debut->format('d/m/Y')} au {$fin->format('d/m/Y')}",
                (bool) $debut  => "à partir du {$debut->format('d/m/Y')}",
                default        => "jusqu'au {$fin->format('d/m/Y')}",
            }];
            $fichier[] = match (true) {
                $debut && $fin => "du-{$debut->format('Y-m-d')}_au-{$fin->format('Y-m-d')}",
                (bool) $debut  => "depuis-{$debut->format('Y-m-d')}",
                default        => "jusqu-au-{$fin->format('Y-m-d')}",
            };
        }

        if ($request->filled('statut')) {
            $lignes[] = ['Statut', Presence::LIBELLES_STATUT[$request->statut] ?? (string) $request->statut];
            $fichier[] = self::STATUT_FICHIER[$request->statut] ?? (string) $request->statut;
        }

        if ($request->filled('search')) {
            $lignes[] = ['Recherche', '« ' . trim((string) $request->search) . ' »'];
        }

        $semestres = $request->filled('semestre')
            ? ['S' . $request->semestre]
            : collect($semestresConcernes)->filter()->unique()->sort()->map(fn ($s) => 'S' . $s)->values()->all();

        $etablissement = $etablissementId ? Etablissement::find($etablissementId) : null;

        return [
            'entite'    => $etablissement ? trim("{$etablissement->code} — {$etablissement->nom}", ' —') : 'Toutes les entités',
            'semestres' => $semestres ? implode(', ', $semestres) : null,
            'lignes'    => $lignes,
            'filtre'    => $lignes !== [] || $request->filled('semestre'),
            'fichier'   => $fichier,
        ];
    }

    /**
     * Nom de fichier résumant les filtres principaux :
     * « historique_IM-L1_S1_du-2026-09-01_au-2026-09-14_export-2026-09-14.xlsx ».
     */
    public function nomFichier(string $prefixe, array $criteres, string $extension): string
    {
        $parties = array_filter(array_map(
            fn (string $partie) => trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', Str::ascii($partie)), '-'),
            $criteres['fichier']
        ));

        return $prefixe . '_' . implode('_', $parties ?: ['complet']) . '_export-' . now()->format('Y-m-d') . '.' . $extension;
    }

    private function date(mixed $valeur): ?Carbon
    {
        if (!$valeur) {
            return null;
        }

        try {
            return Carbon::parse($valeur);
        } catch (\Throwable) {
            return null;
        }
    }
}
