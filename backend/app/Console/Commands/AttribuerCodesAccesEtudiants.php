<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Etudiant;
use App\Services\CodeAccesEtudiant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Attribue un code d'accès aux étudiants qui n'en ont pas encore.
 *
 * Une base migrée depuis avant l'introduction du code (voir la migration
 * « code_acces_etudiant ») compte des étudiants dont la connexion est
 * refusée par un 409 explicite (« code_absent ») plutôt que par un
 * « identifiants invalides » trompeur. Cette commande la lève, un lot à la
 * fois — --envoyer confirme réellement l'envoi ; sans elle, la commande ne
 * fait que compter, pour vérifier avant d'agir sur toute une base.
 */
class AttribuerCodesAccesEtudiants extends Command
{
    protected $signature = 'etudiants:codes-acces
                            {--envoyer : Attribue réellement un code et l\'envoie ; sans cette option, la commande ne fait que compter}
                            {--limite=500 : Nombre maximal d\'étudiants traités en un passage}';

    protected $description = "Attribue et envoie un code d'accès aux étudiants qui n'en ont pas";

    public function handle(): int
    {
        $limite = (int) $this->option('limite');

        $requete = Etudiant::whereNull('code_acces')->limit($limite);
        $total = (clone $requete)->count();

        if ($total === 0) {
            $this->info('Aucun étudiant sans code d\'accès.');

            return self::SUCCESS;
        }

        if (!$this->option('envoyer')) {
            $this->warn("{$total} étudiant(s) sans code d'accès. Relancer avec --envoyer pour leur en attribuer un et le leur envoyer.");

            return self::SUCCESS;
        }

        $envoyes = 0;
        $echoues = 0;

        $requete->with(['filiere', 'anneeAcademique'])->chunkById(50, function ($etudiants) use (&$envoyes, &$echoues) {
            foreach ($etudiants as $etudiant) {
                $code = CodeAccesEtudiant::attribuer($etudiant);

                try {
                    Mail::send('emails.identifiant', [
                        'nom'         => $etudiant->nom,
                        'prenom'      => $etudiant->prenom,
                        'identifiant' => $etudiant->identifiant_unique,
                        'code'        => $code,
                        'filiere'     => $etudiant->filiere?->intitule ?? '',
                        'annee'       => $etudiant->anneeAcademique?->libelle ?? '',
                    ], function ($message) use ($etudiant) {
                        $message->to($etudiant->email)
                            ->subject('Vos identifiants - Système de présence UAC');
                    });
                    $envoyes++;
                } catch (\Throwable $e) {
                    // Le code est déjà attribué (haché en base) même si l'e-mail
                    // échoue : un renvoi manuel depuis l'administration reste
                    // possible, l'étudiant n'est jamais bloqué par cette panne.
                    Log::error("Erreur envoi du code d'accès à {$etudiant->matricule}: " . $e->getMessage());
                    $echoues++;
                }
            }
        });

        $this->info("{$envoyes} code(s) d'accès envoyé(s)" . ($echoues ? ", {$echoues} échec(s) d'envoi." : '.'));

        return self::SUCCESS;
    }
}
