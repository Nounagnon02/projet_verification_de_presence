<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Filiere;
use App\Models\Ue;
use App\Services\Maquette\RegistreMaquette;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Préparer une année à partir d'une autre : filières, maquette (UE et EC),
 * emploi du temps.
 *
 * Une faculté qui passait sur une année neuve la trouvait vide : sans UE ni
 * EC, pas d'inscription possible ; sans emploi du temps, aucune séance
 * générée. On recopie la structure de l'année source, pas ses étudiants — ils
 * y viennent par la promotion, qui les inscrit aux EC copiés.
 *
 * Une filière qui a déjà des UE dans l'année cible garde sa maquette : la
 * fusionner fabriquerait une double maquette. Son emploi du temps peut en
 * revanche être complété, les EC se retrouvant par leurs codes.
 */
class PreparationAnnee
{
    public function __construct(private readonly RegistreMaquette $maquette) {}

    /**
     * Filières de l'établissement présentes dans la source, avec ce que la
     * préparation copierait et ce que la cible contient déjà.
     *
     * @return list<array<string, mixed>>
     */
    public function apercu(AnneeAcademique $source, AnneeAcademique $cible, int $etablissementId): array
    {
        $filieres = $this->filieres($source, $etablissementId);
        $ids = $filieres->pluck('id');

        $dansSource = $this->decomptes($source->id, $ids);
        $dansCible = $this->decomptes($cible->id, $ids);
        $rattachees = DB::table('filiere_annee')->where('annee_id', $cible->id)->whereIn('filiere_id', $ids)->pluck('filiere_id')->flip();

        return $filieres->map(fn (Filiere $f) => [
            'id'            => $f->id,
            'code'          => $f->code,
            'intitule'      => $f->intitule,
            'niveau'        => $f->niveau,
            'source'        => $this->ligne($dansSource, $f->id),
            'cible'         => $this->ligne($dansCible, $f->id),
            'rattachee'     => $rattachees->has($f->id),
            'deja_preparee' => ($dansCible['ues'][$f->id] ?? 0) > 0,
        ])->values()->all();
    }

    /**
     * Prépare les filières désignées. Relancer ne crée aucun doublon.
     *
     * @param  list<int>  $filiereIds  filières de l'établissement présentes dans la source
     * @return list<array{code: string, ues: int, ecs: int, creneaux: int, maquette_gardee: bool, edt_garde: bool}>
     */
    public function preparer(AnneeAcademique $source, AnneeAcademique $cible, int $etablissementId, array $filiereIds, bool $avecEdt): array
    {
        return DB::transaction(function () use ($source, $cible, $etablissementId, $filiereIds, $avecEdt) {
            $bilan = [];

            foreach ($this->filieres($source, $etablissementId)->whereIn('id', $filiereIds) as $filiere) {
                $filiere->anneesAcademiques()->syncWithoutDetaching([$cible->id]);

                $ligne = ['code' => $filiere->code, 'ues' => 0, 'ecs' => 0, 'creneaux' => 0, 'maquette_gardee' => false, 'edt_garde' => false, 'ignorees' => []];

                if (Ue::where('filiere_id', $filiere->id)->where('annee_id', $cible->id)->exists()) {
                    $ligne['maquette_gardee'] = true;
                } else {
                    [$ligne['ues'], $ligne['ecs'], $ligne['ignorees']] = $this->copierMaquette($filiere, $source, $cible);
                }

                if ($avecEdt) {
                    if (EmploiDuTemps::where('filiere_id', $filiere->id)->where('annee_id', $cible->id)->exists()) {
                        $ligne['edt_garde'] = true;
                    } else {
                        $ligne['creneaux'] = $this->copierEmploiDuTemps($filiere, $source, $cible);
                    }
                }

                $bilan[] = $ligne;
            }

            return $bilan;
        });
    }

    /** Filières de l'établissement dans la source : même définition que la reconduction. */
    public function filieres(AnneeAcademique $source, int $etablissementId): Collection
    {
        return Filiere::forAnnee($source->id)
            ->where('etablissement_id', $etablissementId)
            ->orderBy('code')
            ->get(['id', 'code', 'intitule', 'niveau', 'etablissement_id']);
    }

