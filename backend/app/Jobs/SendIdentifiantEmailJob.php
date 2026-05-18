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

    public function __construct(Etudiant $etudiant)
    {
        $this->etudiant = $etudiant;
    }

    public function handle(): void
    {
        try {
            Mail::send('emails.identifiant', [
                'nom' => $this->etudiant->nom,
                'prenom' => $this->etudiant->prenom,
                'identifiant' => $this->etudiant->identifiant_unique,
                'filiere' => $this->etudiant->filiere->intitule,
                'annee' => $this->etudiant->anneeAcademique->libelle,
            ], function ($message) {
                $message->to($this->etudiant->email)
                    ->subject('Votre identifiant unique - Système de présence UAC');
            });

            Log::info("Email envoyé à {$this->etudiant->email} pour l'étudiant {$this->etudiant->matricule}");
        } catch (\Exception $e) {
            Log::error("Erreur envoi email à {$this->etudiant->email}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Échec définitif envoi email à {$this->etudiant->email}: " . $exception->getMessage());
    }
}
