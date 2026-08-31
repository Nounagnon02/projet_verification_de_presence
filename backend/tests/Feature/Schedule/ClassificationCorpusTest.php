<?php

namespace Tests\Feature\Schedule;

use App\Services\Schedule\ClassificateurExtraction;
use App\ValueObjects\DiagnosticExtraction;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Classification des documents réels du dépôt.
 *
 * RÉGRESSION COUVERTE
 *
 * L'extraction rendait un tableau vide pour les QUATRE emplois du temps
 * hebdomadaires du corpus — mesuré à 0 % dans tests/ia/RESULTATS.md — parce que
 * le pipeline exigeait une date calendaire et écartait en silence tout créneau
 * qui n'en avait pas. L'administrateur voyait « aucun créneau », sans rien qui
 * distingue un document vide d'un document incompris.
 *
 * Ces tests portent sur les fichiers RÉELS du dépôt, et non sur des doubles :
 * c'est le seul moyen de garantir qu'un emploi du temps de l'UAC ne sera plus
 * jamais déclaré vide. Ils passent au classificateur une extraction VIDE —
 * exactement ce que produisait l'ancien pipeline.
 */
class ClassificationCorpusTest extends TestCase
{
    private ClassificateurExtraction $classificateur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classificateur = new ClassificateurExtraction();

