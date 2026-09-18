<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class Etudiant extends Model
{
    use HasFactory, HasUuids, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'nom',
        'prenom',
        'matricule',
        'filiere_id',
        'annee_id',
        'email',
        'identifiant_unique',
        'est_responsable',
    ];

    /**
     * Le hachage du code d'accès ne sort jamais du serveur : il n'a d'usage
     * que pour Hash::check au moment de la connexion. Sans ce masquage, le
     * moindre toArray() d'un étudiant — une ressource, un journal d'audit, une
     * réponse d'API — le publierait et offrirait une cible à casser hors ligne.
     */
    protected $hidden = [
        'code_acces',
    ];

    protected $casts = [
        'est_responsable' => 'boolean',
    ];

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_id');
    }

    /**
     * ECs auxquels l'étudiant est inscrit (CDC 7.2.3).
     * Table pivot : etudiant_ec
     */
    public function ecs(): BelongsToMany
    {
        return $this->belongsToMany(Ec::class, 'etudiant_ec')
            ->withPivot('annee_id')
            ->withTimestamps();
    }

    /** Groupes de TD et de TP (un de chaque au plus par année, pivot annee_id). */
    public function groupes(): BelongsToMany
    {
        return $this->belongsToMany(Groupe::class, 'etudiant_groupe')->withPivot(['annee_id', 'type'])->withTimestamps();
    }

    /**
     * Inscrit l'étudiant à tous les ECs de sa filière et année (CDC 7.2.3).
     */
    public function autoEnroll(): void
    {
        $ecIds = Ec::forFiliereAndYear($this->filiere_id, $this->annee_id)->modelKeys();

        if ($ecIds === []) {
            return;
        }

        // Un aller-retour pour lire l'état du pivot, puis un seul INSERT pour
        // toutes les inscriptions manquantes. La boucle précédente appelait
        // syncWithoutDetaching par EC, soit 2 requêtes chacun : ~25 allers-retours
        // pour une filière de 12 ECs, ce qui représentait l'essentiel des ~7 s
        // d'une inscription en production (base Supabase en Irlande,
        // application à Render/Oregon).
        $actuels = $this->ecs()->get(['ecs.id'])->keyBy('id');

        $manquants = array_values(array_filter($ecIds, fn ($id) => !$actuels->has($id)));

        if ($manquants !== []) {
            $this->ecs()->attach(array_fill_keys($manquants, ['annee_id' => $this->annee_id]));
        }

        // Un EC de l'année courante inscrit sous une autre année est recalé.
        // Les EC des années passées ne sont pas touchés : ce sont les
        // inscriptions dont les taux de ces années dépendent. La boucle les
        // recalait tous, et une promotion déplaçait tout l'historique de
        // l'étudiant vers sa nouvelle année.
        foreach ($actuels as $ec) {
            if (in_array($ec->id, $ecIds, true) && (int) $ec->pivot->annee_id !== (int) $this->annee_id) {
                $this->ecs()->updateExistingPivot($ec->id, ['annee_id' => $this->annee_id]);
            }
        }
    }

    /**
     * Recalcule les inscriptions de l'année courante de l'étudiant, après un
     * changement de filière ou d'année.
     *
     * Les inscriptions des autres années sont gardées : les présences
     * attendues de ces années en dépendent (AttendanceRateService joint
     * etudiant_ec sur l'année). Tout détacher, comme avant, faussait les taux
     * de l'année quittée à chaque promotion — ses présences restaient, ses
     * attendus disparaissaient.
     *
     * $anneeQuittee : année à effacer aussi, quand le changement d'année
     * corrige une erreur au lieu de marquer un passage.
     */
    public function recalculateEnrollments(?int $anneeQuittee = null): void
    {
        $annees = array_values(array_unique(array_filter([(int) $this->annee_id, (int) $anneeQuittee])));

        $this->ecs()->wherePivotIn('annee_id', $annees)->detach();
        $this->autoEnroll();

        // Groupes de TD et de TP : ceux d'une autre filière pour l'année en
        // cours, et tous ceux d'une année effacée. Un nouveau niveau, ce sont
        // de nouveaux groupes ; ceux d'une année quittée restent comme historique.
        \Illuminate\Support\Facades\DB::table('etudiant_groupe')
            ->where('etudiant_id', $this->id)
            ->where(fn ($q) => $q
                ->where(fn ($courante) => $courante->where('annee_id', $this->annee_id)
                    ->whereNotIn('groupe_id', fn ($g) => $g->select('id')->from('groupes')->where('filiere_id', $this->filiere_id)))
                ->when($anneeQuittee && (int) $anneeQuittee !== (int) $this->annee_id, fn ($q) => $q->orWhere('annee_id', $anneeQuittee)))
            ->delete();
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    /**
     * L'étudiant peut-il assister à cette séance ?
     *
     * Inscrit à l'EC de la séance pour son année ; à défaut de toute
     * inscription, rattaché à la filière de la séance. C'est la règle du scan,
     * partagée avec l'enregistrement manuel d'une présence.
     */
    /**
     * Étudiants qui peuvent assister à cette séance : la règle de
     * peutAssisterA(), en une seule requête pour toute une liste.
     */
    public function scopeAttendusA($query, Evenement $evenement)
    {
        // Séance d'un groupe : ses seuls membres.
        if ($evenement->groupe_id) {
            $query->whereExists(fn ($membre) => $membre->from('etudiant_groupe')
                ->whereColumn('etudiant_groupe.etudiant_id', 'etudiants.id')
                ->where('etudiant_groupe.groupe_id', $evenement->groupe_id));
        }

        return $query->where(fn ($q) => $q
            ->whereExists(fn ($inscription) => $inscription->from('etudiant_ec')
                ->whereColumn('etudiant_ec.etudiant_id', 'etudiants.id')
                ->where('etudiant_ec.ec_id', $evenement->ec_id)
                ->whereColumn('etudiant_ec.annee_id', 'etudiants.annee_id'))
            ->orWhere(fn ($q) => $q
                ->whereIn('filiere_id', self::filieresDuCours($evenement))
                ->whereDoesntHave('ecs')));
    }

    /** Filières qui suivent le cours de la séance : toutes, pour un cours commun. */
    private static function filieresDuCours(Evenement $evenement): \Closure
    {
        return fn ($f) => $f->select('filiere_id')->from('ue_filiere')
            ->whereIn('ue_id', fn ($u) => $u->select('ue_id')->from('ecs')->where('id', $evenement->ec_id));
    }

    public function peutAssisterA(Evenement $evenement): bool
    {
        // Séance d'un groupe : il faut en être membre.
        if ($evenement->groupe_id && !\Illuminate\Support\Facades\DB::table('etudiant_groupe')
            ->where('etudiant_id', $this->id)->where('groupe_id', $evenement->groupe_id)->exists()) {
            return false;
        }

        // L'inscription a CET EC est verifiee en premier : dans le cas nominal
        // — l'etudiant est bien inscrit — une seule requete suffit.
        $inscritACetEc = $this->ecs()
            ->where('ec_id', $evenement->ec_id)
            ->wherePivot('annee_id', $this->annee_id)
            ->exists();

        if ($inscritACetEc) {
            return true;
        }

        // Deux situations a distinguer : l'etudiant a des inscriptions mais pas
        // a ce cours (refus), ou il n'en a aucune et l'on retombe alors sur son
        // rattachement de filiere.
        return !$this->ecs()->exists()
            && \Illuminate\Support\Facades\DB::table('ue_filiere')->where('filiere_id', $this->filiere_id)
                ->whereIn('ue_id', fn ($u) => $u->select('ue_id')->from('ecs')->where('id', $evenement->ec_id))
                ->exists();
    }
}
