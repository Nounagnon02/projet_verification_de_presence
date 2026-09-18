<?php

declare(strict_types=1);

namespace App\Services\Groupes;

use App\Models\Ec;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\Groupe;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Groupes de TD et de TP d'une promotion : répartir, affecter, et vérifier le
 * groupe qu'une séance vise.
 *
 * Sans groupes, chaque séance de TD attendait toute la filière : l'étudiant du
 * groupe B était compté absent au TD du groupe A.
 */
class GestionGroupes
{
    /** L'étudiant rejoint ce groupe ; il quitte son groupe du même type pour l'année. */
    public function affecter(Etudiant $etudiant, Groupe $groupe): void
    {
        if ((int) $groupe->filiere_id !== (int) $etudiant->filiere_id || (int) $groupe->annee_id !== (int) $etudiant->annee_id) {
            throw ValidationException::withMessages([
                'groupe_id' => "Le groupe {$groupe->libelle} n'est pas un groupe de la promotion de {$etudiant->prenom} {$etudiant->nom}.",
            ]);
        }

        DB::transaction(function () use ($etudiant, $groupe) {
            $this->retirer($etudiant, $groupe->type, (int) $groupe->annee_id);

            DB::table('etudiant_groupe')->insert([
                'etudiant_id' => $etudiant->id,
                'groupe_id'   => $groupe->id,
                'annee_id'    => $groupe->annee_id,
                'type'        => $groupe->type,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        });
    }

    public function retirer(Etudiant $etudiant, string $type, int $anneeId): void
    {
        DB::table('etudiant_groupe')
            ->where('etudiant_id', $etudiant->id)
            ->where('annee_id', $anneeId)
            ->where('type', $type)
            ->delete();
    }

    /**
     * Répartit les étudiants de la promotion en $nombre groupes, par ordre de
     * matricule, de même taille à une unité près. Les groupes G1…Gn sont créés
     * au besoin ; l'ancienne répartition de ce type, pour l'année, est remplacée.
     *
     * @return array<string, int> effectif de chaque groupe
     */
    public function repartir(Filiere $filiere, int $anneeId, string $type, int $nombre): array
    {
        return DB::transaction(function () use ($filiere, $anneeId, $type, $nombre) {
            $groupes = collect(range(1, $nombre))->map(fn (int $i) => Groupe::firstOrCreate([
                'filiere_id' => $filiere->id,
                'annee_id'   => $anneeId,
                'type'       => $type,
                'libelle'    => "G{$i}",
            ]));

            $etudiants = Etudiant::where('filiere_id', $filiere->id)->where('annee_id', $anneeId)->orderBy('matricule')->pluck('id');

            DB::table('etudiant_groupe')->whereIn('etudiant_id', $etudiants)->where('annee_id', $anneeId)->where('type', $type)->delete();

            // Les $reste premiers groupes prennent un étudiant de plus.
            $base = intdiv($etudiants->count(), $nombre);
            $reste = $etudiants->count() % $nombre;
            $effectifs = [];
            $lignes = [];
            $position = 0;

            foreach ($groupes as $i => $groupe) {
                $taille = $base + ($i < $reste ? 1 : 0);
                $effectifs[$groupe->libelle] = $taille;

                foreach ($etudiants->slice($position, $taille) as $etudiantId) {
                    $lignes[] = [
                        'etudiant_id' => $etudiantId,
                        'groupe_id'   => $groupe->id,
                        'annee_id'    => $anneeId,
                        'type'        => $type,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }

                $position += $taille;
            }

            if ($lignes !== []) {
                DB::table('etudiant_groupe')->insert($lignes);
            }

            return $effectifs;
        });
    }

    /**
     * Groupe visé par une séance ou un créneau : de TD ou de TP seulement, du
     * type de la séance, d'une filière qui suit le cours, et de l'année du cours.
     */
    public function verifierPourSeance(?int $groupeId, string $type, Ec $ec): ?Groupe
    {
        if (!$groupeId) {
            return null;
        }

        $groupe = Groupe::find($groupeId);
        $ue = $ec->ue;

        if (!$groupe || !$ue) {
            throw ValidationException::withMessages(['groupe_id' => 'Groupe introuvable.']);
        }

        if (!in_array($type, Groupe::TYPES, true)) {
            throw ValidationException::withMessages([
                'groupe_id' => 'Un groupe ne se donne qu\'à une séance de TD ou de TP : un cours magistral ou une évaluation réunit toute la promotion.',
            ]);
        }

        if ($groupe->type !== $type) {
            throw ValidationException::withMessages([
                'groupe_id' => "{$groupe->libelle} est un groupe de " . strtoupper($groupe->type) . ' : il ne suit pas une séance de ' . strtoupper($type) . '.',
            ]);
        }

        $filieres = $ue->filieres()->pluck('filieres.id')->map(fn ($id) => (int) $id);

        if (!$filieres->contains((int) $groupe->filiere_id) || (int) $groupe->annee_id !== (int) $ue->annee_id) {
            throw ValidationException::withMessages([
                'groupe_id' => "Le groupe {$groupe->libelle} n'appartient pas à une filière qui suit ce cours cette année.",
            ]);
        }

        return $groupe;
    }
}