        if (empty(shell_exec('command -v pdftotext 2>/dev/null'))) {
            $this->markTestSkipped('pdftotext (poppler-utils) requis pour observer les documents.');
        }
    }

    private function racineDepot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function corpus(string $nom): string
    {
        $chemin = $this->racineDepot() . '/../tests/ia/corpus/' . $nom;

        if (!is_file($chemin)) {
            $this->markTestSkipped("Document de corpus absent : {$nom}");
        }

        return $chemin;
    }

    /** @return array<string, list<string>> */
    public static function emploisDuTemps(): array
    {
        return [
            'hebdomadaire simple' => ['edt-01-simple.pdf'],
            'abreviations'        => ['edt-02-abreviations.pdf'],
            'seances longues'     => ['edt-03-seances-longues.pdf'],
            'creneau unique'      => ['edt-04-creneau-unique.pdf'],
            'dates calendaires'   => ['edt-05-date.pdf'],
        ];
    }

    #[DataProvider('emploisDuTemps')]
    public function test_un_emploi_du_temps_n_est_jamais_declare_vide(string $fichier): void
    {
        $d = $this->classificateur->classer([], $this->corpus($fichier));

        $this->assertNotSame(
            DiagnosticExtraction::VIDE,
            $d->statut,
            "{$fichier} contient un emploi du temps : le declarer vide est un mensonge."
        );

        $this->assertSame(DiagnosticExtraction::NON_INTERPRETABLE, $d->statut);
    }

    #[DataProvider('emploisDuTemps')]
    public function test_le_message_dit_que_le_document_n_est_pas_vide(string $fichier): void
    {
        $d = $this->classificateur->classer([], $this->corpus($fichier));

        $this->assertStringContainsString('détecté', $d->message());
        $this->assertNotEmpty($d->remarques, 'Une remarque doit expliquer ce qui a ete observe.');
        $this->assertStringContainsString(
            'PAS un document vide',
            implode(' ', $d->remarques),
            "L'utilisateur doit lire explicitement que son document n'est pas vide."
        );
    }

    /**
     * Cas limite qui a motivé le raffinement des marqueurs : un emploi du temps
     * d'UN SEUL créneau ne porte qu'un nom de jour. Exiger deux repères
     * temporels le déclarait vide.
     */
    public function test_un_emploi_du_temps_d_un_seul_creneau_est_reconnu(): void
    {
        $d = $this->classificateur->classer([], $this->corpus('edt-04-creneau-unique.pdf'));

        $this->assertSame(DiagnosticExtraction::NON_INTERPRETABLE, $d->statut);
        $this->assertSame(1, max(count($d->indices['jours_detectes']), $d->indices['dates_detectees']));
    }

    /** Un planning daté n'affiche AUCUN nom de jour. */
    public function test_un_planning_date_est_reconnu_par_ses_dates(): void
    {
        $d = $this->classificateur->classer([], $this->corpus('edt-05-date.pdf'));

        $this->assertSame(DiagnosticExtraction::NON_INTERPRETABLE, $d->statut);
        $this->assertSame([], $d->indices['jours_detectes'], 'Ce document ne nomme aucun jour.');
        $this->assertGreaterThanOrEqual(2, $d->indices['dates_detectees']);
    }

    /**
     * Pendant indispensable : le seuil abaissé ne doit pas prendre une note
     * administrative pour un emploi du temps. Sans ce test, « ne jamais dire
     * vide » deviendrait « ne jamais rien refuser ».
     */
    public function test_une_note_administrative_est_bien_vide(): void
    {
        $d = $this->classificateur->classer([], $this->corpus('hors-01-note-de-service.pdf'));

        $this->assertSame(
            DiagnosticExtraction::VIDE,
            $d->statut,
            'Une note de service ne contient aucun creneau : elle doit etre declaree vide.'
        );
    }

    /** @return array<string, list<string>> */
    public static function maquettes(): array
    {
        return [
            'simple'              => ['maq-01-simple.pdf'],
            'volumes incoherents' => ['maq-02-volumes-incoherents.pdf'],
            'nombreux ec'         => ['maq-03-nombreux-ec.pdf'],
        ];
    }

    #[DataProvider('maquettes')]
    public function test_une_maquette_n_est_pas_un_emploi_du_temps(string $fichier): void
    {
        $d = $this->classificateur->classer([], $this->corpus($fichier));

        $this->assertSame(DiagnosticExtraction::VIDE, $d->statut);
    }

    /**
     * LE FICHIER RÉEL. Emploi du temps de l'IFRI, Licence 2, six colonnes de
     * jours, bandes horaires « 8h – 13h », plusieurs filières dans un même
     * document. C'est la forme réellement importée, et celle que le pipeline
     * rejetait intégralement.
     */
    public function test_le_fichier_reel_de_l_ifri_n_est_pas_vide(): void
    {
        $chemin = $this->racineDepot() . '/../2026_06_15_EmploiDuTemps_Cours_Licence2-3.pdf';

        if (!is_file($chemin)) {
            $this->markTestSkipped('Fichier reel absent du depot.');
        }

        $d = $this->classificateur->classer([], $chemin);

        $this->assertSame(DiagnosticExtraction::NON_INTERPRETABLE, $d->statut);
        $this->assertGreaterThanOrEqual(5, count($d->indices['jours_detectes']), 'Six colonnes de jours attendues.');
        $this->assertTrue($d->indices['document_lisible']);
    }

    // ── Conclusions quand l'extraction rend des données ────────────────

    public function test_des_creneaux_tous_compris_donnent_valide(): void
    {
        $d = $this->classificateur->classer([
            ['ec' => 'Algorithmique', 'jour' => 'Lundi', 'heure_debut' => '08:00', 'heure_fin' => '10:00'],
            ['ec' => 'Reseaux', 'jour' => 'Mardi', 'heure_debut' => '14h', 'heure_fin' => '16h'],
        ], $this->corpus('edt-01-simple.pdf'));

        $this->assertSame(DiagnosticExtraction::VALIDE, $d->statut);
        $this->assertCount(2, $d->retenus);
        $this->assertSame([], $d->ecartes);
    }

    public function test_un_melange_donne_partiel_avec_le_motif_de_chaque_ecart(): void
    {
        $d = $this->classificateur->classer([
            ['ec' => 'Algorithmique', 'jour' => 'Lundi', 'heure_debut' => '08:00', 'heure_fin' => '10:00'],
            ['ec' => 'Sans jour', 'heure_debut' => '14:00', 'heure_fin' => '16:00'],
        ], $this->corpus('edt-01-simple.pdf'));

        $this->assertSame(DiagnosticExtraction::PARTIEL, $d->statut);
        $this->assertCount(1, $d->retenus);
        $this->assertCount(1, $d->ecartes);
        $this->assertSame('jour', $d->ecartes[0]['champ']);
        $this->assertStringContainsString('Jour introuvable', $d->ecartes[0]['motif']);
    }

    public function test_des_creneaux_tous_ecartes_donnent_non_interpretable(): void
    {
        $d = $this->classificateur->classer([
            ['ec' => 'Sans jour ni heure'],
            ['jour' => 'Lundi'],
        ], $this->corpus('edt-01-simple.pdf'));

        $this->assertSame(DiagnosticExtraction::NON_INTERPRETABLE, $d->statut);
        $this->assertCount(2, $d->ecartes);
    }
}
