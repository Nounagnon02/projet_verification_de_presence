<?php

namespace App\Jobs;

use App\Models\Etudiant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendIdentifiantEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected $etudiant;

    /**
     * Code d'accès en clair : la base n'en garde que le hachage
     * (CodeAccesEtudiant), il ne circule que par cet e-mail. Le driver de
     * file « database » le dépose donc brièvement, en clair, dans la colonne
     * payload de la table jobs — jusqu'au prochain passage du worker planifié
     * (au plus une minute, routes/console.php), comme n'importe quel autre
     * argument de job.
     */
    protected ?string $code;

    public function __construct(Etudiant $etudiant, ?string $code = null)
    {
        $this->etudiant = $etudiant;
        $this->code = $code;
    }

    public function handle(): void
    {
        try {
            Mail::send('emails.identifiant', [
                'nom' => $this->etudiant->nom,
                'prenom' => $this->etudiant->prenom,
                'identifiant' => $this->etudiant->identifiant_unique,
                'code' => $this->code,
                'filiere' => $this->etudiant->filiere->intitule,
                'annee' => $this->etudiant->anneeAcademique->libelle,
            ], function ($message) {
                $message->to($this->etudiant->email)
                    ->subject('Vos identifiants - Système de présence UAC');
            });

            Log::info("Email envoyé à {$this->etudiant->email} pour l'étudiant {$this->etudiant->matricule}");
        } catch (\Throwable $e) {
            // \Throwable et non \Exception : avec le pilote de file « sync »
            // (tests, ou une mauvaise configuration en production), ce job
            // s'exécute EN LIGNE dans dispatch() — une \Error de configuration
            // du mailer (classe de transport absente) remonterait alors
            // jusqu'à la requête HTTP qui a créé l'étudiant et la ferait
            // échouer en 500, alors que l'inscription est déjà en base.
            // On journalise et on ne relance pas : ni ce job ni les deux
            // méthodes synchrones qu'il remplace n'ont jamais fait dépendre
            // l'inscription du succès de l'e-mail.
            Log::error("Erreur envoi email à {$this->etudiant->email}: " . $e->getMessage());
        }
    }

    /** Timeout dépassé (SMTP qui pend) : seul cas qui atteint encore ce point. */
    public function failed(\Throwable $exception): void
    {
        Log::error("Échec définitif envoi email à {$this->etudiant->email}: " . $exception->getMessage());
    }
}
