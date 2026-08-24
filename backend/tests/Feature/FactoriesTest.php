<?php

namespace Tests\Feature;

use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\QrCode;
use App\Models\Salle;
use App\Models\Ue;
use App\Services\IdentifiantService;
use Tests\TestCase;

/**
 * Les factories sont de l'outillage de test : si elles produisent des objets
 * incoherents, tous les tests ecrits par-dessus mesurent autre chose que ce
 * qu'ils croient. Ce fichier les verifie une fois pour toutes.
 *
 * Le piege principal : par defaut, chaque cle etrangere invoque sa propre
 * factory. Un Evenement construit sans precaution rattache donc son EC, sa
 * filiere et son annee a TROIS etablissements differents — et tout test de
 * cloisonnement bati dessus devient faux sans jamais echouer.
 */
class FactoriesTest extends TestCase
{
    public function test_chaque_factory_produit_un_enregistrement_persistable(): void
    {
        foreach ([
            Etablissement::class,
            Filiere::class,
            Ue::class,
            Ec::class,
            Salle::class,
            Evenement::class,
            Etudiant::class,
            QrCode::class,
            Presence::class,
        ] as $modele) {
            $instance = $modele::factory()->create();

            $this->assertTrue(
                $instance->exists,
                "La factory de {$modele} ne persiste pas son enregistrement."
            );
        }
    }

    public function test_deux_appels_ne_heurtent_pas_les_contraintes_d_unicite(): void
    {
        // « code » et « email » sont uniques sur plusieurs tables : une factory
        // a valeur figee casse des le deuxieme appel d'une meme suite.
        $a = Etablissement::factory()->create();
        $b = Etablissement::factory()->create();

        $this->assertNotSame($a->code, $b->code);
        $this->assertNotSame($a->email, $b->email);

        $this->assertNotSame(
            Filiere::factory()->create()->code,
            Filiere::factory()->create()->code,
        );
    }

    public function test_les_annees_successives_ont_des_libelles_distincts(): void
    {
        $libelles = collect(range(1, 3))
            ->map(fn () => \App\Models\AnneeAcademique::factory()->create()->libelle);

        $this->assertCount(3, $libelles->unique(), 'Deux années portent le même libellé.');
    }

    public function test_une_seule_annee_est_active_par_defaut(): void
    {
        // L'invariant metier : une annee active a la fois. La factory ne doit pas
        // en activer une au passage.
        \App\Models\AnneeAcademique::factory()->count(3)->create();

        $this->assertSame(
            0,
            \App\Models\AnneeAcademique::where('active', true)->count(),
            'La factory active une année alors que le choix appartient au test.'
        );

        \App\Models\AnneeAcademique::factory()->active()->create();
        $this->assertSame(1, \App\Models\AnneeAcademique::where('active', true)->count());
    }

    public function test_l_etat_pour_ec_rattache_l_evenement_au_bon_cursus(): void
    {
        $etab    = Etablissement::factory()->create();
        $annee   = \App\Models\AnneeAcademique::factory()->active()->create(['etablissement_id' => $etab->id]);
        $filiere = Filiere::factory()->create(['etablissement_id' => $etab->id]);
        $ue      = Ue::factory()->pour($filiere, $annee)->create();
        $ec      = Ec::factory()->create(['ue_id' => $ue->id]);

        $evenement = Evenement::factory()->pourEc($ec)->create();

        $this->assertSame($filiere->id, $evenement->filiere_id);
        $this->assertSame($annee->id, $evenement->annee_id);
        $this->assertSame($ec->id, $evenement->ec_id);
    }

    public function test_l_identifiant_unique_suit_le_format_du_cahier_des_charges(): void
    {
        $etab    = Etablissement::factory()->create();
        $annee   = \App\Models\AnneeAcademique::factory()->create(['etablissement_id' => $etab->id]);
        $filiere = Filiere::factory()->create(['etablissement_id' => $etab->id]);

        $etudiant = Etudiant::factory()->pour($filiere, $annee)->create();

        $this->assertNotEmpty($etudiant->identifiant_unique);
        $this->assertSame(
            IdentifiantService::generate(
                $etudiant->nom,
                $etudiant->prenom,
                $etudiant->matricule,
                $filiere->id,
                $annee->id,
            ),
            $etudiant->identifiant_unique,
        );
    }

    public function test_l_etat_fenetre_ouverte_rend_l_evenement_reellement_scannable(): void
    {
        // La fenetre est ancree sur l'heure de FIN : un evenement « en cours »
        // au sens naif n'est pas scannable. C'est tout l'objet de cet etat.
        $evenement = Evenement::factory()->fenetreOuverte()->create();

        $maintenant = now();
        $this->assertTrue(
            $maintenant->greaterThanOrEqualTo($evenement->ouvertureScan()),
            'La fenêtre devrait déjà être ouverte.'
        );
        $this->assertTrue(
            $maintenant->lessThanOrEqualTo($evenement->fermetureScan()),
            'La fenêtre devrait encore être ouverte.'
        );
    }

    public function test_les_etats_de_qr_code_et_de_presence_sont_coherents(): void
    {
        $this->assertTrue(QrCode::factory()->expire()->create()->isExpired());
        $this->assertFalse(QrCode::factory()->consomme()->create()->actif);

        $this->assertSame('suspect', Presence::factory()->suspecte()->create()->statut);

        $rejetee = Presence::factory()->rejetee('Motif de test')->create();
        $this->assertSame('rejete', $rejetee->statut);
        $this->assertSame('Motif de test', $rejetee->validation_motif);
    }

    public function test_les_etats_de_salle_couvrent_les_modes_de_verification(): void
    {
        $this->assertNull(Salle::factory()->sansGps()->create()->latitude);
        $this->assertTrue(Salle::factory()->horsReseau()->create()->hors_reseau);
        $this->assertFalse(Salle::factory()->inactive()->create()->actif);

        $avecWifi = Salle::factory()->avecWifi('UAC-TEST')->create();
        $this->assertSame('UAC-TEST', $avecWifi->ssid_attendu);
    }
}
