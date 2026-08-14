<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evenement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ec_id',
        'filiere_id',
        'annee_id',
        'date',
        'heure_debut',
        'heure_fin',
        'salle',
        'salle_id',
        'statut',
    ];

    protected $casts = [
        'date'        => 'date',
        'heure_debut'  => 'string',
        'heure_fin'   => 'string',
    ];

    public function ec(): BelongsTo
    {
        return $this->belongsTo(Ec::class);
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class, 'annee_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class)->where('actif', true);
    }

    public function salleRef(): BelongsTo
    {
        return $this->belongsTo(Salle::class, 'salle_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Fenêtre de prise de présence (config/presence.php)
    |--------------------------------------------------------------------------
    |
    | Ces trois méthodes sont le seul endroit où la fenêtre est calculée. Le
    | contrôleur de scan, la génération manuelle du QR Code et la commande
    | planifiée les appellent toutes : sans cela, chacun reconstituait ses
    | propres bornes et les trois ne décrivaient pas le même cours.
    |
    */

    /**
     * Horodatage de début du cours.
     */
    public function debutCours(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->heure_debut);
    }

    /**
     * Horodatage de fin du cours.
     *
     * Un cours dont l'heure de fin précède l'heure de début se termine le
     * lendemain (séance à cheval sur minuit) : la date est alors décalée d'un
     * jour, sans quoi la fenêtre serait vide.
     */
    public function finCours(): Carbon
    {
        $fin = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->heure_fin);

        if ($fin->lessThanOrEqualTo($this->debutCours())) {
            $fin->addDay();
        }

        return $fin;
    }

    /**
     * Instant à partir duquel un scan est accepté.
     */
    public function ouvertureScan(): Carbon
    {
        return $this->finCours()->subMinutes(config('presence.scan.minutes_avant_fin'));
    }

    /**
     * Instant après lequel un scan est refusé. Aucun token QR ne doit survivre
     * à cette borne, sinon la fenêtre affichée et la fenêtre réellement
     * exploitable divergent.
     */
    public function fermetureScan(): Carbon
    {
        return $this->finCours()->addMinutes(config('presence.scan.minutes_apres_fin'));
    }

    /**
     * Expiration à donner à un token généré maintenant : la durée de vie
     * nominale, plafonnée à la fermeture de la fenêtre de scan.
     */
    public function expirationTokenDepuis(Carbon $maintenant): Carbon
    {
        $expiration = $maintenant->copy()->addSeconds(config('presence.qr.ttl_secondes'));
        $fermeture  = $this->fermetureScan();

        return $expiration->greaterThan($fermeture) ? $fermeture : $expiration;
    }
}
