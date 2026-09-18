<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AnneeAcademique;
use App\Models\Ec;
use App\Models\EmploiDuTemps;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Fermeture;
use App\Models\Filiere;
use App\Models\Groupe;
use App\Models\Notification;
use App\Models\PeriodeSemestre;
use App\Models\Programme;
use App\Models\Salle;
use App\Models\SupportTicket;
use App\Models\Ue;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde STRUCTUREL du cloisonnement multi-établissements, pour le groupe
 * admin.
 *
 * Jusqu'ici, le cloisonnement reposait entièrement sur le rappel manuel de
 * $this->authorizeEtablissement(...) (app/Traits/ScopedByEtablissement.php)
 * dans chaque méthode de contrôleur qui reçoit un modèle par liaison de route.
 * Quatre oublis ont été trouvés et corrigés par ailleurs (export PDF d'une
 * séance, rapport de filière, création/déplacement de séances sur le cours ou
 * la salle d'une autre faculté, inscription/déplacement d'étudiants) : rien
 * n'empêchait le prochain.
 *
 * Ce middleware tourne APRÈS SubstituteBindings (voir bootstrap/app.php) et
 * APRÈS ScopeByEtablissement (qui pose « scoped_etablissement_id » sur la
 * requête) : il inspecte chaque modèle Eloquent lié par la route et vérifie
 * son établissement, quelle que soit la méthode de contrôleur atteinte.
 *
 * Un modèle non déclaré ci-dessous — ni dans CHEMIN_ETABLISSEMENT, ni dans
 * MODELES_EXEMPTES — fait ÉCHOUER LA REQUÊTE hors production (exception
 * bruyante, visible en test et en développement) et est REFUSÉ en production
 * (404, comme toute ressource hors périmètre) : un nouveau modèle exposé sans
 * classification ne doit jamais passer au travers en silence.
 */
class RestrictModelsToEtablissement
{
    /**
     * Classe de modèle => chemin vers son établissement.
     * '' signifie une colonne etablissement_id directe sur le modèle.
     */
    private const CHEMIN_ETABLISSEMENT = [
        Etudiant::class       => 'filiere',
        Evenement::class      => 'filiere',
        Filiere::class        => '',
        Salle::class          => '',
        Ue::class             => 'filiere',
        // etablissement_id est denormalise sur Ec par EcObserver (depuis son UE) :
        // c'est une colonne directe, pas une relation a traverser.
        Ec::class             => '',
        Groupe::class         => 'filiere',
        EmploiDuTemps::class  => 'filiere',
        Programme::class      => '',
        PeriodeSemestre::class => '',
        Fermeture::class      => '',
    ];

    /**
     * Modèles délibérément exemptés : cloisonnés par un mécanisme AUTRE que
     * l'établissement, déjà vérifié dans leur propre contrôleur.
     */
    private const MODELES_EXEMPTES = [
        // Communes à l'université, choisies par établissement via
        // etablissements.annee_active_id — pas par une colonne sur l'année
        // elle-même (voir le docblock de AnneeAcademique).
        AnneeAcademique::class,
        // Scopées par user_id, vérifié dans NotificationController/TicketController.
        Notification::class,
        SupportTicket::class,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $etablissementId = $request->get('scoped_etablissement_id');

        // Super admin (aucun établissement scopé) : pas de cloisonnement.
        if (!$etablissementId) {
            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $modele) {
            if (!$modele instanceof Model) {
                continue;
            }

            $classe = get_class($modele);

            if (in_array($classe, self::MODELES_EXEMPTES, true)) {
                continue;
            }

            if (!array_key_exists($classe, self::CHEMIN_ETABLISSEMENT)) {
                $this->signalerModeleNonClasse($classe);
                abort(404, 'Ressource non trouvée.');
            }

            $cible = $modele;
            foreach (array_filter(explode('.', self::CHEMIN_ETABLISSEMENT[$classe])) as $relation) {
                $cible = $cible?->{$relation};
            }

            if (!$cible) {
                abort(404, 'Ressource non trouvée.');
            }

            // Une ressource sans établissement propre (ex. un jour férié
            // national, Fermeture::etablissement_id === null) n'est pas un cas
            // de fuite inter-établissements : rien n'est comparé, et la
            // décision revient au contrôleur, qui la traite explicitement
            // (voir CalendrierController::supprimerFermeture).
            //
            // 404, jamais 403, dans le cas contraire : ne pas confirmer
            // l'existence de la ressource à un admin qui n'y a pas droit —
            // même convention que ScopedByEtablissement::authorizeEtablissement().
            if ($cible->etablissement_id !== null && (int) $cible->etablissement_id !== (int) $etablissementId) {
                abort(404, 'Ressource non trouvée.');
            }
        }

        return $next($request);
    }

    private function signalerModeleNonClasse(string $classe): void
    {
        $message = "Garde de cloisonnement : le modèle {$classe} est lié à une route du groupe "
            . 'admin sans être classé dans ' . self::class . " — ni dans CHEMIN_ETABLISSEMENT "
            . '(chemin vers son établissement), ni dans MODELES_EXEMPTES (avec le motif de '
            . "l'exemption). Le cloisonnement multi-établissement ne peut pas être garanti pour lui.";

        Log::critical($message);

        // Hors production : échec bruyant et immédiat, pour qu'un nouveau
        // modèle exposé sans classification soit détecté en développement ou
        // en intégration continue, jamais découvert en production.
        if (!app()->isProduction()) {
            throw new \RuntimeException($message);
        }
    }
}
