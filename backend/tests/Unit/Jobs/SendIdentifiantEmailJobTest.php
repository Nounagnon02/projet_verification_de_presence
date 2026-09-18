<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendIdentifiantEmailJob;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Régression : ce job ne recevait pas le code d'accès (seul secret de la
 * connexion étudiante, CodeAccesEtudiant) et l'e-mail envoyé par sa faute
 * n'en contenait aucun. Il n'était de toute façon jamais dispatché — les
 * deux endpoints qui envoient un identifiant l'ignoraient et envoyaient
 * l'e-mail eux-mêmes, de façon synchrone (ImportController, StudentController).
 *
 * Mail::fake() ne convient pas ici : Mail::send() avec une vue et un tableau
 * n'est pas un Mailable, MailFake::sendMail() l'ignore silencieusement (voir
 * RenvoiIdentifiantsTest). On intercepte donc l'appel lui-même.
 */
class SendIdentifiantEmailJobTest extends TestCase
{
    public function test_le_job_transmet_le_code_en_clair_a_la_vue_de_l_email(): void
    {
        $sfx = Str::random(6);
        $filiere = Filiere::create(['code' => 'JOB' . $sfx, 'intitule' => 'Filière', 'niveau' => 'L1']);
        $etudiant = Etudiant::create([
            'nom' => 'JOB', 'prenom' => $sfx, 'matricule' => 'JOB-' . $sfx,
            'filiere_id' => $filiere->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("job-{$sfx}@example.test"), 'identifiant_unique' => 'JOB_' . $sfx,
        ]);

        Mail::shouldReceive('send')
            ->once()
            ->withArgs(function (string $view, array $data) {
                return $view === 'emails.identifiant' && $data['code'] === '482913';
            });

        (new SendIdentifiantEmailJob($etudiant, '482913'))->handle();
    }
}