    /** @return array{0: int, 1: int, 2: list<string>} UE et EC créés, et ce qui a été ignoré */
    private function copierMaquette(Filiere $filiere, AnneeAcademique $source, AnneeAcademique $cible): array
    {
        $ues = 0;
        $ecs = 0;
        $ignorees = [];

        foreach (Ue::where('filiere_id', $filiere->id)->where('annee_id', $source->id)->orderBy('semestre')->orderBy('code')->get() as $ue) {
            // Un code d'UE n'est porté qu'une fois par année : si une autre
            // filière l'a déjà pris dans l'année cible, on ne copie pas.
            if ($motif = $this->maquette->conflitUe($ue->code, $filiere, $cible->id)) {
                $ignorees[] = "UE {$ue->code} non copiée : {$motif}";
                continue;
            }

            // Sans relation chargée : l'observateur de création d'UE lit $ue->ecs,
            // et une copie qui porterait les EC de la source y inscrirait des
            // étudiants.
            $copie = $ue->withoutRelations()->replicate();
            $copie->annee_id = $cible->id;
            $copie->save();
            // Un cours commun reste commun l'année suivante.
            $copie->filieres()->syncWithoutDetaching($ue->filieres()->pluck('filieres.id')->all());
            $ues++;

            foreach (Ec::where('ue_id', $ue->id)->orderBy('code')->get() as $ec) {
                if ($motif = $this->maquette->conflitEc($ec->code, $copie->code, $filiere, $cible->id)) {
                    $ignorees[] = "EC {$ec->code} non copié : {$motif}";
                    continue;
                }

                $copieEc = $ec->withoutRelations()->replicate();
                $copieEc->ue_id = $copie->id;
                $copieEc->save();
                $ecs++;
            }
        }

        return [$ues, $ecs, $ignorees];
    }

    /**
     * Les EC de la cible se retrouvent par leurs codes (UE, EC) : cela vaut pour
     * une maquette qu'on vient de copier comme pour une maquette déjà en place.
     */
    private function copierEmploiDuTemps(Filiere $filiere, AnneeAcademique $source, AnneeAcademique $cible): int
    {
        $parCodes = fn (int $anneeId) => Ec::query()
            ->join('ues', 'ues.id', '=', 'ecs.ue_id')
            ->where('ues.filiere_id', $filiere->id)
            ->where('ues.annee_id', $anneeId)
            ->get(['ecs.id', 'ecs.code as ec_code', 'ues.code as ue_code'])
            ->mapWithKeys(fn ($l) => ["{$l->ue_code}|{$l->ec_code}" => (int) $l->id]);

        $versCible = $parCodes($cible->id);
        $cleSource = $parCodes($source->id)->flip();
        $creneaux = 0;

        foreach (EmploiDuTemps::where('filiere_id', $filiere->id)->where('annee_id', $source->id)->get() as $creneau) {
            $ecCible = $versCible[$cleSource[$creneau->ec_id] ?? ''] ?? null;

            if (!$ecCible) {
                continue;
            }

            $copie = $creneau->withoutRelations()->replicate();
            $copie->ec_id = $ecCible;
            $copie->annee_id = $cible->id;
            $copie->save();
            $creneaux++;
        }

        return $creneaux;
    }

    /** @return array{ues: Collection, ecs: Collection, creneaux: Collection} décomptes par filière */
    private function decomptes(int $anneeId, Collection $filiereIds): array
    {
        return [
            'ues' => Ue::where('annee_id', $anneeId)->whereIn('filiere_id', $filiereIds)
                ->selectRaw('filiere_id, count(*) as n')->groupBy('filiere_id')->pluck('n', 'filiere_id'),
            'ecs' => Ec::join('ues', 'ues.id', '=', 'ecs.ue_id')->where('ues.annee_id', $anneeId)->whereIn('ues.filiere_id', $filiereIds)
                ->selectRaw('ues.filiere_id, count(*) as n')->groupBy('ues.filiere_id')->pluck('n', 'filiere_id'),
            'creneaux' => EmploiDuTemps::where('annee_id', $anneeId)->whereIn('filiere_id', $filiereIds)
                ->selectRaw('filiere_id, count(*) as n')->groupBy('filiere_id')->pluck('n', 'filiere_id'),
        ];
    }

    /** @return array{ues: int, ecs: int, creneaux: int} */
    private function ligne(array $decomptes, int $filiereId): array
    {
        return [
            'ues'      => (int) ($decomptes['ues'][$filiereId] ?? 0),
            'ecs'      => (int) ($decomptes['ecs'][$filiereId] ?? 0),
            'creneaux' => (int) ($decomptes['creneaux'][$filiereId] ?? 0),
        ];
    }
}
