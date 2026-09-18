<?php

declare(strict_types=1);

namespace App\Services\Maquette;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Ue;
use App\Services\SemesterService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Règles d'écriture de la maquette (UE, EC), communes au formulaire, aux
 * imports CSV et IA et à la préparation d'une année.
 *
 * Un code d'UE, comme un code d'EC, est unique dans une année et un
 * établissement ; il se réutilise d'une année à l'autre. Chaque chemin avait sa
 * règle : le formulaire exigeait un code d'EC unique dans toute la base — un EC
 * reconduit devenait impossible à modifier —, les imports ne regardaient que la
 * filière, et laissaient deux UE de même code la même année.
 *
 * Un cours commun est UNE UE suivie par plusieurs filières d'un même niveau
 * (ue_filiere). Un import qui donne le même code et le même intitulé pour une
 * autre filière de ce niveau rattache cette filière ; un autre intitulé sous le
 * même code est une erreur.
 */
class RegistreMaquette
{
    public function __construct(private readonly SemesterService $semestres = new SemesterService()) {}

    /** Intitulé comparable : casse, accents et espaces ignorés. */
    public static function normaliser(?string $intitule): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', Str::ascii((string) $intitule))));
    }

    /** UE portant déjà ce code dans l'année, pour l'établissement de la filière. */
    public function ueDuCode(string $code, Filiere $filiere, int $anneeId, ?int $sauf = null): ?Ue
    {
        return Ue::with('filiere:id,code,niveau,etablissement_id')
            ->where('annee_id', $anneeId)
            ->whereRaw('coalesce(etablissement_id, 0) = ?', [(int) $filiere->etablissement_id])
            ->whereRaw('lower(code) = ?', [mb_strtolower(trim($code))])
            ->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))
            ->first();
    }

    /** EC portant déjà ce code dans l'année et l'établissement donnés. */
    public function ecDuCode(string $code, ?int $etablissementId, int $anneeId, ?int $sauf = null): ?Ec
    {
        return Ec::with('ue.filiere:id,code')
            ->where('annee_id', $anneeId)
            ->whereRaw('coalesce(etablissement_id, 0) = ?', [(int) $etablissementId])
            ->whereRaw('lower(code) = ?', [mb_strtolower(trim($code))])
            ->when($sauf, fn ($q) => $q->where('id', '!=', $sauf))
            ->first();
    }

    // ── Formulaire : tout autre porteur du code est un refus ─────────────

    public function verifierCodeUe(string $code, Filiere $filiere, int $anneeId, ?int $sauf = null): void
    {
        if ($autre = $this->ueDuCode($code, $filiere, $anneeId, $sauf)) {
            throw ValidationException::withMessages(['code' => $this->messageUe($code, $autre)]);
        }
    }

    public function verifierCodeEc(string $code, Ue $ue, ?int $sauf = null): void
    {
        if ($autre = $this->ecDuCode($code, $ue->etablissement_id, (int) $ue->annee_id, $sauf)) {
            throw ValidationException::withMessages(['code' => $this->messageEc($code, $autre)]);
        }
    }

    /** Le semestre découle du niveau : S3 est en L2. */
    public function verifierSemestre(int $semestre, Filiere $filiere): void
    {
        $attendus = $this->semestres->getSemestersForNiveau((string) $filiere->niveau);

        if ($attendus !== [] && !in_array($semestre, $attendus, true)) {
            throw ValidationException::withMessages([
                'semestre' => "Semestre S{$semestre} incompatible avec la filière {$filiere->code} ({$filiere->niveau}), qui couvre "
                    . implode(' ou ', array_map(fn ($n) => "S{$n}", $attendus)) . '.',
            ]);
        }
    }

    // ── Cours communs ──────────────────────────────────────────────────

    /**
     * Une filière de plus suit ce cours : même établissement et même niveau que
     * la porteuse — le semestre de l'UE vaut pour toutes. Ses étudiants de
     * l'année y sont inscrits.
     */
    public function rattacher(Ue $ue, Filiere $filiere): void
    {
        $porteuse = $ue->filiere ?? Filiere::findOrFail($ue->filiere_id);

        if ((int) $porteuse->etablissement_id !== (int) $filiere->etablissement_id || $porteuse->niveau !== $filiere->niveau) {
            throw ValidationException::withMessages([
                'filiere_ids' => "{$filiere->code} ne peut pas suivre {$ue->code} : un cours commun réunit des filières d'un même niveau ({$porteuse->niveau}) et d'un même établissement.",
            ]);
        }

        $ue->filieres()->syncWithoutDetaching([$filiere->id]);

        foreach (Etudiant::where('filiere_id', $filiere->id)->where('annee_id', $ue->annee_id)->get() as $etudiant) {
            $etudiant->autoEnroll();
        }
    }

    /** Filières qui suivent l'UE : la porteuse, et celles données. */
    public function synchroniserFilieres(Ue $ue, array $filiereIds): void
    {
        $voulues = collect($filiereIds)->map(fn ($id) => (int) $id)->push((int) $ue->filiere_id)->unique()->values();
        $actuelles = $ue->filieres()->pluck('filieres.id')->map(fn ($id) => (int) $id);

        foreach (Filiere::whereIn('id', $voulues->diff($actuelles))->get() as $filiere) {
            $this->rattacher($ue, $filiere);
        }

        $retirees = $actuelles->diff($voulues)->values();

        if ($retirees->isNotEmpty()) {
            $ue->filieres()->detach($retirees->all());

            // Leurs étudiants ne suivent plus ces EC cette année.
            DB::table('etudiant_ec')
                ->whereIn('ec_id', $ue->ecs()->pluck('id'))
                ->where('annee_id', $ue->annee_id)
                ->whereIn('etudiant_id', Etudiant::whereIn('filiere_id', $retirees)->select('id'))
                ->delete();
        }
    }

    // ── Imports et préparation : la même UE se complète, une autre refuse ──

    /**
     * Refus si le code est porté cette année-là par une AUTRE UE. C'est la même
     * UE si la filière la suit déjà, ou si l'intitulé et le niveau concordent :
     * un cours commun, que l'on rattache au lieu de le dupliquer.
     */
    public function conflitUe(string $code, Filiere $filiere, int $anneeId, ?string $intitule = null): ?string
    {
        $existante = $this->ueDuCode($code, $filiere, $anneeId);

        if (!$existante || $this->estSuiviePar($existante, $filiere) || $this->mutualisable($existante, $filiere, $intitule)) {
            return null;
        }

        return $this->messageUe($code, $existante)
            . ($existante->filiere?->niveau === $filiere->niveau
                ? ' Même code, autre intitulé : ce ne peut pas être le même cours.'
                : ' Un cours commun réunit des filières d\'un même niveau.');
    }

    /**
     * Refus si le code est porté cette année-là par l'EC d'une autre UE. Les
     * codes d'UE étant uniques dans l'année, un même code d'UE désigne la même.
     */
    public function conflitEc(string $code, string $codeUe, Filiere $filiere, int $anneeId): ?string
    {
        $existant = $this->ecDuCode($code, $filiere->etablissement_id, $anneeId);

        if (!$existant || mb_strtolower((string) $existant->ue?->code) === mb_strtolower(trim($codeUe))) {
            return null;
        }

        return $this->messageEc($code, $existant);
    }

    /**
     * Crée l'UE, ou complète celle de même code : de la même filière, ou d'une
     * filière du même niveau sous le même intitulé (cours commun, auquel cette
     * filière est alors rattachée). Une valeur vide ne remplace pas une valeur
     * existante.
     *
     * @param  array{code: string, intitule?: ?string, semestre: int, volume_horaire?: ?int, credits?: ?int}  $valeurs
     */
    public function enregistrerUe(Filiere $filiere, int $anneeId, array $valeurs): Ue
    {
        $this->verifierSemestre((int) $valeurs['semestre'], $filiere);

        if ($refus = $this->conflitUe($valeurs['code'], $filiere, $anneeId, $valeurs['intitule'] ?? null)) {
            throw ValidationException::withMessages(['code' => $refus]);
        }

        if ($existante = $this->ueDuCode($valeurs['code'], $filiere, $anneeId)) {
            if (!$this->estSuiviePar($existante, $filiere)) {
                $this->rattacher($existante, $filiere);
            }

            $existante->update($this->renseignees(Arr::except($valeurs, ['code'])));

            return $existante;
        }

        return Ue::create($this->renseignees($valeurs) + [
            'intitule'       => $valeurs['code'],
            'volume_horaire' => 0,
            'filiere_id'     => $filiere->id,
            'annee_id'       => $anneeId,
        ]);
    }

    /**
     * Crée l'EC, ou complète celui de même code dans la même UE.
     *
     * @param  array{code: string, intitule?: ?string, volume_horaire?: ?int}  $valeurs
     */
    public function enregistrerEc(Ue $ue, array $valeurs): Ec
    {
        $filiere = $ue->filiere ?? Filiere::findOrFail($ue->filiere_id);

        if ($refus = $this->conflitEc($valeurs['code'], $ue->code, $filiere, (int) $ue->annee_id)) {
            throw ValidationException::withMessages(['code' => $refus]);
        }

        if ($existant = $this->ecDuCode($valeurs['code'], $ue->etablissement_id, (int) $ue->annee_id)) {
            $existant->update($this->renseignees(Arr::except($valeurs, ['code'])));

            return $existant;
        }

        return Ec::create($this->renseignees($valeurs) + ['intitule' => $valeurs['code'], 'ue_id' => $ue->id]);
    }

    private function estSuiviePar(Ue $ue, Filiere $filiere): bool
    {
        return (int) $ue->filiere_id === (int) $filiere->id
            || $ue->filieres()->where('filieres.id', $filiere->id)->exists();
    }

    private function mutualisable(Ue $ue, Filiere $filiere, ?string $intitule): bool
    {
        $porteuse = $ue->filiere;

        return $intitule !== null
            && $porteuse !== null
            && $porteuse->niveau === $filiere->niveau
            && (int) $porteuse->etablissement_id === (int) $filiere->etablissement_id
            && self::normaliser($intitule) === self::normaliser($ue->intitule);
    }

    private function renseignees(array $valeurs): array
    {
        return array_filter($valeurs, fn ($v) => $v !== null && $v !== '');
    }

    private function messageUe(string $code, Ue $autre): string
    {
        $annee = AnneeAcademique::whereKey($autre->annee_id)->value('libelle');

        return "Le code {$code} est déjà utilisé en {$annee} par l'UE « {$autre->intitule} » de {$autre->filiere?->code}.";
    }

    private function messageEc(string $code, Ec $autre): string
    {
        $annee = AnneeAcademique::whereKey($autre->annee_id)->value('libelle');

        return "Le code {$code} est déjà utilisé en {$annee} par l'EC « {$autre->intitule} » (UE {$autre->ue?->code}, {$autre->ue?->filiere?->code}).";
    }
}
