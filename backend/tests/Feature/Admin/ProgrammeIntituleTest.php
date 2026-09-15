<?php

namespace Tests\Feature\Admin;

use App\Models\Etablissement;
use App\Models\Filiere;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Renommer un programme : l'intitulé seul, et au besoin les filières qui
 * suivent encore son motif « {intitulé} ({niveau}) ».
 */
class ProgrammeIntituleTest extends TestCase
{
    private string $sfx;
    private string $jeton;
    private Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sfx = Str::lower(Str::random(6));
        $etab = Etablissement::create(['code' => 'PG' . $this->sfx, 'nom' => 'Faculté P', 'email' => "pg-{$this->sfx}@test.local"]);
        $this->jeton = User::factory()->faculteAdmin($etab->id)->create(['email' => "prog-{$this->sfx}@test.local"])->createToken('t')->plainTextToken;

        $this->programme = Programme::create(['etablissement_id' => $etab->id, 'code' => 'IM' . $this->sfx, 'intitule' => 'Informatique et Mathématiques']);

        foreach (['L1' => 'Informatique et Mathématiques (L1)', 'L2' => 'Parcours IM renforcé'] as $niveau => $intitule) {
            Filiere::create([
                'code' => "IM{$this->sfx}-{$niveau}", 'intitule' => $intitule, 'niveau' => $niveau,
                'programme_id' => $this->programme->id, 'etablissement_id' => $etab->id,
            ]);
        }
    }

    public function test_renommer_le_programme_et_les_filieres_qui_suivent_son_motif(): void
    {
        $this->withToken($this->jeton)->putJson("/api/admin/programmes/{$this->programme->id}", [
            'intitule' => 'Informatique', 'renommer_filieres' => true, 'code' => 'AUTRE',
        ])->assertOk()->assertJsonPath('data.filieres_renommees', ["IM{$this->sfx}-L1"]);

        $this->programme->refresh();
        $this->assertSame('Informatique', $this->programme->intitule);
        $this->assertSame('IM' . $this->sfx, $this->programme->code, 'Le code ne change pas : il préfixe les codes des filières.');

        $intitules = Filiere::where('programme_id', $this->programme->id)->orderBy('niveau')->pluck('intitule')->all();
        $this->assertSame(['Informatique (L1)', 'Parcours IM renforcé'], $intitules, "Un intitulé personnalisé n'est pas touché.");
    }

    public function test_sans_l_option_les_filieres_gardent_leur_intitule(): void
    {
        $this->withToken($this->jeton)->putJson("/api/admin/programmes/{$this->programme->id}", ['intitule' => 'Informatique'])->assertOk();

        $this->assertSame('Informatique et Mathématiques (L1)', Filiere::where('code', "IM{$this->sfx}-L1")->value('intitule'));
    }

    public function test_un_programme_d_une_autre_faculte_est_introuvable(): void
    {
        $autre = Etablissement::create(['code' => 'PX' . $this->sfx, 'nom' => 'Autre', 'email' => "px-{$this->sfx}@test.local"]);
        $programme = Programme::create(['etablissement_id' => $autre->id, 'code' => 'XX' . $this->sfx, 'intitule' => 'Autre programme']);

        $this->withToken($this->jeton)->putJson("/api/admin/programmes/{$programme->id}", ['intitule' => 'Piraté'])->assertNotFound();
        $this->assertSame('Autre programme', $programme->fresh()->intitule);
    }
}
