<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RestrictModelsToEtablissement;
use App\Models\AnneeAcademique;
use App\Models\Etudiant;
use App\Models\Filiere;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Le garde structurel, exercé directement (sans passer par le routeur ni un
 * établissement réel) : c'est le comportement du middleware lui-même, pas une
 * route particulière, qui est sous test.
 */
class RestrictModelsToEtablissementTest extends TestCase
{
    private function requeteAvec(int $etablissementId, array $parametresLies): Request
    {
        $request = Request::create('/api/admin/test');
        $request->attributes->set('scoped_etablissement_id', $etablissementId);
        $request->merge(['scoped_etablissement_id' => $etablissementId]);

        $route = new Route('GET', '/api/admin/test', []);
        $route->bind($request); // initialise $route->parameters avant setParameter()
        foreach ($parametresLies as $nom => $valeur) {
            $route->setParameter($nom, $valeur);
        }
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function passer(Request $request): bool
    {
        $atteint = false;
        (new RestrictModelsToEtablissement())->handle($request, function () use (&$atteint) {
            $atteint = true;

            return response()->json([]);
        });

        return $atteint;
    }

    public function test_un_super_admin_n_est_jamais_bloque(): void
    {
        $request = $this->requeteAvec(0, ['etudiant' => new Etudiant()]);
        // 0 est falsy : reproduit scoped_etablissement_id absent (super admin).
        $request->attributes->set('scoped_etablissement_id', null);
        $request->merge(['scoped_etablissement_id' => null]);

        $this->assertTrue($this->passer($request));
    }

    public function test_un_modele_dont_l_etablissement_correspond_passe(): void
    {
        $filiere = new Filiere(['etablissement_id' => 7]);
        $etudiant = (new Etudiant())->setRelation('filiere', $filiere);

        $this->assertTrue($this->passer($this->requeteAvec(7, ['student' => $etudiant])));
    }

    public function test_un_modele_dont_l_etablissement_diverge_est_refuse(): void
    {
        $filiere = new Filiere(['etablissement_id' => 9]);
        $etudiant = (new Etudiant())->setRelation('filiere', $filiere);

        try {
            $this->passer($this->requeteAvec(7, ['student' => $etudiant]));
            $this->fail('Une HttpException 404 était attendue.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_un_modele_exempte_passe_quel_que_soit_son_etat(): void
    {
        $this->assertTrue($this->passer($this->requeteAvec(7, ['annee' => new AnneeAcademique()])));
    }

    /**
     * Régression : sans cette classification, un modèle Eloquent lié par une
     * route admin traverserait le garde sans aucune vérification.
     */
    public function test_un_modele_non_classe_fait_echouer_la_requete_hors_production(): void
    {
        $this->assertFalse($this->app->isProduction());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(QrCode::class);

        $this->passer($this->requeteAvec(7, ['qrcode' => new QrCode()]));
    }

    public function test_un_modele_non_classe_est_refuse_en_production_plutot_que_de_planter(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->passer($this->requeteAvec(7, ['qrcode' => new QrCode()]));
            $this->fail('Une HttpException 404 était attendue.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
