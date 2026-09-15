<?php

namespace Tests\Feature\Admin;

use App\Models\Ec;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\Evenement;
use App\Models\Filiere;
use App\Models\Presence;
use App\Models\Ue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Un export filtré dit comment il a été filtré.
 *
 * Aucun ne le disait : la liste d'une filière sur une semaine pouvait passer
 * pour l'historique complet.
 */
class ExportCriteresTest extends TestCase
{
    private Etablissement $etab;
    private Filiere $filiere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-14 10:15:00'));

        $sfx = Str::random(4);
        $this->etab = Etablissement::create(['code' => 'EXC' . $sfx, 'nom' => 'Institut ' . $sfx, 'email' => strtolower("exc.{$sfx}@test.local")]);
        $this->filiere = Filiere::create([
            'code' => 'EXF' . $sfx, 'intitule' => 'Filière exportée', 'niveau' => 'L1', 'etablissement_id' => $this->etab->id,
        ]);
        $ue = Ue::create([
            'code' => 'UEX' . $sfx, 'intitule' => 'UE', 'filiere_id' => $this->filiere->id,
            'annee_id' => $this->anneeActive()->id, 'semestre' => 1, 'volume_horaire' => 30,
        ]);
        $ec = Ec::create(['ue_id' => $ue->id, 'code' => 'ECX' . $sfx, 'intitule' => 'Cours exporté', 'volume_horaire' => 30]);
        $seance = Evenement::create([
            'ec_id' => $ec->id, 'filiere_id' => $this->filiere->id, 'annee_id' => $ue->annee_id,
            'date' => '2026-09-10', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'termine',
        ]);
        $etudiant = Etudiant::create([
            'nom' => 'EXPORT', 'prenom' => 'Test', 'matricule' => 'EXP-' . $sfx,
            'filiere_id' => $this->filiere->id, 'annee_id' => $this->anneeActive()->id,
            'email' => strtolower("exp-{$sfx}@example.test"), 'identifiant_unique' => 'EXP_' . $sfx,
        ]);
        Presence::create([
            'etudiant_id' => $etudiant->id, 'evenement_id' => $seance->id,
            'heure_scan' => '2026-09-10 09:50:00', 'statut' => 'rejete', 'device_fingerprint' => 'tel-exp',
        ]);
    }

    public function test_le_nom_du_fichier_resume_les_filtres(): void
    {
        $filtres = "filiere_id={$this->filiere->id}&date_debut=2026-09-01&date_fin=2026-09-14&statut=rejete";

        $reponse = $this->enTantQue($this->superAdmin())->get("/api/admin/presence/export?format=csv&{$filtres}")->assertOk();

        $this->assertStringContainsString(
            "filename=historique_{$this->filiere->code}_du-2026-09-01_au-2026-09-14_rejetes_export-2026-09-14.csv",
            $reponse->headers->get('Content-Disposition')
        );

        $sansFiltre = $this->enTantQue($this->superAdmin())->get('/api/admin/presence/export?format=csv')->assertOk();
        $this->assertStringContainsString('filename=historique_complet_export-2026-09-14.csv', $sansFiltre->headers->get('Content-Disposition'));
    }

    public function test_l_excel_indique_ses_criteres_son_entite_et_ses_semestres(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Export', 'email' => 'exp-' . Str::random(6) . '@example.test',
            'role' => 'faculte_admin', 'etablissement_id' => $this->etab->id,
        ]);
        $filtres = "filiere_id={$this->filiere->id}&date_debut=2026-09-01&date_fin=2026-09-14&statut=rejete&search=test&tri=etudiant&sens=asc";

        $feuille = $this->feuille($this->enTantQue($admin)->get("/api/admin/presence/export?format=xlsx&{$filtres}")->assertOk());

        $this->assertSame('Historique des présences', $feuille->getCell('A1')->getValue());
        $this->assertStringContainsString('1 présence(s)', $feuille->getCell('A2')->getValue());
        $this->assertStringNotContainsString('Admin Export', $feuille->getCell('A2')->getValue());

        $criteres = $this->criteres($feuille);
        $this->assertSame("{$this->etab->code} — {$this->etab->nom}", $criteres['Entité']);
        $this->assertSame('S1', $criteres['Semestre(s)']);
        $this->assertSame("{$this->filiere->code} — Filière exportée", $criteres['Filière']);
        $this->assertSame('du 01/09/2026 au 14/09/2026', $criteres['Période']);
        $this->assertSame('Rejeté', $criteres['Statut']);
        $this->assertSame('« test »', $criteres['Recherche']);
        // Ni tri ni auteur dans les critères.
        $this->assertArrayNotHasKey('Tri', $criteres);
        $this->assertArrayNotHasKey('Exporté par', $criteres);

        // Le tableau suit les critères, avec ses filtres de tableur.
        $entete = $this->ligneEntete($feuille);
        $this->assertSame('Test EXPORT', $feuille->getCell('A' . ($entete + 1))->getValue());
        $this->assertSame("A{$entete}:M" . ($entete + 1), $feuille->getAutoFilter()->getRange());
    }

    public function test_sans_filtre_l_excel_le_dit(): void
    {
        $feuille = $this->feuille($this->enTantQue($this->superAdmin())->get('/api/admin/presence/export?format=xlsx&tri=')->assertOk());

        $criteres = $this->criteres($feuille);
        $this->assertSame('Toutes les entités', $criteres['Entité']);
        $this->assertSame('Aucun filtre', $criteres['Filtres']);
    }

    public function test_les_semestres_couverts_par_l_export_sont_indiques(): void
    {
        // Une seconde présence, dans une UE du semestre 2.
        $ue2 = Ue::create([
            'code' => 'UEX2' . Str::random(4), 'intitule' => 'UE S2', 'filiere_id' => $this->filiere->id,
            'annee_id' => $this->anneeActive()->id, 'semestre' => 2, 'volume_horaire' => 30,
        ]);
        $ec2 = Ec::create(['ue_id' => $ue2->id, 'code' => 'ECX2' . Str::random(4), 'intitule' => 'Cours S2', 'volume_horaire' => 30]);
        $seance2 = Evenement::create([
            'ec_id' => $ec2->id, 'filiere_id' => $this->filiere->id, 'annee_id' => $ue2->annee_id,
            'date' => '2026-09-11', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'statut' => 'termine',
        ]);
        Presence::create([
            'etudiant_id' => Etudiant::where('filiere_id', $this->filiere->id)->firstOrFail()->id, 'evenement_id' => $seance2->id,
            'heure_scan' => '2026-09-11 09:50:00', 'statut' => 'valide', 'device_fingerprint' => 'tel-exp',
        ]);
        $admin = $this->superAdmin();

        $tout = $this->criteres($this->feuille($this->enTantQue($admin)
            ->get("/api/admin/presence/export?format=xlsx&filiere_id={$this->filiere->id}")->assertOk()));
        $this->assertSame('S1, S2', $tout['Semestre(s)']);

        // Filtré sur un semestre : c'est lui, et ce n'est plus « aucun filtre ».
        $s2 = $this->criteres($this->feuille($this->enTantQue($admin)
            ->get('/api/admin/presence/export?format=xlsx&semestre=2')->assertOk()));
        $this->assertSame('S2', $s2['Semestre(s)']);
        $this->assertArrayNotHasKey('Filtres', $s2);
    }

    public function test_la_page_du_pdf_affiche_entite_et_semestres_sans_tri_ni_auteur(): void
    {
        $html = view('reports.history', [
            'presences'      => collect(),
            'origines'       => [],
            'libellesStatut' => Presence::LIBELLES_STATUT,
            'contexte'       => [
                'criteres'   => [
                    'entite'    => 'IFRI — Institut',
                    'semestres' => 'S1, S2',
                    'lignes'    => [['Filière', 'GEA-L1 — Gestion']],
                    'filtre'    => true,
                    'fichier'   => [],
                ],
                'exporte_le' => '14/09/2026 à 11:59',
            ],
            'date'  => '14/09/2026 11:59',
            'title' => 'Historique des Présences',
            'total' => 0,
        ])->render();

        $this->assertStringContainsString('<th>Entité</th><td>IFRI — Institut</td>', $html);
        $this->assertStringContainsString('<th>Semestre(s)</th><td>S1, S2</td>', $html);
        $this->assertStringNotContainsString('Périmètre', $html);
        $this->assertStringNotContainsString('Exporté par', $html);
        $this->assertStringNotContainsString('<th>Tri</th>', $html);
        $this->assertStringNotContainsString('Aucun filtre', $html);
    }

    public function test_le_pdf_filtre_se_genere(): void
    {
        $this->enTantQue($this->superAdmin())
            ->get("/api/admin/presence/export?format=pdf&filiere_id={$this->filiere->id}&statut=rejete")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_l_export_des_rapports_suit_les_colonnes_et_nomme_ses_criteres(): void
    {
        $reponse = $this->enTantQue($this->superAdmin())
            ->get("/api/admin/reports/excel/export?filiere_id={$this->filiere->id}&colonnes=name,status")
            ->assertOk();

        $contenu = ltrim($reponse->streamedContent(), "\xEF\xBB\xBF");
        $lignes = array_values(array_filter(explode("\n", $contenu)));

        $this->assertSame('Étudiant,Statut', $lignes[0]);
        $this->assertSame('"EXPORT Test",Rejeté', $lignes[1]);
        $this->assertStringContainsString(
            "filename=presences_{$this->filiere->code}_export-2026-09-14.csv",
            $reponse->headers->get('Content-Disposition')
        );
    }

    public function test_l_export_des_rapports_applique_tous_les_filtres_de_la_page(): void
    {
        $admin = $this->superAdmin();
        $lignes = fn (string $filtres) => count(array_filter(explode("\n", ltrim(
            $this->enTantQue($admin)->get("/api/admin/reports/excel/export?{$filtres}")->assertOk()->streamedContent(),
            "\xEF\xBB\xBF"
        ))));

        // Le semestre n'était pas appliqué : l'export gardait toutes les présences.
        $this->assertSame(2, $lignes("filiere_id={$this->filiere->id}&semestre=1"));
        $this->assertSame(1, $lignes("filiere_id={$this->filiere->id}&semestre=2"));
    }

    /** Critères lus en tête de feuille : libellé en A, valeur en B, jusqu'à la ligne vide. */
    private function criteres(Worksheet $feuille): array
    {
        $criteres = [];
        for ($ligne = 3; ($libelle = $feuille->getCell("A{$ligne}")->getValue()) !== null && $libelle !== ''; $ligne++) {
            $criteres[$libelle] = $feuille->getCell("B{$ligne}")->getValue();
        }

        return $criteres;
    }

    private function ligneEntete(Worksheet $feuille): int
    {
        for ($ligne = 1; $ligne <= 30; $ligne++) {
            if ($feuille->getCell("A{$ligne}")->getValue() === 'Étudiant') {
                return $ligne;
            }
        }

        $this->fail("Ligne d'en-tête du tableau introuvable.");
    }

    private function feuille($reponse): Worksheet
    {
        $chemin = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';
        file_put_contents($chemin, $reponse->getContent());
        $feuille = IOFactory::load($chemin)->getActiveSheet();
        unlink($chemin);

        return $feuille;
    }

    private function enTantQue(User $utilisateur): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $utilisateur->createToken('t')->plainTextToken);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['email' => 'exp-' . Str::random(6) . '@example.test', 'role' => 'super_admin']);
    }
}
